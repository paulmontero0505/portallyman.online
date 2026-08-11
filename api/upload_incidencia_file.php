<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Subida de adjuntos de una incidencia
   ───────────────────────────────────────────────────────────────────────
   Recibe UN archivo (multipart/form-data):
     · campo "file"  → el archivo
     · campo "tipo"  → "foto" | "declaracion"  (define extensiones permitidas)
   Lo guarda en uploads/incidencias/ con un nombre aleatorio y devuelve la
   ruta relativa para que save_incidencia.php la persista.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/auth.php');
api_require_report();

header('Content-Type: application/json');

$tipo = $_POST['tipo'] ?? '';
if (!in_array($tipo, ['foto', 'declaracion'], true)) {
    echo json_encode(['success' => false, 'error' => 'Tipo de adjunto inválido.']);
    exit;
}

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

// Extensiones y MIME permitidos según el tipo.
$reglas = [
    'foto' => [
        'ext'  => ['jpg', 'jpeg', 'png', 'webp'],
        'mime' => ['image/jpeg', 'image/png', 'image/webp'],
    ],
    'declaracion' => [
        'ext'  => ['pdf', 'jpg', 'jpeg', 'png'],
        'mime' => ['application/pdf', 'image/jpeg', 'image/png'],
    ],
];

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $reglas[$tipo]['ext'], true)) {
    echo json_encode(['success' => false, 'error' => 'Extensión no permitida (' . implode(', ', $reglas[$tipo]['ext']) . ').']);
    exit;
}

// Verificación de MIME real (no confiar en el nombre).
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $reglas[$tipo]['mime'], true)) {
    echo json_encode(['success' => false, 'error' => 'El contenido del archivo no coincide con el tipo permitido.']);
    exit;
}

// Nombre único e impredecible.
$nombre = 'inc_' . $tipo . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destDir = __DIR__ . '/../uploads/incidencias';
$destAbs = $destDir . '/' . $nombre;

if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
    echo json_encode(['success' => false, 'error' => 'No se pudo guardar el archivo en el servidor.']);
    exit;
}

// Ruta relativa a la raíz del proyecto (la que se guarda en BD y se sirve).
$rel = 'uploads/incidencias/' . $nombre;

echo json_encode(['success' => true, 'path' => $rel, 'tipo' => $tipo]);
