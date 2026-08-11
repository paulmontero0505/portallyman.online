<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Cerrar / reabrir turno
   Body JSON: { turnoId, estado: "cerrado" | "abierto" }
   Permisos:
     · CERRAR  (estado=cerrado): Administrador, Supervisor o Coordinador.
     · REABRIR (estado=abierto): SOLO Administrador o Supervisor.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/acciones.php');
api_require_login();

header('Content-Type: application/json');

$data    = json_decode(file_get_contents('php://input'), true);
$turnoId = isset($data['turnoId']) ? (int)$data['turnoId'] : 0;
$estado  = trim($data['estado'] ?? '');

if ($turnoId <= 0) { echo json_encode(['success' => false, 'error' => 'Turno inválido.']); exit; }
if (!in_array($estado, ['cerrado', 'abierto'], true)) { echo json_encode(['success' => false, 'error' => 'Estado inválido.']); exit; }

// ── Autorización según la acción ──
if ($estado === 'abierto') {
    // Reabrir: solo Administrador o Supervisor.
    if (!can_validate()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Solo un Administrador o Supervisor puede reabrir un turno cerrado.']);
        exit;
    }
} else {
    // Cerrar: Administrador, Supervisor o Coordinador.
    if (!can_report()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'No tienes permiso para cerrar turnos.']);
        exit;
    }
}

$stmt = mysqli_prepare($conn, "UPDATE turnos SET estado=? WHERE id=?");
mysqli_stmt_bind_param($stmt, 'si', $estado, $turnoId);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);

if (!$ok) { echo json_encode(['success' => false, 'error' => $err]); exit; }

// Detalle con el actor explícito (además, registrar_accion guarda usuario_id/nombre/rol).
$actor   = ($_SESSION['user_name'] ?? 'Usuario') . ' (' . ($_SESSION['user_rol'] ?? '—') . ')';
$detalle = ($estado === 'cerrado' ? 'Turno cerrado / validado por ' : 'Turno reabierto por ') . $actor;
registrar_accion($conn, $turnoId, 'cierre', $detalle);

// Al cerrar, espejar el resumen y el conteo por persona del turno a Sheets.
if ($estado === 'cerrado') {
    sheets_sync_turno_cierre($conn, $turnoId);
}

echo json_encode(['success' => true, 'estado' => $estado]);
