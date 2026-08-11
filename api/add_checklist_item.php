<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Añadir ítem al checklist de tallyman
   Body JSON: { tpId, item, comentario }
   Devuelve el id del ítem creado.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/acciones.php');
api_require_login();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) { echo json_encode(['success' => false, 'error' => 'Payload inválido']); exit; }

$tpId       = isset($data['tpId']) ? (int)$data['tpId'] : 0;
$item       = trim($data['item'] ?? '');
$comentario = trim($data['comentario'] ?? '');

if ($tpId <= 0)   { echo json_encode(['success' => false, 'error' => 'Registro inválido.']); exit; }
if ($item === '') { echo json_encode(['success' => false, 'error' => 'El ítem es obligatorio.']); exit; }
if (mb_strlen($item) > 120) { echo json_encode(['success' => false, 'error' => 'El ítem es demasiado largo.']); exit; }

$comentarioVal = $comentario === '' ? null : $comentario;

// Contexto + verificación de turno abierto.
$ctx = contexto_tp($conn, $tpId);
if (!$ctx) { echo json_encode(['success' => false, 'error' => 'El colaborador no está en el turno.']); exit; }
if ($ctx['turno_estado'] === 'cerrado') {
    echo json_encode(['success' => false, 'error' => 'El turno está cerrado. Reábrelo para modificarlo.']); exit;
}
api_require_modify_turno($conn, (int)$ctx['turno_id']);

$stmt = mysqli_prepare($conn, "INSERT INTO turno_checklist_items (turno_personal_id, item, comentario) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt, 'iss', $tpId, $item, $comentarioVal);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
$id  = (int)mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

if (!$ok) { echo json_encode(['success' => false, 'error' => $err]); exit; }

$det = 'Checklist: ' . $item . ($comentarioVal ? ' · ' . $comentarioVal : '');
registrar_accion($conn, (int)$ctx['turno_id'], 'checklist', $det, $ctx['codigo'], $ctx['nombre']);

echo json_encode(['success' => true, 'id' => $id]);
