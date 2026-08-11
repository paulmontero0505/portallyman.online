<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Eliminar ítem del checklist de tallyman
   Body JSON: { id }
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/acciones.php');
api_require_login();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;

if ($id <= 0) { echo json_encode(['success' => false, 'error' => 'Ítem inválido.']); exit; }

$ctx = null;
$q = mysqli_prepare(
    $conn,
    "SELECT tp.turno_id, t.estado AS turno_estado, c.codigo, c.nombre, cl.item
       FROM turno_checklist_items cl
       JOIN turno_personal tp ON tp.id = cl.turno_personal_id
       JOIN turnos t          ON t.id = tp.turno_id
       JOIN colaboradores c   ON c.id = tp.colaborador_id
      WHERE cl.id = ? LIMIT 1"
);
mysqli_stmt_bind_param($q, 'i', $id);
mysqli_stmt_execute($q);
$res = mysqli_stmt_get_result($q);
$ctx = mysqli_fetch_assoc($res);
mysqli_stmt_close($q);

if ($ctx && $ctx['turno_estado'] === 'cerrado') {
    echo json_encode(['success' => false, 'error' => 'El turno está cerrado. Reábrelo para modificarlo.']); exit;
}
if ($ctx) api_require_delete_turno($conn, (int)$ctx['turno_id']);

$stmt = mysqli_prepare($conn, "DELETE FROM turno_checklist_items WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
$aff = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if (!$ok)       { echo json_encode(['success' => false, 'error' => $err]); exit; }
if ($aff === 0) { echo json_encode(['success' => false, 'error' => 'No se encontró el ítem.']); exit; }

if ($ctx) {
    registrar_accion($conn, (int)$ctx['turno_id'], 'checklist_borrado',
        'Eliminó ítem de checklist "' . $ctx['item'] . '"', $ctx['codigo'], $ctx['nombre']);
}

echo json_encode(['success' => true]);
