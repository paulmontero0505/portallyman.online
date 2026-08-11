<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/sheets.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido.']);
    exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM colaboradores WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
$affected = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    echo json_encode(['success' => false, 'error' => $err]);
    exit;
}
if ($affected === 0) {
    echo json_encode(['success' => false, 'error' => 'No se encontró el colaborador.']);
    exit;
}
sheets_sync_colaboradores($conn);
echo json_encode(['success' => true]);
