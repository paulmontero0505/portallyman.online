<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Editar evento de la bitácora
   Body JSON: { id, horaInicio, horaFin, motivo, observaciones }
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/acciones.php');
api_require_login();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) { echo json_encode(['success' => false, 'error' => 'Payload inválido']); exit; }

$id     = isset($data['id']) ? (int)$data['id'] : 0;
$ini    = trim($data['horaInicio'] ?? '');
$fin    = trim($data['horaFin'] ?? '');
$motivo = trim($data['motivo'] ?? '');
$obs    = trim($data['observaciones'] ?? '');

$reHora = '/^\d{1,2}:\d{2}$/';

if ($id <= 0)                           { echo json_encode(['success' => false, 'error' => 'Evento inválido.']); exit; }
if (!preg_match($reHora, $ini))         { echo json_encode(['success' => false, 'error' => 'Hora de inicio requerida (HH:MM).']); exit; }
if ($fin !== '' && !preg_match($reHora, $fin)) { echo json_encode(['success' => false, 'error' => 'Hora de fin inválida (HH:MM).']); exit; }

// Verificar turno no cerrado
$q = mysqli_prepare($conn,
    "SELECT tp.turno_id, t.estado AS turno_estado, c.codigo, c.nombre, e.tipo
       FROM turno_eventos e
       JOIN turno_personal tp ON tp.id = e.turno_personal_id
       JOIN turnos t          ON t.id = tp.turno_id
       JOIN colaboradores c   ON c.id = tp.colaborador_id
      WHERE e.id = ? LIMIT 1");
mysqli_stmt_bind_param($q, 'i', $id);
mysqli_stmt_execute($q);
$ctx = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
mysqli_stmt_close($q);

if (!$ctx) { echo json_encode(['success' => false, 'error' => 'Evento no encontrado.']); exit; }
if ($ctx['turno_estado'] === 'cerrado') {
    echo json_encode(['success' => false, 'error' => 'El turno está cerrado.']); exit;
}
api_require_modify_turno($conn, (int)$ctx['turno_id']);

$finVal    = $fin === '' ? null : $fin;
$motivoVal = $motivo === '' ? null : $motivo;
$obsVal    = $obs === '' ? null : $obs;

$stmt = mysqli_prepare($conn,
    "UPDATE turno_eventos SET hora_inicio=?, hora_fin=?, motivo=?, observaciones=? WHERE id=?");
mysqli_stmt_bind_param($stmt, 'ssssi', $ini, $finVal, $motivoVal, $obsVal, $id);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);

if (!$ok) { echo json_encode(['success' => false, 'error' => $err]); exit; }

$labelTipo = ['traslado' => 'Traslado', 'refrigerio' => 'Refrigerio', 'permiso' => 'Permiso'];
registrar_accion($conn, (int)$ctx['turno_id'], 'evento_editado',
    'Editó ' . ($labelTipo[$ctx['tipo']] ?? $ctx['tipo']) . ' → ' . $ini . ($finVal ? '–' . $finVal : ''),
    $ctx['codigo'], $ctx['nombre']);

echo json_encode(['success' => true]);
