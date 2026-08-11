<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Eliminación de un reconocimiento (JSON)
   Borra el registro y su foto adjunta del disco.
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

// Recuperar la ruta del adjunto antes de borrar.
$stmt = mysqli_prepare($conn, "SELECT foto_path FROM reconocimientos WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'El reconocimiento no existe.']); exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM reconocimientos WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo eliminar.']); exit;
}

// Limpiar la foto (sólo dentro de uploads/reconocimientos).
$rel = $row['foto_path'];
if ($rel && strpos($rel, 'uploads/reconocimientos/') === 0 && strpos($rel, '..') === false) {
    $abs = __DIR__ . '/../' . $rel;
    if (is_file($abs)) @unlink($abs);
}

echo json_encode(['success' => true]);
