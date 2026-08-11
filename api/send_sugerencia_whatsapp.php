<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/sugerencias_catalogo.php');
require_once('../includes/whatsapp_baileys.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? (int)$data['id'] : 0;
$respuesta = trim($data['respuesta'] ?? '');
if ($id <= 0 || mb_strlen($respuesta) < 3 || mb_strlen($respuesta) > 800) {
    echo json_encode(['success' => false, 'error' => 'Escribe una respuesta de 3 a 800 caracteres.']); exit;
}

$stmt = mysqli_prepare($conn,
    "SELECT s.id, s.canal, s.colaborador_id, s.colaborador_nombre, s.detalle, s.puntaje, s.puntaje_comentario,
            c.celular
       FROM sugerencias_tallyman s
       LEFT JOIN colaboradores c ON c.id = s.colaborador_id
      WHERE s.id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$sugerencia = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$sugerencia) { echo json_encode(['success' => false, 'error' => 'La sugerencia no existe.']); exit; }
if ($sugerencia['colaborador_id'] === null || !$sugerencia['colaborador_nombre']) {
    echo json_encode(['success' => false, 'error' => 'Las sugerencias anónimas no reciben respuestas por WhatsApp.']); exit;
}

$canales = sg_canales();
$canal = $canales[$sugerencia['canal']]['label'] ?? 'sugerencia';
$puntaje = $sugerencia['canal'] === sg_canal_puntuable() && $sugerencia['puntaje'] !== null
    ? (int)$sugerencia['puntaje'] : null;
[$enviado, $resultado, $error] = wa_enviar_respuesta_sugerencia(
    (string)($sugerencia['celular'] ?? ''),
    (string)$sugerencia['colaborador_nombre'],
    $canal,
    (string)($sugerencia['detalle'] ?? ''),
    $respuesta,
    $puntaje,
    (string)($sugerencia['puntaje_comentario'] ?? '')
);
if (!$enviado) { echo json_encode(['success' => false, 'error' => $error]); exit; }

$enviadoPor = $_SESSION['user_name'] ?? 'Administrador';
$messageId = substr((string)($resultado['messageId'] ?? ''), 0, 120);
$stmt = mysqli_prepare($conn,
    "INSERT INTO sugerencias_whatsapp_historial (sugerencia_id, mensaje, enviado_por, message_id)
     VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, 'isss', $id, $respuesta, $enviadoPor, $messageId);
$okHistorial = mysqli_stmt_execute($stmt);
$errHistorial = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);
if (!$okHistorial) {
    echo json_encode(['success' => false, 'error' => 'El mensaje fue enviado, pero no se pudo guardar en el historial: ' . $errHistorial]); exit;
}

$stmt = mysqli_prepare($conn,
    "UPDATE sugerencias_tallyman
        SET respuesta_whatsapp=?, respuesta_whatsapp_por=?, respuesta_whatsapp_at=NOW(), respuesta_whatsapp_message_id=?
      WHERE id=?");
mysqli_stmt_bind_param($stmt, 'sssi', $respuesta, $enviadoPor, $messageId, $id);
$ok = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);
if (!$ok) { echo json_encode(['success' => false, 'error' => $err ?: 'El mensaje fue enviado, pero no se pudo registrar.']); exit; }

echo json_encode(['success' => true, 'messageId' => $messageId]);
