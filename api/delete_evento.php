<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Eliminar evento de la bitácora
   Body JSON: { id }
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/acciones.php');
api_require_login();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;

if ($id <= 0) { echo json_encode(['success' => false, 'error' => 'Evento inválido.']); exit; }

// Contexto del evento (turno, colaborador, estado del turno) antes de borrar.
$ctx = null;
$q = mysqli_prepare(
    $conn,
    "SELECT tp.id AS tp_id, tp.estado AS tp_estado, tp.turno_id, t.estado AS turno_estado,
            c.codigo, c.nombre, e.tipo, e.hora_inicio
       FROM turno_eventos e
       JOIN turno_personal tp ON tp.id = e.turno_personal_id
       JOIN turnos t          ON t.id = tp.turno_id
       JOIN colaboradores c   ON c.id = tp.colaborador_id
      WHERE e.id = ? LIMIT 1"
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

$stmt = mysqli_prepare($conn, "DELETE FROM turno_eventos WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
$aff = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if (!$ok)       { echo json_encode(['success' => false, 'error' => $err]); exit; }
if ($aff === 0) { echo json_encode(['success' => false, 'error' => 'No se encontró el evento.']); exit; }

// Si se borró un refrigerio y el colaborador seguía en estado 'refrigerio',
// se le regresa a 'activo' (evita que quede "en refrigerio" sin evento que lo respalde).
if ($ctx && $ctx['tipo'] === 'refrigerio' && $ctx['tp_estado'] === 'refrigerio') {
    mysqli_query($conn, "UPDATE turno_personal SET estado = 'activo' WHERE id = " . (int)$ctx['tp_id']);
}

if ($ctx) {
    $labelTipo = ['traslado' => 'Traslado', 'refrigerio' => 'Refrigerio', 'permiso' => 'Permiso'];
    registrar_accion($conn, (int)$ctx['turno_id'], 'evento_borrado',
        'Eliminó evento ' . ($labelTipo[$ctx['tipo']] ?? $ctx['tipo']) . ' ' . substr($ctx['hora_inicio'], 0, 5),
        $ctx['codigo'], $ctx['nombre']);
}

echo json_encode(['success' => true]);
