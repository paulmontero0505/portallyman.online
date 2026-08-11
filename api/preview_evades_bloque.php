<?php

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/evades_bloques.php');
api_require_report();

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']);
    exit;
}

try {
    $puesto = evades_normalizar_puesto($data['puesto'] ?? '');
    $periodo = trim((string)($data['periodo'] ?? ''));
    if ($puesto === null) throw new RuntimeException('Selecciona un puesto EVADES válido.');
    if (!evades_periodo_fechas($periodo)) throw new RuntimeException('Selecciona un trimestre válido.');

    $coordinadorId = evades_resolver_coordinador_objetivo($_SESSION, (int)($data['coordinador_id'] ?? 0));
    $stmt = mysqli_prepare($conn, "SELECT id,nombre FROM usuarios WHERE id=? AND rol='Coordinador' AND estado='Activo' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $coordinadorId);
    mysqli_stmt_execute($stmt);
    $coordinador = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$coordinador) throw new RuntimeException('Coordinador no encontrado o inactivo.');

    $stmt = mysqli_prepare($conn, 'SELECT id FROM evades_bloques WHERE coordinador_id=? AND puesto=? AND periodo=? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'iss', $coordinadorId, $puesto, $periodo);
    mysqli_stmt_execute($stmt);
    $existente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $cobertura = evades_obtener_cobertura_nomina($conn, $coordinadorId, $puesto, $periodo);
    echo json_encode([
        'success' => true,
        'puesto' => $puesto,
        'periodo' => $periodo,
        'coordinador' => ['id' => (int)$coordinador['id'], 'nombre' => $coordinador['nombre']],
        'total_colaboradores' => $cobertura['resumen']['total_colaboradores'],
        'cobertura' => $cobertura['resumen'],
        'colaboradores' => $cobertura['colaboradores'],
        'bloque_existente_id' => $existente ? (int)$existente['id'] : null,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
