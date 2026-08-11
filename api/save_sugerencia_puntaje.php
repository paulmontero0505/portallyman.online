<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Asignar puntaje a una Propuesta de mejora (JSON)
   Solo Administrador. Solo aplica a registros con canal='propuesta'.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/sugerencias_catalogo.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']); exit;
}

$id        = isset($data['id']) ? (int)$data['id'] : 0;
$puntaje   = isset($data['puntaje']) && $data['puntaje'] !== '' ? (int)$data['puntaje'] : null;
$comentario= trim($data['comentario'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido.']); exit;
}
if ($puntaje !== null && ($puntaje < sg_puntaje_min() || $puntaje > sg_puntaje_max())) {
    echo json_encode(['success' => false, 'error' => 'Puntaje fuera de rango (' . sg_puntaje_min() . '-' . sg_puntaje_max() . ').']); exit;
}

$stmt = mysqli_prepare($conn, "SELECT canal FROM sugerencias_tallyman WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$row) {
    echo json_encode(['success' => false, 'error' => 'El registro no existe.']); exit;
}
if ($row['canal'] !== sg_canal_puntuable()) {
    echo json_encode(['success' => false, 'error' => 'Solo las propuestas de mejora admiten puntaje.']); exit;
}

$puntuadoPor = $_SESSION['user_name'] ?? 'Administrador';

$stmt = mysqli_prepare(
    $conn,
    "UPDATE sugerencias_tallyman SET puntaje=?, puntaje_comentario=?, puntaje_por=?, puntaje_at=NOW() WHERE id=?"
);
mysqli_stmt_bind_param($stmt, 'issi', $puntaje, $comentario, $puntuadoPor, $id);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo guardar el puntaje.']); exit;
}

echo json_encode(['success' => true]);
