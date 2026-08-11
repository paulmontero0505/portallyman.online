<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']); exit;
}

$id          = isset($data['id']) ? (int)$data['id'] : 0;
$titulo      = trim($data['titulo'] ?? '');
$descripcion = trim($data['descripcion'] ?? '');
$prioridad   = $data['prioridad'] ?? 'media';
$fechaLimite = trim($data['fecha_limite'] ?? '');
$alLote      = !empty($data['aplicar_a_lote']);

if ($titulo === '')           { echo json_encode(['success'=>false,'error'=>'Indica el título de la tarea.']); exit; }
if (mb_strlen($titulo) > 180) { echo json_encode(['success'=>false,'error'=>'El título supera los 180 caracteres.']); exit; }
if (!array_key_exists($prioridad, tk_prioridades())) $prioridad = 'media';

if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $fechaLimite)) {
    echo json_encode(['success'=>false,'error'=>'Fecha límite inválida.']); exit;
}
if (strlen($fechaLimite) === 16) $fechaLimite .= ':00';
if (strtotime($fechaLimite) === false) {
    echo json_encode(['success'=>false,'error'=>'Fecha límite inválida.']); exit;
}

$descSql   = $descripcion !== '' ? $descripcion : null;
$creadoPor = $_SESSION['user_name'] ?? 'Administrador';
$creadoUid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if ($id > 0) {
    $st = mysqli_prepare($conn,
        "SELECT id, estado, lote_id, titulo, fecha_limite FROM tareas WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($st, 'i', $id);
    mysqli_stmt_execute($st);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);

    $permiso = tk_puede_editar($row);
    if (!$permiso['ok']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $permiso['error']]); exit;
    }

    mysqli_begin_transaction($conn);
    try {
        $st = mysqli_prepare($conn,
            "UPDATE tareas SET titulo=?, descripcion=?, prioridad=?, fecha_limite=? WHERE id=?");
        mysqli_stmt_bind_param($st, 'ssssi', $titulo, $descSql, $prioridad, $fechaLimite, $id);
        if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_stmt_error($st));
        mysqli_stmt_close($st);

        $detalle = 'Título: «' . $row['titulo'] . '» → «' . $titulo . '». '
                 . 'Fecha 1: ' . $row['fecha_limite'] . ' → ' . $fechaLimite . '.';
        tk_historial($conn, $id, 'editada', $detalle);

        $afectadas = 1;
        if ($alLote && $row['lote_id']) {
            $lote = (int)$row['lote_id'];
            $st = mysqli_prepare($conn,
                "UPDATE tareas SET titulo=?, descripcion=?, prioridad=?, fecha_limite=?
                  WHERE lote_id=? AND id<>? AND estado='pendiente'");
            mysqli_stmt_bind_param($st, 'ssssii', $titulo, $descSql, $prioridad, $fechaLimite, $lote, $id);
            if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_stmt_error($st));
            $afectadas += mysqli_stmt_affected_rows($st);
            mysqli_stmt_close($st);

            $rs = mysqli_query($conn,
                "SELECT id FROM tareas WHERE lote_id=$lote AND id<>$id AND estado='pendiente'");
            while ($h = mysqli_fetch_assoc($rs)) {
                tk_historial($conn, (int)$h['id'], 'editada', 'Editada junto con su lote. ' . $detalle);
            }
        }

        mysqli_commit($conn);
        echo json_encode(['success' => true, 'id' => $id, 'afectadas' => $afectadas]);
    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo json_encode(['success' => false, 'error' => 'No se pudo guardar: ' . $e->getMessage()]);
    }
    exit;
}

$destinatarios = $data['destinatarios'] ?? [];
if (!is_array($destinatarios) || !count($destinatarios)) {
    echo json_encode(['success'=>false,'error'=>'Elige al menos un destinatario.']); exit;
}
$ids = array_values(array_unique(array_filter(array_map('intval', $destinatarios), fn($v) => $v > 0)));
if (!count($ids)) {
    echo json_encode(['success'=>false,'error'=>'Destinatarios inválidos.']); exit;
}
if (count($ids) > 50) {
    echo json_encode(['success'=>false,'error'=>'No se pueden crear más de 50 tareas de una vez.']); exit;
}

$inList = implode(',', $ids);
$rs = mysqli_query($conn,
    "SELECT u.id, u.nombre, u.rol, u.soporte_de_id, c.nombre AS coordinador_nombre
       FROM usuarios u
       LEFT JOIN usuarios c ON c.id = u.soporte_de_id
      WHERE u.id IN ($inList) AND u.estado='Activo' AND u.rol IN ('Coordinador','Soporte')");
$validos = [];
while ($u = mysqli_fetch_assoc($rs)) $validos[(int)$u['id']] = $u;

if (count($validos) !== count($ids)) {
    echo json_encode(['success' => false,
        'error' => 'Algún destinatario no existe, está inactivo o no puede recibir tareas.']); exit;
}

mysqli_begin_transaction($conn);
try {
    $ins = mysqli_prepare($conn,
        "INSERT INTO tareas
            (lote_id, titulo, descripcion, prioridad,
             asignado_id, asignado_nombre, asignado_rol,
             coordinador_ref_id, coordinador_ref_nombre,
             fecha_limite, estado, creado_por, creado_por_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente', ?, ?)");

    $loteId  = null;
    $creados = [];

    foreach ($ids as $uid) {
        $u       = $validos[$uid];
        $nombre  = $u['nombre'];
        $rolAsig = $u['rol'];
        $refCoord    = ($rolAsig === 'Soporte' && $u['soporte_de_id'] !== null)
                     ? (int)$u['soporte_de_id'] : null;
        $refCoordNom = ($rolAsig === 'Soporte') ? $u['coordinador_nombre'] : null;

        mysqli_stmt_bind_param($ins, 'isssississsi',
            $loteId, $titulo, $descSql, $prioridad,
            $uid, $nombre, $rolAsig,
            $refCoord, $refCoordNom,
            $fechaLimite, $creadoPor, $creadoUid);
        if (!mysqli_stmt_execute($ins)) throw new Exception(mysqli_stmt_error($ins));

        $nuevoId = (int)mysqli_insert_id($conn);
        $creados[] = $nuevoId;

        if ($loteId === null) {
            $loteId = $nuevoId;
            if (!mysqli_query($conn, "UPDATE tareas SET lote_id=$nuevoId WHERE id=$nuevoId")) {
                throw new Exception(mysqli_error($conn));
            }
        }

        tk_historial($conn, $nuevoId, 'creada',
            'Asignada a ' . $nombre . ' (' . tk_rol_label($rolAsig) . '). '
          . 'Fecha límite: ' . $fechaLimite . '.');
    }
    mysqli_stmt_close($ins);

    mysqli_commit($conn);
    echo json_encode([
        'success'  => true,
        'ids'      => $creados,
        'lote_id'  => $loteId,
        'creadas'  => count($creados),
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'No se pudo crear: ' . $e->getMessage()]);
}
