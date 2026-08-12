<?php

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/evades_bloques.php');
api_require_admin();

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
try {
    if (!is_array($data)) throw new RuntimeException('Payload inválido.');
    $bloque = evades_revisar_y_cerrar_bloque($conn, (int)($data['id'] ?? 0), (int)($_SESSION['user_id'] ?? 0));
    echo json_encode(['success' => true, 'data' => $bloque], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
