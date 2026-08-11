<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Elimina una capacitación (JSON)
   ───────────────────────────────────────────────────────────────────────
   Body JSON: { id }

   El coordinador dueño solo puede borrarla mientras esté «programada»;
   una vez enviada, el registro deja de ser suyo. El Administrador puede
   borrar en cualquier estado (limpieza de registros erróneos).

   Temas, asistentes y adjuntos caen por ON DELETE CASCADE (sql/028). Las
   copias locales de respaldo se borran del disco antes, porque la cascada
   no las alcanza.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/capacitaciones_catalogo.php');
api_require_report();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido.']); exit; }

$stmt = mysqli_prepare($conn, "SELECT id, titulo, estado, coordinador_id FROM capacitaciones WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$cap = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$permiso = cap_puede_eliminar($cap);
if (!$permiso['ok']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $permiso['error']]); exit;
}

// ── Copias locales de respaldo (la cascada de BD no toca el disco) ──────
$stmt = mysqli_prepare($conn,
    "SELECT ruta_local FROM capacitaciones_adjuntos WHERE capacitacion_id=? AND ruta_local IS NOT NULL");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$r = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($r)) {
    $rel = $row['ruta_local'];
    if (strpos($rel, 'uploads/') !== 0 || strpos($rel, '..') !== false) continue;
    $abs = __DIR__ . '/../' . $rel;
    if (is_file($abs)) @unlink($abs);
}
mysqli_stmt_close($stmt);

$del = mysqli_prepare($conn, "DELETE FROM capacitaciones WHERE id=?");
mysqli_stmt_bind_param($del, 'i', $id);
$ok  = mysqli_stmt_execute($del);
$err = mysqli_stmt_error($del);
mysqli_stmt_close($del);

if (!$ok) { echo json_encode(['success'=>false,'error'=>$err ?: 'No se pudo eliminar.']); exit; }

echo json_encode(['success' => true, 'id' => $id]);
