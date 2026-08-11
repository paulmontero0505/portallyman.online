<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Envía una capacitación a validación (JSON)
   ───────────────────────────────────────────────────────────────────────
   Body JSON: { id }
   Transición:  programada ──▶ por_validar

   Las guardas (≥1 tema y asistencia completa) se comprueban AQUÍ, no solo
   en el navegador: el botón deshabilitado es una cortesía, no una barrera.

   Al enviar se SELLA `total_plantilla` con el nº de colaboradores activos
   en este momento. Sin ese sello, dar de alta a alguien mañana bajaría el
   porcentaje de asistencia de esta capacitación y de todo el histórico.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/capacitaciones_catalogo.php');
api_require_report();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'ID inválido.']); exit; }

// ── Autorización ────────────────────────────────────────────────────────
$stmt = mysqli_prepare($conn, "SELECT id, estado, coordinador_id FROM capacitaciones WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$cap = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$permiso = cap_puede_editar($cap);
if (!$permiso['ok']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $permiso['error']]); exit;
}

// ── Guardas ─────────────────────────────────────────────────────────────
$temas = 0;
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM capacitaciones_temas WHERE capacitacion_id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$temas = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'] ?? 0);
mysqli_stmt_close($stmt);

$marcados = 0;
$stmt = mysqli_prepare($conn,
    "SELECT COUNT(*) AS n FROM capacitaciones_asistentes a
       JOIN colaboradores col ON col.id = a.colaborador_id AND col.activo = 1
      WHERE a.capacitacion_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$marcados = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'] ?? 0);
mysqli_stmt_close($stmt);

$plantilla = 0;
$rp = mysqli_query($conn, "SELECT COUNT(*) AS n FROM colaboradores WHERE activo = 1");
if ($rp && ($r = mysqli_fetch_assoc($rp))) $plantilla = (int)$r['n'];

$motivo = cap_motivo_no_enviable($temas, $marcados, $plantilla);
if ($motivo !== null) {
    echo json_encode(['success' => false, 'error' => $motivo]); exit;
}

// ── Transición ──────────────────────────────────────────────────────────
// El WHERE repite estado='programada' para que dos envíos simultáneos no
// puedan sellar total_plantilla dos veces con conteos distintos.
$upd = mysqli_prepare($conn,
    "UPDATE capacitaciones
        SET estado='por_validar', total_plantilla=?, enviado_at=NOW()
      WHERE id=? AND estado='programada'");
mysqli_stmt_bind_param($upd, 'ii', $plantilla, $id);
$ok       = mysqli_stmt_execute($upd);
$afectada = mysqli_stmt_affected_rows($upd);
$err      = mysqli_stmt_error($upd);
mysqli_stmt_close($upd);

if (!$ok) {
    echo json_encode(['success'=>false,'error'=>$err ?: 'No se pudo enviar a validación.']); exit;
}
if ($afectada === 0) {
    echo json_encode(['success'=>false,'error'=>'La capacitación ya había sido enviada.']); exit;
}

echo json_encode([
    'success'         => true,
    'id'              => $id,
    'estado'          => 'por_validar',
    'total_plantilla' => $plantilla,
]);
