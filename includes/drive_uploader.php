<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Subida de adjuntos a Google Drive vía Apps Script Web App
   ───────────────────────────────────────────────────────────────────────
   sg_drive_subir()  → envía el archivo al Web App (POST JSON con base64).
   sg_guardar_local() → copia de respaldo en uploads/ cuando Drive falla,
                        para que ninguna sugerencia se pierda.

   El Web App corre como el dueño de la carpeta de Drive, así que aquí no se
   manejan tokens de OAuth. Ver apps-script/SugerenciasDrive.gs.
   ═══════════════════════════════════════════════════════════════════════ */

require_once(__DIR__ . '/drive_config.php');

/**
 * Sube un archivo a la subcarpeta de Drive indicada.
 *
 * @param string $carpeta  Nombre de la subcarpeta (ej. "Observaciones").
 * @param string $nombre   Nombre final del archivo (ej. "2026-07-09_14-32-05.pdf").
 * @param string $mime     MIME real del archivo.
 * @param string $rutaTmp  Ruta temporal del archivo en el servidor.
 * @return array{ok:bool, fileId?:string, url?:string, error?:string}
 */
function sg_drive_subir($carpeta, $nombre, $mime, $rutaTmp) {
    if (DRIVE_APPS_SCRIPT_URL === '' || DRIVE_SHARED_SECRET === '') {
        return ['ok' => false, 'error' => 'Google Drive no está configurado en el servidor.'];
    }
    if (!is_readable($rutaTmp)) {
        return ['ok' => false, 'error' => 'No se pudo leer el archivo temporal.'];
    }

    $bin = file_get_contents($rutaTmp);
    if ($bin === false) {
        return ['ok' => false, 'error' => 'No se pudo leer el archivo temporal.'];
    }

    $payload = json_encode([
        'secreto'    => DRIVE_SHARED_SECRET,
        'raizId'     => DRIVE_ROOT_FOLDER_ID,
        'carpeta'    => $carpeta,
        'nombre'     => $nombre,
        'mime'       => $mime,
        'contenido'  => base64_encode($bin),
    ]);
    unset($bin);

    $ch = curl_init(DRIVE_APPS_SCRIPT_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        // Apps Script responde 302 hacia script.googleusercontent.com.
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'error' => 'Error de red hacia Drive: ' . $err];
    }
    if ($code !== 200) {
        return ['ok' => false, 'error' => 'Drive respondió HTTP ' . $code];
    }

    $j = json_decode($resp, true);
    if (!is_array($j)) {
        return ['ok' => false, 'error' => 'Respuesta inválida de Drive.'];
    }
    if (empty($j['ok'])) {
        return ['ok' => false, 'error' => $j['error'] ?? 'Drive rechazó el archivo.'];
    }

    // 'nombre' es el nombre REAL en Drive: puede diferir del pedido si hubo
    // colisión y el script le añadió un sufijo (-2, -3, …).
    return [
        'ok'     => true,
        'fileId' => $j['fileId'] ?? '',
        'url'    => $j['url'] ?? '',
        'nombre' => $j['nombre'] ?? $nombre,
    ];
}

/**
 * Copia de respaldo local cuando Drive no está disponible.
 * Devuelve la ruta relativa guardada, o null si tampoco se pudo.
 */
function sg_guardar_local($carpeta, $nombre, $rutaTmp) {
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($carpeta));
    $dir  = SG_UPLOAD_DIR . '/' . $slug;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return null;

    // Evita que el directorio quede navegable desde la web.
    $ht = SG_UPLOAD_DIR . '/.htaccess';
    if (!file_exists($ht)) @file_put_contents($ht, "Options -Indexes\nDeny from all\n");

    $destino = $dir . '/' . $nombre;
    $ok = is_uploaded_file($rutaTmp)
        ? @move_uploaded_file($rutaTmp, $destino)
        : @copy($rutaTmp, $destino);

    return $ok ? ('uploads/sugerencias/' . $slug . '/' . $nombre) : null;
}
