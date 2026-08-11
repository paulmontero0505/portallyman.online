<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Listado de Capacitaciones (JSON)
   ───────────────────────────────────────────────────────────────────────
   Devuelve las capacitaciones con sus temas, adjuntos y el conteo de
   asistencia ya anidados, en UN solo viaje.

   Los temas y adjuntos se traen con dos consultas agregadas
   (WHERE capacitacion_id IN (…)), no una por capacitación.

   La plantilla de colaboradores NO viaja aquí: son ~38 filas por
   capacitación y la tabla del listado no las usa. Va en
   get_capacitacion_plantilla.php, que se pide al abrir el detalle.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/capacitaciones_catalogo.php');
api_require_report();

header('Content-Type: application/json');

// ── Cabeceras ───────────────────────────────────────────────────────────
// total_marcados / asistieron / tardanzas / faltas salen de subconsultas:
// una sola pasada, sin traer las filas de asistencia al PHP.
$sql = "SELECT c.id, c.titulo, c.fecha, c.hora, c.duracion_min, c.lugar, c.expositor,
               c.observaciones, c.estado, c.total_plantilla,
               c.coordinador, c.coordinador_id, c.enviado_at,
               c.validado_por, c.validado_por_id, c.validado_at, c.comentario_admin,
               c.created_at, c.updated_at,
               (SELECT COUNT(*) FROM capacitaciones_asistentes a
                 WHERE a.capacitacion_id = c.id)                          AS marcados,
               (SELECT COUNT(*) FROM capacitaciones_asistentes a
                 WHERE a.capacitacion_id = c.id AND a.estado = 'asistio')  AS asistieron,
               (SELECT COUNT(*) FROM capacitaciones_asistentes a
                 WHERE a.capacitacion_id = c.id AND a.estado = 'tardanza') AS tardanzas,
               (SELECT COUNT(*) FROM capacitaciones_asistentes a
                 WHERE a.capacitacion_id = c.id AND a.estado = 'falta')    AS faltas
          FROM capacitaciones c
      ORDER BY c.fecha DESC, c.hora DESC, c.id DESC";

$res = mysqli_query($conn, $sql);
if (!$res) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

$filas = [];
$ids   = [];
while ($row = mysqli_fetch_assoc($res)) {
    $id  = (int)$row['id'];
    $ids[] = $id;

    $row['id']              = $id;
    $row['duracion_min']    = $row['duracion_min'] === null ? null : (int)$row['duracion_min'];
    $row['total_plantilla'] = $row['total_plantilla'] === null ? null : (int)$row['total_plantilla'];
    $row['coordinador_id']  = $row['coordinador_id'] === null ? null : (int)$row['coordinador_id'];
    $row['validado_por_id'] = $row['validado_por_id'] === null ? null : (int)$row['validado_por_id'];
    $row['marcados']        = (int)$row['marcados'];
    $row['asistieron']      = (int)$row['asistieron'];
    $row['tardanzas']       = (int)$row['tardanzas'];
    $row['faltas']          = (int)$row['faltas'];
    $row['temas']           = [];
    $row['adjuntos']        = [];

    $filas[$id] = $row;
}

// ── Plantilla activa: denominador VIVO de las capacitaciones aún abiertas ──
// Las ya enviadas usan su total_plantilla sellado; sin ese sello, dar de alta
// un colaborador movería el % de asistencia de todo el histórico.
$plantillaActiva = 0;
$rp = mysqli_query($conn, "SELECT COUNT(*) AS n FROM colaboradores WHERE activo = 1");
if ($rp && ($r = mysqli_fetch_assoc($rp))) $plantillaActiva = (int)$r['n'];

foreach ($filas as $id => $f) {
    $filas[$id]['plantilla'] = $f['total_plantilla'] !== null
        ? $f['total_plantilla']
        : $plantillaActiva;
}

// ── Temas y adjuntos, anidados ──────────────────────────────────────────
if ($ids) {
    $in = implode(',', array_map('intval', $ids));

    $rt = mysqli_query($conn,
        "SELECT id, capacitacion_id, orden, titulo, descripcion
           FROM capacitaciones_temas
          WHERE capacitacion_id IN ($in)
       ORDER BY capacitacion_id ASC, orden ASC, id ASC");
    while ($t = mysqli_fetch_assoc($rt)) {
        $cid = (int)$t['capacitacion_id'];
        if (!isset($filas[$cid])) continue;
        $filas[$cid]['temas'][] = [
            'id'          => (int)$t['id'],
            'orden'       => (int)$t['orden'],
            'titulo'      => $t['titulo'],
            'descripcion' => $t['descripcion'],
        ];
    }

    $ra = mysqli_query($conn,
        "SELECT id, capacitacion_id, nombre_archivo, mime, peso_bytes,
                drive_file_id, drive_url, ruta_local, estado, error_msg
           FROM capacitaciones_adjuntos
          WHERE capacitacion_id IN ($in)
       ORDER BY capacitacion_id ASC, id ASC");
    while ($a = mysqli_fetch_assoc($ra)) {
        $cid = (int)$a['capacitacion_id'];
        if (!isset($filas[$cid])) continue;
        $filas[$cid]['adjuntos'][] = [
            'id'             => (int)$a['id'],
            'nombre_archivo' => $a['nombre_archivo'],
            'mime'           => $a['mime'],
            'peso_bytes'     => (int)$a['peso_bytes'],
            'drive_file_id'  => $a['drive_file_id'],
            'drive_url'      => $a['drive_url'],
            'ruta_local'     => $a['ruta_local'],
            'estado'         => $a['estado'],
            'error_msg'      => $a['error_msg'],
        ];
    }
}

echo json_encode([
    'success'         => true,
    'data'            => array_values($filas),
    'plantillaActiva' => $plantillaActiva,
]);
