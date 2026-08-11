<?php
/* Body JSON: { gestion_codigo, periodo, team } — solo Administrador/Supervisor. */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/indicadores_catalogo.php');
api_require_indicadores();

header('Content-Type: application/json');

$rol = $_SESSION['user_rol'] ?? '';
if (!in_array($rol, ['Administrador', 'Supervisor'], true)) {
    echo json_encode(['success' => false, 'error' => 'Solo Administrador o Supervisor pueden editar el Cronograma.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$gestion = trim($body['gestion_codigo'] ?? '');
$periodo = trim($body['periodo'] ?? '');
$team = trim($body['team'] ?? '');

if (!in_array($gestion, ['G1', 'G2', 'G3', 'G4'], true)) {
    echo json_encode(['success' => false, 'error' => 'Gestión inválida.']); exit;
}
if (ind_periodo_fechas($periodo) === null) {
    echo json_encode(['success' => false, 'error' => 'Periodo inválido.']); exit;
}
if (!in_array($team, ind_teams(), true)) {
    echo json_encode(['success' => false, 'error' => 'Team inválido.']); exit;
}

$stmt = mysqli_prepare($conn,
    "INSERT INTO indicadores_cronograma (gestion_codigo, periodo, team) VALUES (?,?,?)
     ON DUPLICATE KEY UPDATE team=VALUES(team)"
);
mysqli_stmt_bind_param($stmt, 'sss', $gestion, $periodo, $team);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

echo json_encode($ok ? ['success' => true] : ['success' => false, 'error' => mysqli_error($conn)]);
