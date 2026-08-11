<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Subir PDF del Relevo a Google Drive
   Body JSON: { folder, filename, content(base64) }
   El navegador NO puede llamar a Apps Script directo (CORS); este endpoint
   reenvía la subida por cURL desde el servidor (sin CORS), igual que las
   incidencias en includes/sheets.php. Guarda en RELEVO_DRIVE_FOLDER_ID.
   ═══════════════════════════════════════════════════════════════════════ */
require_once(__DIR__ . '/../includes/auth.php');
require_once(__DIR__ . '/../includes/sheets.php');

header('Content-Type: application/json; charset=utf-8');
api_require_operaciones();

if (!sheets_enabled()) {
    echo json_encode(['ok' => false, 'error' => 'Integración de Drive no configurada']); exit;
}

$folderId = defined('RELEVO_DRIVE_FOLDER_ID') && RELEVO_DRIVE_FOLDER_ID !== ''
          ? RELEVO_DRIVE_FOLDER_ID
          : (defined('DRIVE_FOLDER_ID') ? DRIVE_FOLDER_ID : '');
if ($folderId === '') {
    echo json_encode(['ok' => false, 'error' => 'Carpeta de relevo no configurada']); exit;
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) { echo json_encode(['ok' => false, 'error' => 'Payload inválido']); exit; }

$folder   = trim($in['folder'] ?? 'Relevo');
$filename = trim($in['filename'] ?? 'relevo.pdf');
$content  = $in['content'] ?? '';   // base64 del PDF
if ($content === '') { echo json_encode(['ok' => false, 'error' => 'Sin contenido']); exit; }

$payload = json_encode([
    'token'    => SHEETS_TOKEN,
    'action'   => 'driveUpload',
    'folderId' => $folderId,
    'folder'   => $folder,
    'filename' => $filename,
    'mimeType' => 'application/pdf',
    'content'  => $content,
], JSON_UNESCAPED_UNICODE);

try {
    $ch = curl_init(SHEETS_WEBAPP_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,   // Apps Script redirige a googleusercontent.com
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 30,     // subir archivos toma más que filas
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($resp === false || $code < 200 || $code >= 300) {
        echo json_encode(['ok' => false, 'error' => 'HTTP ' . $code . ' ' . ($cerr ?: '')]); exit;
    }
    $j = json_decode($resp, true);
    if (!is_array($j) || empty($j['ok'])) {
        echo json_encode(['ok' => false, 'error' => 'Respuesta: ' . substr((string)$resp, 0, 200)]); exit;
    }
    echo json_encode(['ok' => true, 'url' => $j['url'] ?? null, 'folder' => $j['folder'] ?? $folder]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
