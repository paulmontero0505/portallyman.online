<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Calificar una Propuesta de mejora (JSON)
   Solo Administrador. Solo aplica a registros con canal='propuesta'.
   ───────────────────────────────────────────────────────────────────────
   Reemplaza a save_sugerencia_puntaje.php: el endpoint escribe ahora dos
   campos de naturaleza distinta y «puntaje» dejó de nombrar a ninguno.

     · viabilidad (1-10)  → ¿se puede hacer?
     · impacto            → ¿cuánto aporta si se hace?

   NO hay validación cruzada entre ambos, y es deliberado: viabilidad=2 con
   impacto='alto' («gran idea, imposible hoy») es exactamente el caso que
   el modelo anterior no sabía representar. Cualquier combinación se guarda.
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

$id         = isset($data['id']) ? (int)$data['id'] : 0;
$viabilidad = isset($data['viabilidad']) && $data['viabilidad'] !== '' && $data['viabilidad'] !== null
              ? (int)$data['viabilidad'] : null;
$impacto    = isset($data['impacto']) && $data['impacto'] !== '' && $data['impacto'] !== null
              ? (string)$data['impacto'] : null;
$comentario = trim($data['comentario'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido.']); exit;
}
if ($viabilidad !== null && ($viabilidad < sg_puntaje_min() || $viabilidad > sg_puntaje_max())) {
    echo json_encode(['success' => false, 'error' => 'Viabilidad fuera de rango (' . sg_puntaje_min() . '-' . sg_puntaje_max() . ').']); exit;
}
if ($impacto !== null && !array_key_exists($impacto, sg_impactos())) {
    echo json_encode(['success' => false, 'error' => 'Impacto inválido.']); exit;
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
    echo json_encode(['success' => false, 'error' => 'Solo las propuestas de mejora admiten calificación.']); exit;
}

$calificadoPor = $_SESSION['user_name'] ?? 'Administrador';

$stmt = mysqli_prepare(
    $conn,
    "UPDATE sugerencias_tallyman
        SET viabilidad=?, impacto=?, puntaje_comentario=?, puntaje_por=?, puntaje_at=NOW()
      WHERE id=?"
);
// 'i' con $viabilidad NULL manda NULL a la BD; bind_param no lo convierte a 0.
mysqli_stmt_bind_param($stmt, 'isssi', $viabilidad, $impacto, $comentario, $calificadoPor, $id);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo guardar la calificación.']); exit;
}

echo json_encode(['success' => true]);
