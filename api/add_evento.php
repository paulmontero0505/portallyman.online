<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Añadir evento a la bitácora
   Body JSON: { tpId, tipo, horaInicio, horaFin, observaciones }
   Devuelve el id del evento creado.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/acciones.php');
api_require_login();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) { echo json_encode(['success' => false, 'error' => 'Payload inválido']); exit; }

$tpId   = isset($data['tpId']) ? (int)$data['tpId'] : 0;
$tipo   = trim($data['tipo'] ?? '');
$ini    = trim($data['horaInicio'] ?? '');
$fin    = trim($data['horaFin'] ?? '');
$motivo = trim($data['motivo'] ?? '');
$obs    = trim($data['observaciones'] ?? '');

$tiposOk = ['traslado', 'refrigerio', 'permiso'];
$reHora  = '/^\d{1,2}:\d{2}$/';

if ($tpId <= 0)                          { echo json_encode(['success' => false, 'error' => 'Registro inválido.']); exit; }
if (!in_array($tipo, $tiposOk, true))    { echo json_encode(['success' => false, 'error' => 'Tipo inválido.']); exit; }
if (!preg_match($reHora, $ini))          { echo json_encode(['success' => false, 'error' => 'Hora de inicio requerida (HH:MM).']); exit; }
if ($fin !== '' && !preg_match($reHora, $fin)) { echo json_encode(['success' => false, 'error' => 'Hora de fin inválida (HH:MM).']); exit; }

$finVal = $fin === '' ? null : $fin;
$motivoVal = $motivo === '' ? null : $motivo;
$obsVal = $obs === '' ? null : $obs;

// Contexto + verificación de turno abierto.
$ctx = contexto_tp($conn, $tpId);
if (!$ctx) { echo json_encode(['success' => false, 'error' => 'El colaborador no está en el turno.']); exit; }
if ($ctx['turno_estado'] === 'cerrado') {
    echo json_encode(['success' => false, 'error' => 'El turno está cerrado. Reábrelo para modificarlo.']); exit;
}
api_require_modify_turno($conn, (int)$ctx['turno_id']);

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO turno_eventos (turno_personal_id, tipo, motivo, hora_inicio, hora_fin, observaciones)
          VALUES (?, ?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param($stmt, 'isssss', $tpId, $tipo, $motivoVal, $ini, $finVal, $obsVal);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
$id  = (int)mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

if (!$ok) { echo json_encode(['success' => false, 'error' => $err]); exit; }

$labelTipo = ['traslado' => 'Traslado', 'refrigerio' => 'Refrigerio', 'permiso' => 'Permiso'];

// ── Espejo a Google Sheets · hoja 'Bitacora' (fila rica del evento) ──
if (sheets_enabled()) {
    $fecha = ''; $jornada = '';
    if ($q = mysqli_prepare($conn, "SELECT t.fecha, j.nombre FROM turnos t JOIN jornadas j ON j.id=t.jornada_id WHERE t.id=? LIMIT 1")) {
        $tid = (int)$ctx['turno_id'];
        mysqli_stmt_bind_param($q, 'i', $tid);
        mysqli_stmt_execute($q);
        mysqli_stmt_bind_result($q, $f, $jn);
        if (mysqli_stmt_fetch($q)) { $fecha = $f; $jornada = $jn; }
        mysqli_stmt_close($q);
    }
    $row = [
        date('Y-m-d H:i:s'), $fecha, $jornada,
        $ctx['codigo'] ?? '', $ctx['nombre'] ?? '', $ctx['funcion'] ?? '', $ctx['ubicacion'] ?? '',
        $labelTipo[$tipo] ?? $tipo, $ini, $finVal ?? '', _sheets_dur($ini, $finVal),
        $motivoVal ?? '', $obsVal ?? '', $_SESSION['user_name'] ?? '',
    ];
    sheets_push('Bitacora', [$row], 'append', ['header' => [
        'Registrado', 'Fecha turno', 'Jornada', 'Código', 'Colaborador', 'Función', 'Ubicación',
        'Tipo', 'Inicio', 'Fin', 'Duración (min)', 'Motivo', 'Observaciones', 'Usuario',
    ]]);
}
$det = ($labelTipo[$tipo] ?? $tipo) . ' ' . $ini . ($finVal ? '–' . $finVal : '');
$motivoTxt = $motivoVal ?: '';
if ($motivoVal === 'Otros' && $obsVal) $motivoTxt = $obsVal;
if ($motivoTxt) $det .= ' · ' . $motivoTxt;
registrar_accion($conn, (int)$ctx['turno_id'], 'evento', $det, $ctx['codigo'], $ctx['nombre']);

echo json_encode(['success' => true, 'id' => $id]);
