<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Elimina un adjunto de una capacitación (JSON)
   ───────────────────────────────────────────────────────────────────────
   Body JSON: { id }   ← id del ADJUNTO, no de la capacitación.

   Borra la fila y, si existía, la copia local de respaldo. El archivo en
   Drive NO se borra: el Apps Script solo expone subida (ver
   apps-script/SugerenciasDrive.gs), y dejar el archivo huérfano en Drive
   es preferible a fingir que se borró.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/capacitaciones_catalogo.php');
api_require_report();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido.']); exit; }

// ── Adjunto + su capacitación, en una sola consulta ─────────────────────
$stmt = mysqli_prepare($conn,
    "SELECT ad.id, ad.ruta_local, ad.drive_url,
            c.id AS cap_id, c.estado, c.coordinador_id
       FROM capacitaciones_adjuntos ad
       JOIN capacitaciones c ON c.id = ad.capacitacion_id
      WHERE ad.id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$row) { echo json_encode(['success'=>false,'error'=>'El adjunto no existe.']); exit; }

$permiso = cap_puede_editar($row);
if (!$permiso['ok']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $permiso['error']]); exit;
}

// ── Copia local de respaldo ─────────────────────────────────────────────
// La ruta guardada es relativa a la raíz del proyecto y la genera
// sg_guardar_local(); se comprueba el prefijo antes de tocar el disco.
if (!empty($row['ruta_local']) && strpos($row['ruta_local'], 'uploads/') === 0
    && strpos($row['ruta_local'], '..') === false) {
    $abs = __DIR__ . '/../' . $row['ruta_local'];
    if (is_file($abs)) @unlink($abs);
}

$del = mysqli_prepare($conn, "DELETE FROM capacitaciones_adjuntos WHERE id=?");
mysqli_stmt_bind_param($del, 'i', $id);
$ok  = mysqli_stmt_execute($del);
$err = mysqli_stmt_error($del);
mysqli_stmt_close($del);

if (!$ok) { echo json_encode(['success'=>false,'error'=>$err ?: 'No se pudo eliminar el adjunto.']); exit; }

echo json_encode([
    'success' => true,
    'id'      => $id,
    'aviso'   => !empty($row['drive_url'])
        ? 'El archivo se quitó de la capacitación. La copia en Drive se conserva.'
        : null,
]);
