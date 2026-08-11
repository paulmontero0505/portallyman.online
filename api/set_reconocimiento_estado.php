<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Cambia el estado de un reconocimiento (JSON)
   ───────────────────────────────────────────────────────────────────────
   Body JSON: { id, estado: 'pendiente'|'aprobado'|'rechazado' }
   · Al APROBAR se registra al supervisor (nombre de la sesión) y la fecha:
     esos datos firman el diploma.
   · Al dejar PENDIENTE se limpian los datos del supervisor.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/reconocimientos_catalogo.php');
api_require_report();

header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true);
$id     = isset($data['id']) ? (int)$data['id'] : 0;
$estado = trim($data['estado'] ?? '');

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID inválido.']); exit;
}
if (!array_key_exists($estado, rec_estados())) {
    echo json_encode(['success' => false, 'error' => 'Estado inválido.']); exit;
}

// Verificar que el reconocimiento existe.
$stmt = mysqli_prepare($conn, "SELECT id FROM reconocimientos WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$existe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$existe) {
    echo json_encode(['success' => false, 'error' => 'El reconocimiento no existe.']); exit;
}

$supervisor   = $_SESSION['user_name'] ?? 'Supervisor';
$supervisorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if ($estado === 'pendiente') {
    // Vuelve a pendiente: se limpia la firma del supervisor.
    $stmt = mysqli_prepare($conn,
        "UPDATE reconocimientos
            SET estado='pendiente', supervisor=NULL, supervisor_id=NULL, aprobado_at=NULL
          WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
} elseif ($estado === 'aprobado') {
    $stmt = mysqli_prepare($conn,
        "UPDATE reconocimientos
            SET estado='aprobado', supervisor=?, supervisor_id=?, aprobado_at=NOW()
          WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'sii', $supervisor, $supervisorId, $id);
} else { // rechazado
    $stmt = mysqli_prepare($conn,
        "UPDATE reconocimientos
            SET estado='rechazado', supervisor=?, supervisor_id=?, aprobado_at=NULL
          WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'sii', $supervisor, $supervisorId, $id);
}

$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo actualizar el estado.']); exit;
}

echo json_encode([
    'success'    => true,
    'id'         => $id,
    'estado'     => $estado,
    'supervisor' => ($estado === 'pendiente') ? null : $supervisor,
]);
