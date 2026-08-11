<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Espejo a Google Sheets (Apps Script Web App)
   ───────────────────────────────────────────────────────────────────────
   MySQL es la fuente de verdad. Estas funciones EMPUJAN (copian) datos a una
   Google Sheet vía un Web App de Apps Script. Todo es OPT-IN y a prueba de
   fallos: si no hay config, no hay internet o cURL no está, NO se rompe el
   guardado — solo se registra el error en logs/sheets.log.

   Config (la crea el usuario): includes/sheets_config.php define
     SHEETS_WEBAPP_URL, SHEETS_TOKEN  y opcional SHEETS_SHEET_URL.
   Ver includes/sheets_config.example.php y el script de Apps Script entregado.
   ═══════════════════════════════════════════════════════════════════════ */

$__sheets_cfg = __DIR__ . '/sheets_config.php';
if (is_file($__sheets_cfg)) require_once $__sheets_cfg;

/** ¿Está configurada e instalable la integración? */
function sheets_enabled() {
    return defined('SHEETS_WEBAPP_URL') && SHEETS_WEBAPP_URL !== ''
        && defined('SHEETS_TOKEN') && SHEETS_TOKEN !== ''
        && function_exists('curl_init');
}

/** Registra un error de sincronización (no molesta al usuario). */
function sheets_log($msg) {
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @file_put_contents($dir . '/sheets.log', '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
}

/**
 * Envía filas a una pestaña. NUNCA lanza excepción; devuelve true/false.
 *   $sheet  nombre de pestaña destino
 *   $rows   array de arrays (filas); cada fila es array de valores escalares
 *   $mode   'append' | 'replace' | 'upsert'
 *   $opts   ['header' => [...], 'keyCols' => [1-based...]]
 */
function sheets_push($sheet, array $rows, $mode = 'append', array $opts = []) {
    if (!sheets_enabled()) return false;
    if ($mode !== 'replace' && empty($rows)) return true;   // nada que agregar/actualizar

    $payload = json_encode([
        'token'   => SHEETS_TOKEN,
        'sheet'   => $sheet,
        'mode'    => $mode,
        'rows'    => array_values($rows),
        'header'  => $opts['header']  ?? [],
        'keyCols' => $opts['keyCols'] ?? [],
    ], JSON_UNESCAPED_UNICODE);

    try {
        $ch = curl_init(SHEETS_WEBAPP_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,   // Apps Script redirige a googleusercontent.com
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 10,
            // XAMPP normalmente no trae un bundle de CA configurado; al ser una
            // llamada saliente a una URL fija de Google desde un equipo local,
            // se desactiva la verificación para que funcione sin instalar cacert.
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $code < 200 || $code >= 300) {
            sheets_log("sheet=$sheet mode=$mode http=$code err=" . ($cerr ?: 'http'));
            return false;
        }
        $j = json_decode($resp, true);
        if (!is_array($j) || empty($j['ok'])) {
            sheets_log("sheet=$sheet mode=$mode resp=" . substr((string)$resp, 0, 200));
            return false;
        }
        return true;
    } catch (Throwable $e) {
        sheets_log("sheet=$sheet mode=$mode exc=" . $e->getMessage());
        return false;
    }
}

/** ¿Está lista la subida a Drive? (mismo Web App/token + carpeta destino). */
function drive_enabled() {
    return sheets_enabled() && defined('DRIVE_FOLDER_ID') && DRIVE_FOLDER_ID !== '';
}

/**
 * Sube un archivo local a Google Drive vía el Web App de Apps Script.
 * Lo guarda dentro de DRIVE_FOLDER_ID, en la subcarpeta $subfolder (se crea
 * si no existe). NUNCA lanza: devuelve la URL de Drive o null si falla.
 *   $absPath    ruta absoluta del archivo en el servidor
 *   $subfolder  nombre de la subcarpeta (ej. "NOMBRE - 2026-06-04")
 *   $fileName   nombre con que se guardará en Drive (opcional; por defecto basename)
 */
function drive_upload($absPath, $subfolder, $fileName = null) {
    if (!drive_enabled()) return null;
    if (!is_file($absPath) || !is_readable($absPath)) { sheets_log('drive: archivo no legible ' . $absPath); return null; }

    $data = @file_get_contents($absPath);
    if ($data === false) { sheets_log('drive: no se pudo leer ' . $absPath); return null; }

    $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
    $mime  = $finfo ? finfo_file($finfo, $absPath) : 'application/octet-stream';
    if ($finfo) finfo_close($finfo);

    $payload = json_encode([
        'token'    => SHEETS_TOKEN,
        'action'   => 'driveUpload',
        'folderId' => DRIVE_FOLDER_ID,
        'folder'   => $subfolder,
        'filename' => $fileName ?: basename($absPath),
        'mimeType' => $mime ?: 'application/octet-stream',
        'content'  => base64_encode($data),
    ], JSON_UNESCAPED_UNICODE);

    try {
        $ch = curl_init(SHEETS_WEBAPP_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 30,   // subir archivos toma más que filas
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($resp === false || $code < 200 || $code >= 300) {
            sheets_log("drive upload http=$code err=" . ($cerr ?: 'http'));
            return null;
        }
        $j = json_decode($resp, true);
        if (!is_array($j) || empty($j['ok']) || empty($j['url'])) {
            sheets_log('drive upload resp=' . substr((string)$resp, 0, 200));
            return null;
        }
        return $j['url'];
    } catch (Throwable $e) {
        sheets_log('drive upload exc=' . $e->getMessage());
        return null;
    }
}

/** Reescribe la pestaña 'Registro_Tallyman' con todos los registros de actividad. Devuelve bool. */
function sheets_sync_tallyman() {
    if (!sheets_enabled()) return false;
    $op = function_exists('conn_operaciones')
        ? conn_operaciones()
        : @mysqli_connect('127.0.0.1', 'root', '', getenv('OPER_DB_NAME') ?: 'portally_operaciones', 3306);
    if (!$op) return false;
    $rows = [];
    $r = mysqli_query($op,
        "SELECT tr.id, tr.fecha_turno, tr.turno, tr.ubicacion_tipo, tr.ubicacion,
                COALESCE(n.nombre, tr.nave_patio, '') AS nave,
                ta.nombre AS actividad,
                tr.status_act, tr.planned, tr.executed, tr.productivity,
                tr.details, tr.registrado_por, tr.fecha_registro
           FROM tallyman_registros tr
           LEFT JOIN tallyman_actividades ta ON ta.id = tr.actividad_id
           LEFT JOIN naves n                 ON n.id  = tr.nave_id
          ORDER BY tr.fecha_turno DESC, tr.id DESC");
    while ($r && $row = mysqli_fetch_assoc($r)) {
        $rows[] = [
            (int)$row['id'],
            $row['fecha_turno'],
            $row['turno'],
            $row['ubicacion_tipo'],
            $row['ubicacion'],
            $row['nave'],
            $row['actividad'],
            $row['status_act'],
            $row['planned'] !== null ? (float)$row['planned'] : '',
            (float)$row['executed'],
            $row['productivity'] !== null ? (float)$row['productivity'] : '',
            $row['details'] ?? '',
            $row['registrado_por'],
            $row['fecha_registro'],
        ];
    }
    mysqli_close($op);
    return sheets_push('Registro_Tallyman', $rows, 'replace', ['header' => [
        'ID', 'Fecha Turno', 'Jornada', 'Tipo', 'Ubicación', 'Nave / Patio',
        'Actividad', 'Status', 'Planned', 'Executed', 'Productividad',
        'Detalles', 'Registrado Por', 'Fecha Registro',
    ]]);
}

/** Reescribe la pestaña 'TALLYMANS' con el catálogo completo (snapshot). Devuelve bool. */
function sheets_sync_colaboradores($conn) {
    if (!sheets_enabled()) return false;
    $rows = [];
    $r = mysqli_query($conn, "SELECT c.codigo, c.nombre, c.funcion_principal, c.tipo_funcion,
                                     c.cuadrilla, c.activo, u.nombre AS coordinador_nombre
                                FROM colaboradores c
                                LEFT JOIN usuarios u ON u.id = c.coordinador_id
                               ORDER BY c.nombre");
    while ($r && $row = mysqli_fetch_assoc($r)) {
        $rows[] = [
            $row['codigo'],
            $row['nombre'],
            $row['funcion_principal'],
            $row['tipo_funcion'] ?? '',
            $row['cuadrilla'],
            $row['coordinador_nombre'] ?? '',
            ((int)$row['activo'] === 1 ? 'Activo' : 'Inactivo'),
        ];
    }
    return sheets_push('TALLYMANS', $rows, 'replace',
        ['header' => ['Código', 'Nombre', 'Puesto', 'Función', 'Team', 'Coordinador', 'Estado']]);
}

/** Duración en minutos entre dos "HH:MM" (cruza medianoche → +24h). null si falta una. */
function _sheets_dur($ini, $fin) {
    if (empty($ini) || empty($fin)) return null;
    $a = explode(':', (string)$ini); $b = explode(':', (string)$fin);
    $am = (int)$a[0] * 60 + (int)($a[1] ?? 0);
    $bm = (int)$b[0] * 60 + (int)($b[1] ?? 0);
    $d = $bm - $am; if ($d < 0) $d += 1440;
    return $d;
}

/**
 * Calcula los agregados de un turno para las hojas de cierre.
 * Devuelve ['resumen' => fila, 'conteo' => [filas...]] o null si el turno no existe.
 * Se reutiliza tanto en el cierre en vivo como en el backfill.
 */
function _sheets_turno_aggregates($conn, $turnoId) {
    $turnoId = (int)$turnoId;
    $tq = mysqli_query($conn,
        "SELECT t.fecha, j.nombre AS jornada, j.hora_inicio, j.hora_fin
           FROM turnos t JOIN jornadas j ON j.id = t.jornada_id
          WHERE t.id = $turnoId LIMIT 1");
    $t = $tq ? mysqli_fetch_assoc($tq) : null;
    if (!$t) return null;

    $fecha = $t['fecha']; $jornada = $t['jornada'];
    $horario = substr($t['hora_inicio'], 0, 5) . '–' . substr($t['hora_fin'], 0, 5);

    // Personal del turno
    $personal = [];
    $pq = mysqli_query($conn,
        "SELECT tp.id, tp.estado, tp.funcion, tp.ubicacion, c.codigo, c.nombre
           FROM turno_personal tp JOIN colaboradores c ON c.id = tp.colaborador_id
          WHERE tp.turno_id = $turnoId ORDER BY c.nombre");
    while ($pq && $p = mysqli_fetch_assoc($pq)) {
        $personal[(int)$p['id']] = [
            'codigo' => $p['codigo'], 'nombre' => $p['nombre'], 'funcion' => $p['funcion'],
            'ubicacion' => $p['ubicacion'], 'estado' => $p['estado'],
            'traslado' => 0, 'refrigerio' => 0, 'permiso' => 0,
            'min_refrigerio' => 0, 'min_permiso' => 0,
        ];
    }

    // Eventos del turno
    $evTot = ['traslado' => 0, 'refrigerio' => 0, 'permiso' => 0];
    $eq = mysqli_query($conn,
        "SELECT e.turno_personal_id, e.tipo, e.hora_inicio, e.hora_fin
           FROM turno_eventos e JOIN turno_personal tp ON tp.id = e.turno_personal_id
          WHERE tp.turno_id = $turnoId");
    while ($eq && $e = mysqli_fetch_assoc($eq)) {
        $tpid = (int)$e['turno_personal_id'];
        if (!isset($personal[$tpid])) continue;
        $tipo = $e['tipo'];
        if (isset($personal[$tpid][$tipo])) $personal[$tpid][$tipo]++;
        if (isset($evTot[$tipo])) $evTot[$tipo]++;
        $dur = _sheets_dur($e['hora_inicio'], $e['hora_fin']);
        if ($dur !== null) {
            if ($tipo === 'refrigerio') $personal[$tpid]['min_refrigerio'] += $dur;
            elseif ($tipo === 'permiso') $personal[$tpid]['min_permiso'] += $dur;
        }
    }

    $total = count($personal); $activos = 0; $refri = 0; $incid = 0;
    foreach ($personal as $p) {
        if ($p['estado'] === 'activo') $activos++;
        elseif ($p['estado'] === 'refrigerio') $refri++;
        elseif ($p['estado'] === 'incidencia') $incid++;
    }
    $uname = $_SESSION['user_name'] ?? '';

    $resumen = [
        $fecha, $jornada, $horario, $total, $activos, $refri, $incid,
        $evTot['traslado'], $evTot['refrigerio'], $evTot['permiso'],
        $uname, date('Y-m-d H:i:s'),
    ];
    $conteo = [];
    foreach ($personal as $p) {
        $conteo[] = [
            $fecha, $jornada, $p['codigo'], $p['nombre'], $p['funcion'], $p['ubicacion'], $p['estado'],
            $p['traslado'], $p['refrigerio'], $p['permiso'], $p['min_refrigerio'], $p['min_permiso'],
        ];
    }
    return ['resumen' => $resumen, 'conteo' => $conteo];
}

/** Encabezados de las hojas de cierre (compartidos por cierre en vivo y backfill). */
function _sheets_resumen_header() {
    return ['Fecha', 'Jornada', 'Horario', 'Total personal', 'Activos', 'En refrigerio',
            'Incidencias', 'Ev. traslado', 'Ev. refrigerio', 'Ev. permiso', 'Cerrado por', 'Cerrado en'];
}
function _sheets_conteo_header() {
    return ['Fecha', 'Jornada', 'Código', 'Colaborador', 'Función', 'Ubicación', 'Estado',
            'Traslados', 'Refrigerios', 'Permisos', 'Min. refrigerio', 'Min. permiso'];
}

/** Al cerrar un turno: upsert de su fila de resumen y de su conteo por persona. */
function sheets_sync_turno_cierre($conn, $turnoId) {
    if (!sheets_enabled()) return;
    $agg = _sheets_turno_aggregates($conn, $turnoId);
    if (!$agg) return;
    sheets_push('Resumen_Turnos', [$agg['resumen']], 'upsert',
        ['header' => _sheets_resumen_header(), 'keyCols' => [1, 2]]);          // key: Fecha+Jornada
    if ($agg['conteo']) {
        sheets_push('Conteo_Personal', $agg['conteo'], 'upsert',
            ['header' => _sheets_conteo_header(), 'keyCols' => [1, 2, 3]]);     // key: Fecha+Jornada+Código
    }
}
