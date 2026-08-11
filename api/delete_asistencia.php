<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Eliminación de una Asistencia Pre-Operativa (JSON)
   Los participantes y evidencias caen por ON DELETE CASCADE. Las copias
   locales de respaldo (si Drive falló) se borran del disco.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_report();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) { echo json_encode(['success' => false, 'error' => 'ID inválido.']); exit; }

// Rutas locales de respaldo, para limpiarlas del disco tras el borrado.
$locales = [];
$rl = mysqli_query($conn, "SELECT ruta_local FROM asistencias_evidencias WHERE asistencia_id=" . (int)$id . " AND ruta_local IS NOT NULL");
while ($rl && ($row = mysqli_fetch_assoc($rl))) $locales[] = $row['ruta_local'];

$stmt = mysqli_prepare($conn, "DELETE FROM asistencias_preoperativas WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
$aff = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if (!$ok) { echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo eliminar.']); exit; }
if ($aff === 0) { echo json_encode(['success' => false, 'error' => 'La asistencia no existe.']); exit; }

foreach ($locales as $rel) {
    $abs = __DIR__ . '/../' . ltrim($rel, '/');
    if (is_file($abs)) @unlink($abs);
}

echo json_encode(['success' => true]);
