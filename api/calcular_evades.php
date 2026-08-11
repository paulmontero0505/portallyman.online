<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Calcula las sugerencias EVADES para un colaborador y
   trimestre, sin persistir nada. Lo llama el modal antes de mostrar el
   formulario editable.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/evades_catalogo.php');
require_once('../includes/evades_engine.php');
api_require_report();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']);
    exit;
}

$colaboradorId = isset($data['colaborador_id']) ? (int)$data['colaborador_id'] : 0;
$periodo       = trim($data['periodo'] ?? '');

if ($colaboradorId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Selecciona el colaborador a evaluar.']); exit;
}
if (!evades_periodo_fechas($periodo)) {
    echo json_encode(['success' => false, 'error' => 'Período inválido.']); exit;
}

// El colaborador debe existir, ocupar uno de los dos puestos EVADES, y si
// quien pide es Coordinador, estar a su cargo.
$stmt = mysqli_prepare(
    $conn,
    "SELECT c.id, c.nombre, c.codigo, c.funcion_principal, c.dni, c.coordinador_id
       FROM colaboradores c WHERE c.id=? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $colaboradorId);
mysqli_stmt_execute($stmt);
$col = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$col) {
    echo json_encode(['success' => false, 'error' => 'Colaborador no encontrado.']); exit;
}
if (evades_normalizar_puesto($col['funcion_principal']) === null) {
    echo json_encode(['success' => false, 'error' => 'El puesto del colaborador no corresponde a EVADES.']); exit;
}
$rol = $_SESSION['user_rol'] ?? '';
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($rol === 'Coordinador' && (int)($col['coordinador_id'] ?? 0) !== $uid) {
    echo json_encode(['success' => false, 'error' => 'Ese colaborador no está a tu cargo.']); exit;
}

$sugerencias = evades_calcular_sugerencias($conn, $colaboradorId, $periodo);
if ($sugerencias === null) {
    // No debería ocurrir: $periodo ya pasó evades_periodo_fechas() arriba.
    // Defensivo por si esa validación y la del motor llegan a desalinearse.
    echo json_encode(['success' => false, 'error' => 'Período inválido.']); exit;
}

echo json_encode([
    'success' => true,
    'colaborador' => [
        'id' => (int)$col['id'],
        'nombre' => $col['nombre'],
        'codigo' => $col['codigo'],
        'cargo' => $col['funcion_principal'],
        'dni' => $col['dni'],
    ],
    'periodo' => $periodo,
    'competencias' => $sugerencias,
]);
