<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Subida de la foto de un reconocimiento
   ───────────────────────────────────────────────────────────────────────
   Recibe UN archivo (multipart/form-data) en el campo "file".
   Lo guarda en uploads/reconocimientos/ con un nombre aleatorio y devuelve
   la ruta relativa para que save_reconocimiento.php la persista.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/auth.php');
api_require_report();

header('Content-Type: application/json');

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $codes = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo excede el tamaño permitido por el servidor.',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo excede el tamaño permitido.',
        UPLOAD_ERR_PARTIAL    => 'La subida quedó incompleta. Reintenta.',
        UPLOAD_ERR_NO_FILE    => 'No se recibió ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Error de servidor (sin carpeta temporal).',
        UPLOAD_ERR_CANT_WRITE => 'Error de servidor (no se pudo escribir).',
    ];
    $err = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    echo json_encode(['success' => false, 'error' => $codes[$err] ?? 'Error al subir el archivo.']);
    exit;
}

$file = $_FILES['file'];

// Límite de tamaño: 8 MB.
$MAX = 8 * 1024 * 1024;
if ($file['size'] > $MAX) {
    echo json_encode(['success' => false, 'error' => 'El archivo supera los 8 MB.']);
    exit;
}

// Solo imágenes.
$EXT  = ['jpg', 'jpeg', 'png', 'webp'];
$MIME = ['image/jpeg', 'image/png', 'image/webp'];

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $EXT, true)) {
    echo json_encode(['success' => false, 'error' => 'Extensión no permitida (' . implode(', ', $EXT) . ').']);
    exit;
}

// Verificación de MIME real (no confiar en el nombre).
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $MIME, true)) {
    echo json_encode(['success' => false, 'error' => 'El contenido del archivo no coincide con una imagen permitida.']);
    exit;
}

// Nombre único e impredecible.
$nombre  = 'rec_foto_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destDir = __DIR__ . '/../uploads/reconocimientos';
if (!is_dir($destDir)) @mkdir($destDir, 0775, true);
$destAbs = $destDir . '/' . $nombre;

if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
    echo json_encode(['success' => false, 'error' => 'No se pudo guardar el archivo en el servidor.']);
    exit;
}

// Ruta relativa a la raíz del proyecto (la que se guarda en BD y se sirve).
$rel = 'uploads/reconocimientos/' . $nombre;

echo json_encode(['success' => true, 'path' => $rel, 'tipo' => 'foto']);
