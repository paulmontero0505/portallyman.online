<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_tareas();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$adjId = isset($data['id']) ? (int)$data['id'] : 0;
if ($adjId <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de adjunto inválido.']); exit;
}

$st = mysqli_prepare($conn,
    "SELECT a.id, a.tarea_id, a.nombre_archivo, a.subido_por_id, a.origen,
            t.estado, t.asignado_id, u.soporte_de_id AS asignado_soporte_de
       FROM tareas_adjuntos a
       JOIN tareas t     ON t.id = a.tarea_id
       LEFT JOIN usuarios u ON u.id = t.asignado_id
      WHERE a.id = ? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $adjId);
mysqli_stmt_execute($st);
$a = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if (!$a) {
    echo json_encode(['success' => false, 'error' => 'El adjunto no existe.']); exit;
}
if (!tk_puede_ver($a)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tienes permiso sobre esta tarea.']); exit;
}

$uid    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$esMio  = ($a['subido_por_id'] !== null && (int)$a['subido_por_id'] === $uid);
$puede  = is_admin() || ($esMio && tk_es_abierta($a['estado']));
if (!$puede) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => tk_es_abierta($a['estado'])
        ? 'Solo puedes eliminar los archivos que tú subiste.'
        : 'La tarea ya no admite cambios en sus archivos.']); exit;
}

$tareaId = (int)$a['tarea_id'];

mysqli_begin_transaction($conn);
try {
    $st = mysqli_prepare($conn, "DELETE FROM tareas_adjuntos WHERE id=?");
    mysqli_stmt_bind_param($st, 'i', $adjId);
    if (!mysqli_stmt_execute($st)) throw new Exception(mysqli_stmt_error($st));
    mysqli_stmt_close($st);

    tk_historial($conn, $tareaId, 'adjunto_borrado', 'Archivo retirado: ' . $a['nombre_archivo']);

    mysqli_commit($conn);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'No se pudo eliminar: ' . $e->getMessage()]);
}
