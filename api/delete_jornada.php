<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Eliminar jornada (admin)
   Body JSON: { id }
   No permite borrar si ya tiene turnos registrados (sugiere desactivar).
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) { echo json_encode(['success' => false, 'error' => 'Jornada inválida.']); exit; }

// ¿Tiene turnos asociados?
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM turnos WHERE jornada_id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$n   = (int)(mysqli_fetch_assoc($res)['n'] ?? 0);
mysqli_stmt_close($stmt);

if ($n > 0) {
    echo json_encode(['success' => false, 'error' => 'No se puede borrar: la jornada ya tiene turnos registrados. Desactívala en su lugar.']);
    exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM jornadas WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
$aff = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if (!$ok)       { echo json_encode(['success' => false, 'error' => $err]); exit; }
if ($aff === 0) { echo json_encode(['success' => false, 'error' => 'No se encontró la jornada.']); exit; }

echo json_encode(['success' => true]);
