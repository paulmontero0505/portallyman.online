<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Eliminación de una evaluación EVADES (JSON)
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/evades_catalogo.php');
require_once('../includes/evades_bloques.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;
$deleteBlock = !empty($data['bloque']);
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido.']); exit;
}

try {
    $actorId = (int)($_SESSION['user_id'] ?? 0);
    $result = $deleteBlock
        ? evades_eliminar_bloque($conn, $id, $actorId)
        : evades_eliminar_evaluacion_bloque($conn, $id, $actorId);
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
