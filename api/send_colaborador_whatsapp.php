<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/whatsapp_baileys.php');
api_require_admin();

header('Content-Type: application/json');
$data = json_decode(file_get_contents('php://input'), true);
$colaboradorId = is_array($data) ? (int)($data['colaborador_id'] ?? 0) : 0;
$mensaje = trim((string)($data['mensaje'] ?? ''));
if ($colaboradorId <= 0 || mb_strlen($mensaje) < 3 || mb_strlen($mensaje) > 800) {
    echo json_encode(['success' => false, 'error' => 'Escribe un mensaje de 3 a 800 caracteres.']); exit;
}

$stmt = mysqli_prepare($conn, 'SELECT id, nombre, celular FROM colaboradores WHERE id=? AND activo=1 LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $colaboradorId);
mysqli_stmt_execute($stmt);
$colaborador = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$colaborador) { echo json_encode(['success' => false, 'error' => 'El colaborador no está disponible.']); exit; }

[$enviado, $resultado, $error] = wa_enviar_texto_colaborador((string)($colaborador['celular'] ?? ''), $mensaje);
if (!$enviado) { echo json_encode(['success' => false, 'error' => $error]); exit; }

echo json_encode(['success' => true, 'messageId' => substr((string)($resultado['messageId'] ?? ''), 0, 120)]);
