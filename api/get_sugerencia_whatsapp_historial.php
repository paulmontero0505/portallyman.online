<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Sugerencia inválida.']); exit;
}

$stmt = mysqli_prepare($conn,
    'SELECT id, mensaje, enviado_por, message_id, enviado_at
       FROM sugerencias_whatsapp_historial
      WHERE sugerencia_id=?
      ORDER BY enviado_at DESC, id DESC');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$data = [];
while ($row = mysqli_fetch_assoc($res)) $data[] = $row;
mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'data' => $data]);
