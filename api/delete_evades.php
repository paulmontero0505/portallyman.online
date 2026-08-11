<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Eliminación de una evaluación EVADES (JSON)
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/evades_catalogo.php');
api_require_report();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido.']); exit;
}

$rol = (string)($_SESSION['user_rol'] ?? '');
$uid = (int)($_SESSION['user_id'] ?? 0);
$visibilidad = in_array($rol, ['Administrador', 'Supervisor'], true)
    ? '1=1'
    : ($rol === 'Coordinador' && $uid > 0
        ? "((ev.bloque_id IS NOT NULL AND ev.coordinador_id=$uid) OR (ev.bloque_id IS NULL AND col.coordinador_id=$uid))"
        : '0=1');
$stmt = mysqli_prepare(
    $conn,
    "SELECT ev.id,ev.bloque_id FROM evades_evaluaciones ev
       LEFT JOIN colaboradores col ON col.id = ev.colaborador_id
      WHERE ev.id=? AND ($visibilidad) LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'La evaluación no existe o no está a tu cargo.']); exit;
}
if ($row['bloque_id'] !== null) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Las evaluaciones de un bloque no se eliminan individualmente.']); exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM evades_evaluaciones WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo eliminar.']); exit;
}

echo json_encode(['success' => true]);
