<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Eliminación de una evaluación de desempeño (JSON)
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_report();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido.']); exit;
}

$stmt = mysqli_prepare($conn, "SELECT id FROM evaluacion_desempeno WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'La evaluación no existe.']); exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM evaluacion_desempeno WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo eliminar.']); exit;
}

echo json_encode(['success' => true]);
