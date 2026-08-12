<?php

require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_report();

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
try {
    $id = (int)($data['id'] ?? 0);
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $rol = (string)($_SESSION['user_rol'] ?? '');
    if ($id <= 0 || $uid <= 0) throw new RuntimeException('Evaluación o usuario inválido.');

    $where = in_array($rol, ['Administrador', 'Supervisor'], true)
        ? '1=1'
        : ($rol === 'Coordinador' ? 'ev.coordinador_id=' . $uid : '0=1');
    $stmt = mysqli_prepare($conn, "SELECT ev.id FROM evades_evaluaciones ev WHERE ev.id=? AND ($where) LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) throw new RuntimeException('No tienes acceso a esta evaluación.');

    $stmt = mysqli_prepare($conn, 'UPDATE evades_evaluaciones SET revisado_at=COALESCE(revisado_at,NOW()), revisado_por=COALESCE(revisado_por,?) WHERE id=?');
    mysqli_stmt_bind_param($stmt, 'ii', $uid, $id);
    if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
