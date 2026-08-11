<?php
/* Body JSON: { codigo, meta, activo } — solo Administrador. */

require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$codigo = trim($body['codigo'] ?? '');
$meta = isset($body['meta']) ? (float)$body['meta'] : null;
$activo = isset($body['activo']) ? (int)(bool)$body['activo'] : null;

if ($codigo === '' || $meta === null || $activo === null) {
    echo json_encode(['success' => false, 'error' => 'Faltan campos.']); exit;
}

$stmt = mysqli_prepare($conn, "UPDATE indicadores_catalogo SET meta=?, activo=? WHERE codigo=?");
mysqli_stmt_bind_param($stmt, 'dis', $meta, $activo, $codigo);
$ok = mysqli_stmt_execute($stmt);
$afectadas = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if (!$ok) { echo json_encode(['success' => false, 'error' => mysqli_error($conn)]); exit; }
echo json_encode(['success' => true, 'actualizado' => $afectadas > 0]);
