<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido.']);
    exit;
}

// No permitir auto-eliminarse
if ($id === (int)($_SESSION['user_id'] ?? 0)) {
    echo json_encode(['success' => false, 'error' => 'No puedes eliminar tu propia cuenta.']);
    exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM usuarios WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
$ok = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);

if ($ok) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $err]);
}
