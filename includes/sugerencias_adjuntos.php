<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Adjuntos de Sugerencias · normalización y validación
   ───────────────────────────────────────────────────────────────────────
   Funciones puras (sin base de datos) que usa
   api/publico/guardar_sugerencia.php para recibir hasta 3 archivos.
   ═══════════════════════════════════════════════════════════════════════ */

require_once(__DIR__ . '/drive_config.php');

/** Convierte el $_FILES['archivos'] múltiple en una lista plana, sin huecos. */
function sg_normalizar_archivos($f) {
    if (!is_array($f) || !isset($f['name'])) return [];
    $names = is_array($f['name']) ? $f['name'] : [$f['name']];
    $out = [];
    foreach ($names as $i => $name) {
        $error = is_array($f['error']) ? $f['error'][$i] : $f['error'];
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        $out[] = [
            'name'     => $name,
            'tmp_name' => is_array($f['tmp_name']) ? $f['tmp_name'][$i] : $f['tmp_name'],
            'size'     => is_array($f['size'])     ? $f['size'][$i]     : $f['size'],
            'error'    => $error,
        ];
    }
    return $out;
}

/**
 * Devuelve un mensaje de error, o null si el archivo es válido.
 * $verificarUpload = false solo en pruebas (sin petición HTTP real).
 */
function sg_validar_archivo($a, $verificarUpload = true) {
    if ($a['error'] !== UPLOAD_ERR_OK) {
        return 'No se pudo recibir el archivo "' . htmlspecialchars($a['name']) . '".';
    }
    if ($a['size'] <= 0) {
        return 'El archivo "' . htmlspecialchars($a['name']) . '" está vacío.';
    }
    if ($a['size'] > SG_MAX_BYTES) {
        return 'El archivo "' . htmlspecialchars($a['name']) . '" supera los 4 MB.';
    }
    if ($verificarUpload && !is_uploaded_file($a['tmp_name'])) {
        return 'Archivo no válido.';
    }

    $tipos = sg_tipos_permitidos();
    $ext   = strtolower(pathinfo($a['name'], PATHINFO_EXTENSION));
    if (!isset($tipos[$ext])) {
        return 'El tipo de archivo ".' . htmlspecialchars($ext) . '" no está permitido.';
    }

    $mime = sg_mime_real($a['tmp_name']);
    if (!in_array($mime, $tipos[$ext], true)) {
        return 'El contenido de "' . htmlspecialchars($a['name']) . '" no coincide con su extensión.';
    }
    return null;
}

/** MIME real leído del contenido, no del header enviado por el cliente. */
function sg_mime_real($ruta) {
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $m  = finfo_file($fi, $ruta);
        finfo_close($fi);
        if ($m) return $m;
    }
    return 'application/octet-stream';
}

/** Nombre final del adjunto: fecha y hora de subida (+ sufijo si hay varios). */
function sg_nombre_archivo($sello, $indice, $nombreOriginal) {
    $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    return $sello . ($indice > 0 ? '_' . ($indice + 1) : '') . '.' . $ext;
}

/** Inserta la fila del adjunto vinculada a la sugerencia. */
function sg_registrar_adjunto($conn, $sugId, $nombre, $mime, $bytes, $fileId, $url, $local, $estado, $errMsg) {
    $stmt = mysqli_prepare($conn,
        "INSERT INTO sugerencias_adjuntos
           (sugerencia_id, nombre_archivo, mime, peso_bytes, drive_file_id, drive_url, ruta_local, estado, error_msg)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'ississsss',
        $sugId, $nombre, $mime, $bytes, $fileId, $url, $local, $estado, $errMsg);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}
