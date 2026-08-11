<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Guardar relevo generado
   Body JSON: { fecha, turno, observaciones, datos }
   Registra quién generó el relevo y cuándo (control + base para Sheets).
   ═══════════════════════════════════════════════════════════════════════ */
require_once(__DIR__ . '/../includes/auth.php');
require_once(__DIR__ . '/../includes/db.php');
require_once(__DIR__ . '/../includes/turno.php');

header('Content-Type: application/json; charset=utf-8');
api_require_operaciones();

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) { echo json_encode(['success' => false, 'error' => 'Payload inválido']); exit; }

$fecha = trim($data['fecha'] ?? '');
$turno = strtoupper(trim($data['turno'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $turno === '') {
    echo json_encode(['success' => false, 'error' => 'fecha (YYYY-MM-DD) y turno son obligatorios.']); exit;
}

$observaciones = trim($data['observaciones'] ?? '');
$observaciones = $observaciones === '' ? null : $observaciones;
$datosJson = isset($data['datos']) ? json_encode($data['datos'], JSON_UNESCAPED_UNICODE) : null;

// turno_id si existe el turno en BD (no es obligatorio para guardar el relevo).
$turnoId = null;
$j = jornada_por_codigo($conn, $turno);
if ($j && ($stmt = mysqli_prepare($conn, "SELECT id FROM turnos WHERE fecha=? AND jornada_id=? LIMIT 1"))) {
    $jid = (int)$j['id'];
    mysqli_stmt_bind_param($stmt, 'si', $fecha, $jid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $tid);
    if (mysqli_stmt_fetch($stmt)) $turnoId = (int)$tid;
    mysqli_stmt_close($stmt);
}

$generadoPor   = $_SESSION['user_name'] ?? 'Desconocido';
$generadoPorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO relevo_generado
        (fecha, turno, turno_id, generado_por, generado_por_id, observaciones, datos_json)
     VALUES (?, ?, ?, ?, ?, ?, ?)"
);
if (!$stmt) { echo json_encode(['success' => false, 'error' => 'No se pudo preparar el guardado (¿migración 011 aplicada?).']); exit; }
mysqli_stmt_bind_param($stmt, 'ssisiss', $fecha, $turno, $turnoId, $generadoPor, $generadoPorId, $observaciones, $datosJson);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);
if (!$ok) { echo json_encode(['success' => false, 'error' => $err]); exit; }

echo json_encode([
    'success'      => true,
    'generado_por' => $generadoPor,
    'generado_en'  => date('Y-m-d H:i:s'),
]);
