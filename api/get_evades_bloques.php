<?php

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/evades_bloques.php');
api_require_report();

header('Content-Type: application/json');
try {
    $id = (int)($_GET['id'] ?? 0);
    $actorId = (int)($_SESSION['user_id'] ?? 0);
    $rol = (string)($_SESSION['user_rol'] ?? '');
    $data = $id > 0
        ? evades_obtener_bloque($conn, $id, $actorId, $rol)
        : evades_listar_bloques($conn, $actorId, $rol);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
