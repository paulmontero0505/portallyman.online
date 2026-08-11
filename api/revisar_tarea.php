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

$id         = isset($data['id']) ? (int)$data['id'] : 0;
$veredicto  = $data['veredicto'] ?? '';
$nota       = (isset($data['nota']) && $data['nota'] !== '' && $data['nota'] !== null)
            ? (int)$data['nota'] : null;
$comentario = trim($data['comentario'] ?? '');
$fecha2     = trim($data['fecha_limite_2'] ?? '');
$motivo     = trim($data['prorroga_motivo'] ?? '');

if ($id <= 0) {
    echo json_encode(['success'=>false,'error'=>'ID de tarea inválido.']); exit;
}
if (!in_array($veredicto, ['aprobada','observada','rechazada'], true)) {
    echo json_encode(['success'=>false,'error'=>'Veredicto inválido.']); exit;
}

$st = mysqli_prepare($conn, "SELECT * FROM tareas WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

$permiso = tk_puede_revisar($row);
if (!$permiso['ok']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $permiso['error']]); exit;
}

if ($nota !== null && !array_key_exists($nota, ed_escala())) {
    echo json_encode(['success'=>false,'error'=>'La nota debe estar entre 1 y 5.']); exit;
}
if ($veredicto === 'aprobada' && $nota === null) {
    echo json_encode(['success'=>false,'error'=>'Pon una nota del 1 al 5 para aprobar la tarea.']); exit;
}
if ($veredicto === 'rechazada' && ($nota === null || $comentario === '')) {
    echo json_encode(['success'=>false,
        'error'=>'Para rechazar hacen falta la nota y un comentario que explique el motivo.']); exit;
}
if ($veredicto === 'observada' && $comentario === '') {
    echo json_encode(['success'=>false,
        'error'=>'Escribe qué debe corregir antes de devolver la tarea.']); exit;
}
if (mb_strlen($comentario) > 4000) {
    echo json_encode(['success'=>false,'error'=>'El comentario es demasiado largo.']); exit;
}

$aplicaProrroga = false;
if ($fecha2 !== '') {
    if ($veredicto !== 'observada') {
        echo json_encode(['success'=>false,
            'error'=>'Solo se puede dar una 2ª fecha cuando devuelves la tarea con observaciones.']); exit;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $fecha2)) {
        echo json_encode(['success'=>false,'error'=>'2ª fecha inválida.']); exit;
    }
    if (strlen($fecha2) === 16) $fecha2 .= ':00';
    if (strtotime($fecha2) <= strtotime($row['fecha_limite'])) {
        echo json_encode(['success'=>false,
            'error'=>'La 2ª fecha debe ser posterior a la fecha límite original.']); exit;
    }
    if ($motivo === '') {
        echo json_encode(['success'=>false,
            'error'=>'Indica el motivo de la prórroga. Una prórroga sin motivo es indistinguible de un error de digitación.']); exit;
    }
    $aplicaProrroga = true;
}

$comentSql  = $comentario !== '' ? $comentario : null;
$revisadoP  = $_SESSION['user_name'] ?? 'Administrador';
$revisadoId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

mysqli_begin_transaction($conn);
try {
    if ($aplicaProrroga) {
        $st = mysqli_prepare($conn,
            "UPDATE tareas
                SET estado=?, nota=?, comentario_admin=?,
                    revisado_por=?, revisado_por_id=?, revisado_at=NOW(),
                    fecha_limite_2=?, prorroga_motivo=?,
                    prorroga_por=?, prorroga_por_id=?, prorroga_at=NOW()
              WHERE id=?");
        mysqli_stmt_bind_param($st, 'sississsii',
            $veredicto, $nota, $comentSql,
            $revisadoP, $revisadoId,
            $fecha2, $motivo, $revisadoP, $revisadoId, $id);
    } else {
        $st = mysqli_prepare($conn,
            "UPDATE tareas
                SET estado=?, nota=?, comentario_admin=?,
                    revisado_por=?, revisado_por_id=?, revisado_at=NOW()
              WHERE id=?");
        mysqli_stmt_bind_param($st, 'sissii',
            $veredicto, $nota, $comentSql, $revisadoP, $revisadoId, $id);
    }
    if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_stmt_error($st));
    mysqli_stmt_close($st);

    $detalle = 'Nota: ' . ($nota !== null ? $nota . ' · ' . tk_nota_label($nota) : 'sin nota') . '.';
    if ($comentario !== '') $detalle .= ' Comentario: ' . $comentario;
    if (!empty($row['comentario_admin'])) {
        $detalle .= ' | Comentario anterior (sustituido): ' . $row['comentario_admin'];
    }
    tk_historial($conn, $id, $veredicto, $detalle);

    if ($aplicaProrroga) {
        tk_historial($conn, $id, 'prorroga',
            'Nueva fecha de entrega: ' . $fecha2 . '. Motivo: ' . $motivo);
    }

    mysqli_commit($conn);
    echo json_encode([
        'success'   => true,
        'estado'    => $veredicto,
        'nota'      => $nota,
        'nota_label'=> tk_nota_label($nota),
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'No se pudo revisar: ' . $e->getMessage()]);
}
