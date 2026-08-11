<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de tarea inválido.']); exit;
}

$st = mysqli_prepare($conn, "SELECT id, titulo, asignado_nombre FROM tareas WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'La tarea no existe.']); exit;
}

$st = mysqli_prepare($conn, "DELETE FROM tareas WHERE id=?");
mysqli_stmt_bind_param($st, 'i', $id);
$ok  = mysqli_stmt_execute($st);
$err = mysqli_stmt_error($st);
mysqli_stmt_close($st);

if (!$ok) {
    echo json_encode(['success' => false, 'error' => 'No se pudo eliminar: ' . $err]); exit;
}
echo json_encode(['success' => true]);
