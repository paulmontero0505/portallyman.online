<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Indicadores — guarda una captura manual N/D
   Body JSON: { indicador_codigo, periodo, team, numerador, denominador }
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/indicadores_catalogo.php');
require_once('../includes/indicadores_engine.php');
api_require_indicadores();

header('Content-Type: application/json');

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$codigo = trim($body['indicador_codigo'] ?? '');
$periodo = trim($body['periodo'] ?? '');
$team = trim($body['team'] ?? '');
$numerador = array_key_exists('numerador', $body) && $body['numerador'] !== '' ? (float)$body['numerador'] : null;
$denominador = array_key_exists('denominador', $body) && $body['denominador'] !== '' ? (float)$body['denominador'] : null;

if (ind_periodo_fechas($periodo) === null) {
    echo json_encode(['success' => false, 'error' => 'Periodo inválido.']); exit;
}
if (!in_array($team, ind_teams(), true)) {
    echo json_encode(['success' => false, 'error' => 'Team inválido.']); exit;
}

$stmt = mysqli_prepare($conn, "SELECT fuente_automatica FROM indicadores_catalogo WHERE codigo=? AND activo=1");
mysqli_stmt_bind_param($stmt, 's', $codigo);
mysqli_stmt_execute($stmt);
$ind = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$ind) { echo json_encode(['success' => false, 'error' => 'Indicador no existe.']); exit; }

// El único caso parcial hoy es G1.1: numerador automático, solo se puede escribir el denominador.
if ($ind['fuente_automatica'] !== null && $codigo !== 'G1.1') {
    echo json_encode(['success' => false, 'error' => 'Este indicador se calcula automáticamente, no admite captura manual.']);
    exit;
}
if ($codigo === 'G1.1') $numerador = null; // nunca se sobreescribe el numerador automático

// Coordinador: solo puede capturar el team que le corresponde.
$rol = $_SESSION['user_rol'] ?? '';
if ($rol === 'Coordinador') {
    $miTeam = ind_team_de_coordinador($conn, (int)($_SESSION['user_id'] ?? 0));
    if ($miTeam === null || $miTeam !== $team) {
        echo json_encode(['success' => false, 'error' => 'Solo puedes capturar el team que tienes a cargo.']);
        exit;
    }
}

$nombre = $_SESSION['user_name'] ?? '';
$uid = (int)($_SESSION['user_id'] ?? 0);

$stmt = mysqli_prepare($conn,
    "INSERT INTO indicadores_captura (indicador_codigo, periodo, team, numerador, denominador, capturado_por, capturado_por_id)
     VALUES (?,?,?,?,?,?,?)
     ON DUPLICATE KEY UPDATE numerador=VALUES(numerador), denominador=VALUES(denominador),
       capturado_por=VALUES(capturado_por), capturado_por_id=VALUES(capturado_por_id)"
);
mysqli_stmt_bind_param($stmt, 'sssddsi', $codigo, $periodo, $team, $numerador, $denominador, $nombre, $uid);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

echo json_encode($ok ? ['success' => true] : ['success' => false, 'error' => mysqli_error($conn)]);
