<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { echo json_encode(['success' => false, 'error' => 'ID inválido.']); exit; }

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, mensaje, enviado_por, message_id, enviado_at
       FROM sugerencias_whatsapp_historial
      WHERE sugerencia_id = ?
      ORDER BY enviado_at ASC, id ASC"
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$r = mysqli_stmt_get_result($stmt);

$historial = [];
while ($row = mysqli_fetch_assoc($r)) {
    $historial[] = [
        'id'          => (int)$row['id'],
        'mensaje'     => $row['mensaje'],
        'enviado_por' => $row['enviado_por'],
        'message_id'  => $row['message_id'],
        'enviado_at'  => $row['enviado_at'],
    ];
}
mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'data' => $historial]);
