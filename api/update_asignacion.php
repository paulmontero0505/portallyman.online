<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Registrar cambio de asignación (puesto / estado)
   Body JSON: { tpId, funcion, ubicacion, naveId?, horario, estado, radio }
   Calcula el diff anterior → nuevo y lo deja registrado en la auditoría.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/acciones.php');
require_once('../includes/operaciones_naves.php');   // opn_validar_nave()
api_require_login();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) { echo json_encode(['success' => false, 'error' => 'Payload inválido']); exit; }

$tpId      = isset($data['tpId']) ? (int)$data['tpId'] : 0;
$funcion   = trim($data['funcion']   ?? '');
$ubicacion = trim($data['ubicacion'] ?? '');
$horario   = trim($data['horario']   ?? '');
$estado    = trim($data['estado']    ?? '');
$radio     = !empty($data['radio']) ? 1 : 0;

$estadosOk = ['activo', 'traslado', 'permiso', 'refrigerio', 'incidencia'];
if ($tpId <= 0)                           { echo json_encode(['success' => false, 'error' => 'Registro inválido.']); exit; }
if ($funcion === '')                      { echo json_encode(['success' => false, 'error' => 'Función requerida.']); exit; }
if (!in_array($estado, $estadosOk, true)) { echo json_encode(['success' => false, 'error' => 'Estado inválido.']); exit; }
$ubicacionVal = $ubicacion === '' ? null : $ubicacion;
$horarioVal   = $horario   === '' ? null : $horario;

// Nave asignada (opcional). Sin clave foránea posible —`naves` está en otra
// base—, esta validación es lo único que evita una referencia rota.
$naveId  = isset($data['naveId']) && (int)$data['naveId'] > 0 ? (int)$data['naveId'] : null;
$chkNave = opn_validar_nave($naveId);
if (!$chkNave['ok']) { echo json_encode(['success' => false, 'error' => $chkNave['error']]); exit; }

$ctx = contexto_tp($conn, $tpId);
if (!$ctx) { echo json_encode(['success' => false, 'error' => 'El colaborador no está en el turno.']); exit; }
if ($ctx['turno_estado'] === 'cerrado') {
    echo json_encode(['success' => false, 'error' => 'El turno está cerrado. Reábrelo para modificarlo.']); exit;
}
api_require_modify_turno($conn, (int)$ctx['turno_id']);

$stmt = mysqli_prepare(
    $conn,
    "UPDATE turno_personal SET funcion=?, ubicacion=?, nave_id=?, horario_colab=?, estado=?, radio=? WHERE id=?"
);
mysqli_stmt_bind_param($stmt, 'ssissii', $funcion, $ubicacionVal, $naveId, $horarioVal, $estado, $radio, $tpId);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);
if (!$ok) { echo json_encode(['success' => false, 'error' => $err]); exit; }

// ── Auditoría: describir solo lo que cambió ──
$labelEstado = ['activo' => 'Activo', 'traslado' => 'Traslado', 'permiso' => 'Permiso', 'refrigerio' => 'Refrigerio', 'incidencia' => 'Incidencia'];
$cambios = [];
$esCambioPuesto = false;
if ($ctx['funcion'] !== $funcion) {
    $cambios[] = 'función: ' . $ctx['funcion'] . ' → ' . $funcion; $esCambioPuesto = true;
}
if (($ctx['ubicacion'] ?? '') !== ($ubicacionVal ?? '')) {
    $cambios[] = 'ubicación: ' . ($ctx['ubicacion'] ?: '—') . ' → ' . ($ubicacionVal ?: '—'); $esCambioPuesto = true;
}
if ((int)($ctx['nave_id'] ?? 0) !== (int)($naveId ?? 0)) {
    // Se registra el NOMBRE, no el id: la auditoría la lee una persona, y el
    // nombre sigue siendo legible aunque la nave se borre más adelante.
    $antesNave = opn_validar_nave($ctx['nave_id'] ?? null);
    $cambios[] = 'nave: ' . ($antesNave['nombre'] ?: '—') . ' → ' . ($chkNave['nombre'] ?: '—');
    $esCambioPuesto = true;
}
if (($ctx['horario_colab'] ?? '') !== ($horarioVal ?? '')) {
    $cambios[] = 'horario: ' . ($ctx['horario_colab'] ?: '—') . ' → ' . ($horarioVal ?: '—'); $esCambioPuesto = true;
}
if ($ctx['estado'] !== $estado) {
    $cambios[] = 'estado: ' . ($labelEstado[$ctx['estado']] ?? $ctx['estado']) . ' → ' . ($labelEstado[$estado] ?? $estado);
}
if ((int)($ctx['radio'] ?? 0) !== $radio) {
    $cambios[] = 'radio: ' . ((int)($ctx['radio'] ?? 0) ? 'Sí' : 'No') . ' → ' . ($radio ? 'Sí' : 'No');
    $esCambioPuesto = true;
}

if ($cambios) {
    $detalle = implode(' · ', $cambios);
    $tipo = $esCambioPuesto ? 'cambio' : 'estado';
    registrar_accion($conn, (int)$ctx['turno_id'], $tipo, $detalle, $ctx['codigo'], $ctx['nombre']);
}

echo json_encode(['success' => true]);
