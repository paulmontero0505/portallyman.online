<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Alta / edición de una Capacitación (JSON)
   ───────────────────────────────────────────────────────────────────────
   Payload JSON:
   {
     id?:          int (0/omitido = alta),
     titulo, fecha, hora,
     duracion_min?, lugar?, expositor?, observaciones?,
     temas?: [ { titulo, descripcion? }, ... ]     // reemplazo completo
   }

   · El coordinador sale SIEMPRE de la sesión; el payload no lo decide.
   · El estado NO se toca aquí: nace 'programada' y solo lo mueven
     enviar_capacitacion.php y validar_capacitacion.php.
   · En alta, `temas` suele venir vacío: el modal de creación solo pide
     título, fecha y hora. El contenido se carga entrando al registro.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/capacitaciones_catalogo.php');
api_require_report();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']); exit;
}

$id            = isset($data['id']) ? (int)$data['id'] : 0;
$titulo        = trim($data['titulo'] ?? '');
$fecha         = trim($data['fecha'] ?? '');
$hora          = trim($data['hora'] ?? '');
$duracion      = isset($data['duracion_min']) && $data['duracion_min'] !== '' ? (int)$data['duracion_min'] : null;
$lugar         = trim($data['lugar'] ?? '');
$expositor     = trim($data['expositor'] ?? '');
$observaciones = trim($data['observaciones'] ?? '');
$temas         = $data['temas'] ?? null;   // null = no tocar; array = reemplazar

// ── Validaciones ────────────────────────────────────────────────────────
if ($titulo === '')                                { echo json_encode(['success'=>false,'error'=>'Indica el título de la capacitación.']); exit; }
if (mb_strlen($titulo) > 180)                      { echo json_encode(['success'=>false,'error'=>'El título supera los 180 caracteres.']); exit; }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha))  { echo json_encode(['success'=>false,'error'=>'Fecha inválida.']); exit; }
if (!preg_match('/^\d{2}:\d{2}$/', $hora))         { echo json_encode(['success'=>false,'error'=>'Hora inválida.']); exit; }
if ($duracion !== null && ($duracion < 1 || $duracion > 1440)) {
    echo json_encode(['success'=>false,'error'=>'La duración debe estar entre 1 y 1440 minutos.']); exit;
}

$horaSql      = $hora . ':00';
$lugarSql     = $lugar     !== '' ? $lugar     : null;
$expositorSql = $expositor !== '' ? $expositor : null;
$obsSql       = $observaciones !== '' ? $observaciones : null;

// ── Autorización sobre el registro existente ────────────────────────────
if ($id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT id, estado, coordinador_id FROM capacitaciones WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $permiso = cap_puede_editar($row);
    if (!$permiso['ok']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $permiso['error']]); exit;
    }
}

$coordinador   = $_SESSION['user_name'] ?? 'Coordinador';
$coordinadorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// ── Transacción: cabecera + temas ───────────────────────────────────────
mysqli_begin_transaction($conn);
try {
    if ($id > 0) {
        $stmt = mysqli_prepare($conn,
            "UPDATE capacitaciones SET
                titulo=?, fecha=?, hora=?, duracion_min=?, lugar=?, expositor=?, observaciones=?
             WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sssisssi',
            $titulo, $fecha, $horaSql, $duracion, $lugarSql, $expositorSql, $obsSql, $id);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_errno($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        $capId = $id;
    } else {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO capacitaciones
                (titulo, fecha, hora, duracion_min, lugar, expositor, observaciones,
                 estado, coordinador, coordinador_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'programada', ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssissssi',
            $titulo, $fecha, $horaSql, $duracion, $lugarSql, $expositorSql, $obsSql,
            $coordinador, $coordinadorId);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_errno($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        $capId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
    }

    // Temas: reemplazo completo, solo si el payload los trae.
    // `null` significa «no tocar» (el modal de creación no los envía);
    // un array vacío sí borra, que es lo que pasa al quitar el último tema.
    if (is_array($temas)) {
        $del = mysqli_prepare($conn, "DELETE FROM capacitaciones_temas WHERE capacitacion_id=?");
        mysqli_stmt_bind_param($del, 'i', $capId);
        mysqli_stmt_execute($del);
        if (mysqli_stmt_errno($del)) throw new Exception(mysqli_stmt_error($del));
        mysqli_stmt_close($del);

        $stmtT = mysqli_prepare($conn,
            "INSERT INTO capacitaciones_temas (capacitacion_id, orden, titulo, descripcion)
             VALUES (?, ?, ?, ?)");
        $orden = 0;
        foreach ($temas as $t) {
            $tt = trim(is_array($t) ? ($t['titulo'] ?? '') : (string)$t);
            if ($tt === '') continue;                       // una fila vacía no es un tema
            if (mb_strlen($tt) > 200) $tt = mb_substr($tt, 0, 200);
            $td = is_array($t) ? trim($t['descripcion'] ?? '') : '';
            $tdSql = $td !== '' ? $td : null;
            $orden++;
            mysqli_stmt_bind_param($stmtT, 'iiss', $capId, $orden, $tt, $tdSql);
            mysqli_stmt_execute($stmtT);
            if (mysqli_stmt_errno($stmtT)) throw new Exception(mysqli_stmt_error($stmtT));
        }
        mysqli_stmt_close($stmtT);
    }

    mysqli_commit($conn);
} catch (Exception $ex) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'No se pudo guardar: ' . $ex->getMessage()]); exit;
}

echo json_encode(['success' => true, 'id' => $capId]);
