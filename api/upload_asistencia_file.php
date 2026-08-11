<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Subida de UNA evidencia de una asistencia pre-operativa
   ───────────────────────────────────────────────────────────────────────
   Recibe multipart/form-data { file }. Valida (imagen/video/PDF < 4 MB),
   la sube a Google Drive vía Apps Script (subcarpeta "Asistencias
   Pre-Operativas") y devuelve los metadatos para que save_asistencia.php
   los persista. Si Drive falla, guarda una copia local y marca 'pendiente'.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/auth.php');
require_once('../includes/asistencias_catalogo.php');
require_once('../includes/drive_uploader.php');   // sg_drive_subir, sg_guardar_local, reglas
api_require_report();

header('Content-Type: application/json');

if (empty($_FILES['file']) || !isset($_FILES['file']['error'])) {
    echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo.']); exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $codes = [
        UPLOAD_ERR_INI_SIZE  => 'El archivo excede el tamaño permitido por el servidor.',
        UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño permitido.',
        UPLOAD_ERR_PARTIAL   => 'La subida quedó incompleta. Reintenta.',
        UPLOAD_ERR_NO_FILE   => 'No se recibió ningún archivo.',
    ];
    echo json_encode(['success' => false, 'error' => $codes[$file['error']] ?? 'Error al subir el archivo.']); exit;
}
if ($file['size'] <= 0) {
    echo json_encode(['success' => false, 'error' => 'El archivo está vacío.']); exit;
}
if ($file['size'] > SG_MAX_BYTES) {
    echo json_encode(['success' => false, 'error' => 'El archivo supera los 4 MB.']); exit;
}
if (!is_uploaded_file($file['tmp_name'])) {
    echo json_encode(['success' => false, 'error' => 'Archivo no válido.']); exit;
}

// Extensión + MIME real (mismos tipos que Sugerencias: imagen, video, PDF, doc).
$tipos = sg_tipos_permitidos();
$ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!isset($tipos[$ext])) {
    echo json_encode(['success' => false, 'error' => 'El tipo de archivo ".' . htmlspecialchars($ext) . '" no está permitido.']); exit;
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $tipos[$ext], true)) {
    echo json_encode(['success' => false, 'error' => 'El contenido del archivo no coincide con su extensión.']); exit;
}

$carpeta = aso_carpeta_drive();
$nombre  = date('Y-m-d_H-i-s') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;

$res = sg_drive_subir($carpeta, $nombre, $mime, $file['tmp_name']);

if (!empty($res['ok'])) {
    echo json_encode([
        'success'    => true,
        'nombre'     => $res['nombre'] ?? $nombre,
        'mime'       => $mime,
        'peso_bytes' => (int)$file['size'],
        'drive_file_id' => $res['fileId'] ?? '',
        'drive_url'  => $res['url'] ?? '',
        'ruta_local' => null,
        'estado'     => 'subido',
    ]);
    exit;
}

// Drive falló: respaldo local para no perder la evidencia.
$local = sg_guardar_local($carpeta, $nombre, $file['tmp_name']);
echo json_encode([
    'success'    => true,      // el archivo no se perdió; la asistencia se puede guardar igual
    'nombre'     => $nombre,
    'mime'       => $mime,
    'peso_bytes' => (int)$file['size'],
    'drive_file_id' => null,
    'drive_url'  => null,
    'ruta_local' => $local,
    'estado'     => $local ? 'pendiente' : 'error',
    'aviso'      => 'No se pudo subir a Drive: ' . mb_substr($res['error'] ?? '', 0, 180)
                    . ($local ? ' (guardado en el servidor, se subirá luego).' : '.'),
]);
