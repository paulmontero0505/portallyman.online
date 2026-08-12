<?php

require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
try {
    $id = (int)($data['id'] ?? 0);
    if ($id <= 0) throw new RuntimeException('Evaluación inválida.');
    $stmt = mysqli_prepare($conn, "UPDATE evades_evaluaciones ev
        LEFT JOIN evades_bloques b ON b.id=ev.bloque_id
        SET ev.revisado_at=NULL,ev.revisado_por=NULL
        WHERE ev.id=? AND (b.id IS NULL OR b.estado<>'cerrado')");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
    if (mysqli_stmt_affected_rows($stmt) !== 1) throw new RuntimeException('La revisión pertenece a un bloque cerrado y no puede reabrirse.');
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
