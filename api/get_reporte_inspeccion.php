<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Listado de reportes de inspección (JSON)
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_report();

header('Content-Type: application/json');

$r = mysqli_query(
    $conn,
    "SELECT id, tally_id, tally_nombre, tally_cargo,
            zona_trabajo, area_involucrada, fecha, inspector, inspector_id,
            criterios, medidas_tomar, recomendaciones,
            accion_opciones, accion_comentario, accion_por, accion_por_id, accion_fecha,
            created_at, updated_at
       FROM reporte_inspeccion
       ORDER BY fecha DESC, id DESC"
);

if (!$r) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

$out = [];
while ($row = mysqli_fetch_assoc($r)) {
    $row['id']              = (int)$row['id'];
    $row['tally_id']        = $row['tally_id'] !== null ? (int)$row['tally_id'] : null;
    $row['inspector_id']    = $row['inspector_id'] !== null ? (int)$row['inspector_id'] : null;
    $row['criterios']       = json_decode($row['criterios'], true) ?: [];
    // Acción correctiva: la respuesta del coordinador al reporte.
    $row['accion_por_id']   = $row['accion_por_id'] !== null ? (int)$row['accion_por_id'] : null;
    $row['accion_opciones'] = json_decode((string)$row['accion_opciones'], true) ?: [];
    $row['accion_evidencias'] = [];
    $out[$row['id']] = $row;
}

// Evidencias de la acción correctiva, en una sola consulta para todos.
if ($out) {
    $ids = implode(',', array_map('intval', array_keys($out)));
    $re = mysqli_query($conn,
        "SELECT id, reporte_id, nombre_archivo, mime, peso_bytes, drive_file_id, drive_url, estado
           FROM reporte_inspeccion_evidencias
          WHERE reporte_id IN ($ids)
          ORDER BY id ASC");
    while ($re && $e = mysqli_fetch_assoc($re)) {
        $rid = (int)$e['reporte_id'];
        if (!isset($out[$rid])) continue;
        $e['id']         = (int)$e['id'];
        $e['peso_bytes'] = (int)$e['peso_bytes'];
        unset($e['reporte_id']);
        $out[$rid]['accion_evidencias'][] = $e;
    }
}

echo json_encode(['success' => true, 'data' => array_values($out)]);
