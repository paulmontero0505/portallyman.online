<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Quitar colaborador del turno
   Body JSON: { tpId }   (borra también su bitácora por cascade)
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/acciones.php');
api_require_login();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$tpId = isset($data['tpId']) ? (int)$data['tpId'] : 0;

if ($tpId <= 0) { echo json_encode(['success' => false, 'error' => 'Registro inválido.']); exit; }

$ctx = contexto_tp($conn, $tpId);
if ($ctx && $ctx['turno_estado'] === 'cerrado') {
    echo json_encode(['success' => false, 'error' => 'El turno está cerrado. Reábrelo para modificarlo.']); exit;
}
if ($ctx) api_require_delete_turno($conn, (int)$ctx['turno_id']);

$stmt = mysqli_prepare($conn, "DELETE FROM turno_personal WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $tpId);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
$aff = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if (!$ok)        { echo json_encode(['success' => false, 'error' => $err]); exit; }
if ($aff === 0)  { echo json_encode(['success' => false, 'error' => 'No se encontró en el turno.']); exit; }

if ($ctx) {
    registrar_accion($conn, (int)$ctx['turno_id'], 'baja', 'Quitado del turno', $ctx['codigo'], $ctx['nombre']);
}

echo json_encode(['success' => true]);
