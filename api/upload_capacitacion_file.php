<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Subida de UN material/evidencia de una capacitación
   ───────────────────────────────────────────────────────────────────────
   Recibe multipart/form-data { id, file }.

   A diferencia de las evidencias de Charlas —que viajan como metadatos
   hasta que se guarda la charla—, aquí el adjunto se PERSISTE en el acto:
   la capacitación ya existe (se creó con título, fecha y hora antes de
   entrar a llenarla), así que no hay motivo para sostener el archivo en
   memoria del navegador y arriesgarse a perderlo si se cierra el modal.

   El nombre original se conserva (saneado): este material se consulta
   luego desde Drive, y "Arnes_v3.pptx" es buscable; "2026-07-30_14-02.pptx"
   no lo es. Si hay colisión, el Apps Script añade sufijo y devuelve el
   nombre real, que es el que se guarda.

   Si Drive falla, se guarda copia local y el adjunto queda 'pendiente'.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/capacitaciones_catalogo.php');
require_once('../includes/drive_uploader.php');   // sg_drive_subir, sg_guardar_local
api_require_report();

header('Content-Type: application/json');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de capacitación inválido.']); exit;
}

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

// ── Tope de adjuntos ────────────────────────────────────────────────────
$stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM capacitaciones_adjuntos WHERE capacitacion_id=?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$n = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'] ?? 0);
mysqli_stmt_close($stmt);
if ($n >= cap_max_adjuntos()) {
    echo json_encode(['success' => false,
        'error' => 'Ya hay ' . cap_max_adjuntos() . ' archivos adjuntos, que es el máximo.']); exit;
}

// ── Validación del archivo ──────────────────────────────────────────────
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
if ($file['size'] > cap_max_bytes()) {
    echo json_encode(['success' => false, 'error' => 'El archivo supera los 4 MB.']); exit;
}
if (!is_uploaded_file($file['tmp_name'])) {
    echo json_encode(['success' => false, 'error' => 'Archivo no válido.']); exit;
}

// Extensión declarada + MIME real del contenido. Los dos tienen que cuadrar.
$tipos = sg_tipos_permitidos();
$ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!isset($tipos[$ext])) {
    echo json_encode(['success' => false,
        'error' => 'El tipo de archivo ".' . htmlspecialchars($ext) . '" no está permitido.']); exit;
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $tipos[$ext], true)) {
    echo json_encode(['success' => false, 'error' => 'El contenido del archivo no coincide con su extensión.']); exit;
}

// ── Nombre final: el original, saneado ──────────────────────────────────
$base = pathinfo($file['name'], PATHINFO_FILENAME);
$base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base);   // sin rutas, sin espacios, sin acentos raros
$base = trim(preg_replace('/_+/', '_', $base), '_.');
if ($base === '') $base = 'material';
if (mb_strlen($base) > 90) $base = mb_substr($base, 0, 90);
$nombre = $base . '.' . $ext;

$carpeta = cap_carpeta_drive();
$res     = sg_drive_subir($carpeta, $nombre, $mime, $file['tmp_name']);

if (!empty($res['ok'])) {
    $nombreFinal = $res['nombre'] ?? $nombre;   // Drive pudo añadir sufijo por colisión
    $fileId      = $res['fileId'] ?? null;
    $url         = $res['url'] ?? null;
    $local       = null;
    $estado      = 'subido';
    $errMsg      = null;
    $aviso       = null;
} else {
    // Drive falló: respaldo local para no perder el material.
    $nombreFinal = $nombre;
    $fileId      = null;
    $url         = null;
    $local       = sg_guardar_local($carpeta, $nombre, $file['tmp_name']);
    $estado      = $local ? 'pendiente' : 'error';
    $errMsg      = mb_substr($res['error'] ?? 'Fallo desconocido de Drive.', 0, 255);
    $aviso       = 'No se pudo subir a Drive: ' . mb_substr($res['error'] ?? '', 0, 180)
                 . ($local ? ' (guardado en el servidor, se subirá luego).' : '.');
}

$peso = (int)$file['size'];
$ins  = mysqli_prepare($conn,
    "INSERT INTO capacitaciones_adjuntos
        (capacitacion_id, nombre_archivo, mime, peso_bytes, drive_file_id, drive_url,
         ruta_local, estado, error_msg)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($ins, 'ississsss',
    $id, $nombreFinal, $mime, $peso, $fileId, $url, $local, $estado, $errMsg);
$ok = mysqli_stmt_execute($ins);
$adjId = $ok ? (int)mysqli_insert_id($conn) : 0;
$dbErr = mysqli_stmt_error($ins);
mysqli_stmt_close($ins);

if (!$ok) {
    echo json_encode(['success' => false, 'error' => 'No se pudo registrar el adjunto: ' . $dbErr]); exit;
}

echo json_encode([
    'success'  => true,
    'aviso'    => $aviso,
    'adjunto'  => [
        'id'             => $adjId,
        'nombre_archivo' => $nombreFinal,
        'mime'           => $mime,
        'peso_bytes'     => $peso,
        'drive_file_id'  => $fileId,
        'drive_url'      => $url,
        'ruta_local'     => $local,
        'estado'         => $estado,
        'error_msg'      => $errMsg,
    ],
]);
