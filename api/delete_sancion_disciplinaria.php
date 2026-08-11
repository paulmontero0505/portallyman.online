<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');

api_require_admin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id = (int)($data['id'] ?? 0);

$stmt = mysqli_prepare($conn, 'SELECT incidencia_id, evidencia_path FROM sanciones_disciplinarias WHERE id=? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Sancion no encontrada.']);
    exit;
}

mysqli_begin_transaction($conn);
$ok = true;

if ($row['incidencia_id'] !== null) {
    $incidenciaId = (int)$row['incidencia_id'];
    $stmt = mysqli_prepare($conn, "UPDATE incidencias SET descuento_puntos=0, frecuencia_descuento=NULL, sancion_disciplinaria='sin_sancion', medida_aplicada_por=NULL, medida_aplicada_por_id=NULL, medida_aplicada_at=NULL WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $incidenciaId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

if ($ok) {
    $stmt = mysqli_prepare($conn, 'DELETE FROM sanciones_disciplinarias WHERE id=?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

if ($ok) {
    mysqli_commit($conn);
} else {
    mysqli_rollback($conn);
}

if ($ok && !empty($row['evidencia_path']) && strpos($row['evidencia_path'], 'uploads/sanciones/') === 0) {
    @unlink(__DIR__ . '/../' . $row['evidencia_path']);
}

echo json_encode(['success' => $ok, 'error' => $ok ? null : 'No se pudo eliminar.']);
