<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Configuración de subida a Google Drive (vía Apps Script)
   ───────────────────────────────────────────────────────────────────────
   Los adjuntos NO se suben con OAuth ni cuenta de servicio. Se envían a un
   Web App de Google Apps Script desplegado en la cuenta dueña de la carpeta
   (ver apps-script/SugerenciasDrive.gs). El script corre como el dueño, así
   que no hay problemas de cuota ni de credenciales en el servidor.

   Configura DRIVE_APPS_SCRIPT_URL y DRIVE_SHARED_SECRET con los valores que
   obtengas al desplegar el script. Se pueden sobreescribir por variables de
   entorno, igual que las credenciales de db.php.
   ═══════════════════════════════════════════════════════════════════════ */

// URL del Web App desplegado. Termina en /exec  (NO en /dev).
if (!defined('DRIVE_APPS_SCRIPT_URL')) {
    define('DRIVE_APPS_SCRIPT_URL', getenv('DRIVE_APPS_SCRIPT_URL') ?: 'https://script.google.com/macros/s/AKfycbz-3qAeOLX4OMKd9IPP2xq2Ju3_fPEqdLf57wECO9AIRA3AloPbvzlSP1Q4WQ3RiBXzEg/exec');
}

// Secreto compartido: debe coincidir con SECRETO dentro del Apps Script.
// Sirve para que nadie más pueda escribir en tu Drive llamando a la URL.
if (!defined('DRIVE_SHARED_SECRET')) {
    define('DRIVE_SHARED_SECRET', getenv('DRIVE_SHARED_SECRET') ?: 'c0934ee5bdedb112e2eeb75fa6e75f51407baf503cea6965acf4192a410bc84f');
}

// Carpeta raíz de Drive donde viven las subcarpetas por canal.
if (!defined('DRIVE_ROOT_FOLDER_ID')) {
    define('DRIVE_ROOT_FOLDER_ID', getenv('DRIVE_ROOT_FOLDER_ID') ?: '1XoA32V9CW4x8V5dW-bokC5Zqz9DlMdyv');
}

// ── Reglas de los adjuntos ──────────────────────────────────────────────
if (!defined('SG_MAX_ARCHIVOS'))   define('SG_MAX_ARCHIVOS', 3);
if (!defined('SG_MAX_BYTES'))      define('SG_MAX_BYTES', 4 * 1024 * 1024); // 4 MB por archivo
if (!defined('SG_UPLOAD_DIR'))     define('SG_UPLOAD_DIR', __DIR__ . '/../uploads/sugerencias');

/**
 * Tipos permitidos: extensión => MIME esperado.
 * La validación en servidor compara la extensión Y el MIME real del archivo.
 */
function sg_tipos_permitidos() {
    return [
        // Imágenes
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'webp' => ['image/webp'],
        'gif'  => ['image/gif'],
        'heic' => ['image/heic', 'image/heif', 'application/octet-stream'],
        // PDF
        'pdf'  => ['application/pdf'],
        // Documentos
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls'  => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt'  => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'txt'  => ['text/plain'],
        'csv'  => ['text/plain', 'text/csv'],
        // Video
        'mp4'  => ['video/mp4'],
        'webm' => ['video/webm'],
        'mov'  => ['video/quicktime'],
        '3gp'  => ['video/3gpp'],
    ];
}

/** Lista de extensiones para el atributo accept del <input type="file">. */
function sg_accept_attr() {
    return '.' . implode(',.', array_keys(sg_tipos_permitidos()));
}
