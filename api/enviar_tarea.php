<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_tareas();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']); exit;
}

$id         = isset($data['id']) ? (int)$data['id'] : 0;
$comentario = trim($data['comentario'] ?? '');
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de tarea inválido.']); exit;
}

$st = mysqli_prepare($conn,
    "SELECT t.*, u.soporte_de_id AS asignado_soporte_de
       FROM tareas t LEFT JOIN usuarios u ON u.id = t.asignado_id
      WHERE t.id = ? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

$permiso = tk_puede_entregar($row);
if (!$permiso['ok']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $permiso['error']]); exit;
}

$ronda = (int)$row['entregas_count'] + 1;
$st = mysqli_prepare($conn,
    "SELECT COUNT(*) AS n FROM tareas_adjuntos
      WHERE tarea_id=? AND origen='asignado' AND entrega_nro=?");
mysqli_stmt_bind_param($st, 'ii', $id, $ronda);
mysqli_stmt_execute($st);
$nAdj = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['n'] ?? 0);
mysqli_stmt_close($st);

if ($nAdj === 0 && $comentario === '') {
    echo json_encode(['success' => false,
        'error' => 'Adjunta al menos un archivo de evidencia o escribe un comentario de entrega.']); exit;
}
if (mb_strlen($comentario) > 4000) {
    echo json_encode(['success' => false, 'error' => 'El comentario es demasiado largo.']); exit;
}

$plazoSellado = tk_plazo_vigente($row);
$comentSql    = $comentario !== '' ? $comentario : null;
$enNombreDe   = !empty($permiso['en_nombre_de'])
             && (int)($row['asignado_id'] ?? 0) !== (int)($_SESSION['user_id'] ?? 0);

mysqli_begin_transaction($conn);
try {
    $st = mysqli_prepare($conn,
        "UPDATE tareas
            SET estado='entregada',
                entrega_comentario=?,
                enviado_at=NOW(),
                plazo_al_enviar=?,
                entregas_count=entregas_count+1
          WHERE id=?");
    mysqli_stmt_bind_param($st, 'ssi', $comentSql, $plazoSellado, $id);
    if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_stmt_error($st));
    mysqli_stmt_close($st);

    $tarde   = strtotime('now') > strtotime($plazoSellado);
    $detalle = 'Envío n.º ' . $ronda . '. Plazo vigente al enviar: ' . $plazoSellado . '.'
             . ($tarde ? ' ENTREGA FUERA DE PLAZO.' : '')
             . ' Archivos en esta ronda: ' . $nAdj . '.'
             . ($enNombreDe ? ' Enviada por el Administrador en nombre de ' . $row['asignado_nombre'] . '.' : '');
    tk_historial($conn, $id, 'enviada', $detalle);

    mysqli_commit($conn);
    echo json_encode([
        'success'         => true,
        'estado'          => 'entregada',
        'entregas_count'  => $ronda,
        'plazo_al_enviar' => $plazoSellado,
        'entregada_tarde' => $tarde,
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'No se pudo enviar: ' . $e->getMessage()]);
}
