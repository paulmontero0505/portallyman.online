<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/sheets.php');
api_require_admin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$coordinadorId = is_array($data) ? (int)($data['coordinador_id'] ?? 0) : 0;
$ids = is_array($data['colaborador_ids'] ?? null) ? array_values(array_unique(array_filter(array_map('intval', $data['colaborador_ids']), fn($id) => $id > 0))) : [];
if ($coordinadorId <= 0 || !$ids || count($ids) > 1000) { echo json_encode(['success' => false, 'error' => 'Selecciona un coordinador y entre 1 y 1000 colaboradores.']); exit; }

$coord = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE id=? AND rol='Coordinador' AND estado='Activo' AND (oculto IS NULL OR oculto=0)");
mysqli_stmt_bind_param($coord, 'i', $coordinadorId);
mysqli_stmt_execute($coord);
$coordinadorValido = mysqli_fetch_assoc(mysqli_stmt_get_result($coord));
mysqli_stmt_close($coord);
if (!$coordinadorValido) { echo json_encode(['success' => false, 'error' => 'El coordinador seleccionado no está disponible.']); exit; }

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$types = 'i' . str_repeat('i', count($ids));
$stmt = mysqli_prepare($conn, "UPDATE colaboradores SET coordinador_id=? WHERE id IN ($placeholders)");
mysqli_stmt_bind_param($stmt, $types, $coordinadorId, ...$ids);
$ok = mysqli_stmt_execute($stmt); $updated = mysqli_stmt_affected_rows($stmt); $error = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);
if (!$ok) { echo json_encode(['success' => false, 'error' => $error ?: 'No se pudo guardar la asignación.']); exit; }
sheets_sync_colaboradores($conn);
echo json_encode(['success' => true, 'updated' => $updated]);
