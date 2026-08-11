<?php

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/evades_apreciaciones.php');
api_require_report();

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']);
    exit;
}

try {
    $apreciacion = evades_guardar_apreciacion($conn, $data, (int)($_SESSION['user_id'] ?? 0));
    echo json_encode(['success' => true, 'data' => $apreciacion], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
