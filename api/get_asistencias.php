<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Listado de Asistencias Pre-Operativas (JSON)
   Devuelve cada reunión con sus participantes (con su estado: asistió /
   falta / tardanza) y sus evidencias.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_report();

header('Content-Type: application/json');

// ¿Se pide una sola asistencia (para ver/editar/exportar)?
$soloId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$where = $soloId > 0 ? "WHERE id=" . $soloId : "";
$r = mysqli_query($conn,
    "SELECT id, tema, tipo_reunion, lugar, capacitador, turno, fecha, hora,
            zona_trabajo, observaciones, coordinador, coordinador_id, created_at, updated_at
       FROM asistencias_preoperativas
       $where
       ORDER BY fecha DESC, id DESC");
if (!$r) { echo json_encode(['success' => false, 'error' => mysqli_error($conn)]); exit; }

$out = [];
while ($row = mysqli_fetch_assoc($r)) {
    $row['id'] = (int)$row['id'];
    $row['coordinador_id'] = $row['coordinador_id'] !== null ? (int)$row['coordinador_id'] : null;
    $row['participantes'] = [];
    $row['evidencias']    = [];
    $out[$row['id']] = $row;
}

if ($out) {
    $ids = implode(',', array_map('intval', array_keys($out)));

    // Participantes, con su estado de asistencia.
    $rp = mysqli_query($conn,
        "SELECT id, asistencia_id, colaborador_id, colaborador_nombre, colaborador_dni,
                colaborador_cargo, estado
           FROM asistencias_participantes
          WHERE asistencia_id IN ($ids)
          ORDER BY id ASC");
    while ($p = mysqli_fetch_assoc($rp)) {
        $aid = (int)$p['asistencia_id'];
        if (!isset($out[$aid])) continue;
        $p['id'] = (int)$p['id'];
        $p['colaborador_id'] = $p['colaborador_id'] !== null ? (int)$p['colaborador_id'] : null;
        unset($p['asistencia_id']);
        $out[$aid]['participantes'][] = $p;
    }

    // Evidencias
    $re = mysqli_query($conn,
        "SELECT id, asistencia_id, nombre_archivo, mime, peso_bytes, drive_file_id, drive_url, estado
           FROM asistencias_evidencias
          WHERE asistencia_id IN ($ids)
          ORDER BY id ASC");
    while ($e = mysqli_fetch_assoc($re)) {
        $aid = (int)$e['asistencia_id'];
        if (!isset($out[$aid])) continue;
        $e['id'] = (int)$e['id'];
        $e['peso_bytes'] = (int)$e['peso_bytes'];
        unset($e['asistencia_id']);
        $out[$aid]['evidencias'][] = $e;
    }
}

echo json_encode(['success' => true, 'data' => array_values($out)]);
