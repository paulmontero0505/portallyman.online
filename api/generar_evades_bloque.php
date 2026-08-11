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
    $coordinadorId = evades_resolver_coordinador_objetivo($_SESSION, (int)($data['coordinador_id'] ?? 0));
    $bloque = evades_generar_bloque(
        $conn,
        $coordinadorId,
        (string)($data['puesto'] ?? ''),
        trim((string)($data['periodo'] ?? '')),
        (int)($_SESSION['user_id'] ?? 0)
    );
    echo json_encode(['success' => true, 'data' => $bloque], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $duplicado = strpos($e->getMessage(), 'Ya existe un bloque') !== false;
    http_response_code($duplicado ? 409 : 422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
