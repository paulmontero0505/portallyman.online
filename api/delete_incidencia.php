<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Eliminación de una incidencia (JSON)
   Borra el registro y sus adjuntos del disco.
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

// Recuperar rutas de adjuntos antes de borrar.
$stmt = mysqli_prepare($conn, "SELECT foto_path, declaracion_path FROM incidencias WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'La incidencia no existe.']); exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM incidencias WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo eliminar.']); exit;
}

// Limpiar adjuntos (sólo dentro de uploads/incidencias).
foreach ([$row['foto_path'], $row['declaracion_path']] as $rel) {
    if ($rel && strpos($rel, 'uploads/incidencias/') === 0 && strpos($rel, '..') === false) {
        $abs = __DIR__ . '/../' . $rel;
        if (is_file($abs)) @unlink($abs);
    }
}

echo json_encode(['success' => true]);
