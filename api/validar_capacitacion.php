<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Validación de una capacitación por el Administrador (JSON)
   ───────────────────────────────────────────────────────────────────────
   Body JSON: { id, resultado: 'realizada'|'no_realizada', comentario? }
   Transición:  por_validar ──▶ realizada | no_realizada   (terminal)

   SOLO Administrador: api_require_admin(). Que el coordinador pudiera
   validar lo suyo vaciaría de sentido el paso entero.

   «No realizada» NO borra nada. El registro se conserva con sus temas y
   sus marcas: saber qué se planificó y no se hizo es justamente el dato
   que da valor al módulo.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/capacitaciones_catalogo.php');
api_require_admin();

header('Content-Type: application/json');

$data       = json_decode(file_get_contents('php://input'), true);
$id         = isset($data['id']) ? (int)$data['id'] : 0;
$resultado  = trim($data['resultado'] ?? '');
$comentario = trim($data['comentario'] ?? '');

if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido.']); exit; }
if (!in_array($resultado, ['realizada', 'no_realizada'], true)) {
    echo json_encode(['success'=>false,'error'=>'Indica si la capacitación se realizó o no.']); exit;
}
// Marcar «no realizada» sin decir por qué deja el registro sin la única
// información que lo hace accionable.
if ($resultado === 'no_realizada' && $comentario === '') {
    echo json_encode(['success'=>false,'error'=>'Explica en el comentario por qué no se realizó.']); exit;
}
if (mb_strlen($comentario) > 4000) {
    echo json_encode(['success'=>false,'error'=>'El comentario supera los 4000 caracteres.']); exit;
}

// ── Estado de partida ───────────────────────────────────────────────────
$stmt = mysqli_prepare($conn, "SELECT id, estado FROM capacitaciones WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$cap = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$cap) { echo json_encode(['success'=>false,'error'=>'La capacitación no existe.']); exit; }
if ($cap['estado'] === 'programada') {
    echo json_encode(['success'=>false,
        'error'=>'El coordinador todavía no la ha enviado a validación.']); exit;
}
if (cap_es_terminal($cap['estado'])) {
    echo json_encode(['success'=>false,
        'error'=>'Esta capacitación ya fue validada como «' . cap_estado_label($cap['estado']) . '».']); exit;
}

$admin     = $_SESSION['user_name'] ?? 'Administrador';
$adminId   = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$comentSql = $comentario !== '' ? $comentario : null;

// El WHERE repite estado='por_validar' para que dos validaciones simultáneas
// no se pisen: la segunda afecta 0 filas y se avisa.
$upd = mysqli_prepare($conn,
    "UPDATE capacitaciones
        SET estado=?, validado_por=?, validado_por_id=?, validado_at=NOW(), comentario_admin=?
      WHERE id=? AND estado='por_validar'");
mysqli_stmt_bind_param($upd, 'ssisi', $resultado, $admin, $adminId, $comentSql, $id);
$ok       = mysqli_stmt_execute($upd);
$afectada = mysqli_stmt_affected_rows($upd);
$err      = mysqli_stmt_error($upd);
mysqli_stmt_close($upd);

if (!$ok) {
    echo json_encode(['success'=>false,'error'=>$err ?: 'No se pudo validar la capacitación.']); exit;
}
if ($afectada === 0) {
    echo json_encode(['success'=>false,'error'=>'Otro administrador acaba de validarla. Recarga la página.']); exit;
}

echo json_encode([
    'success'      => true,
    'id'           => $id,
    'estado'       => $resultado,
    'validado_por' => $admin,
]);
