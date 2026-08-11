<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Listado de incidencias (JSON)
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_report();

header('Content-Type: application/json');

// Ojo con los dos coordinadores, que son datos distintos:
//   · coordinador / coordinador_id → quién REGISTRÓ la incidencia (usuario de
//     la sesión, congelado al guardar).
//   · coord_cargo_* → quién está A CARGO del colaborador hoy, según
//     colaboradores.coordinador_id. Se resuelve en vivo con el JOIN, no se
//     congela: así las incidencias ya registradas reflejan la asignación
//     actual del colaborador.
$r = mysqli_query(
    $conn,
    "SELECT i.id, i.colaborador_id, i.colaborador_nombre, i.colaborador_cargo,
            i.punto_mejorar, i.competencia, i.impacto, i.coordinador, i.coordinador_id,
            i.turno, i.fecha, i.zona_trabajo, i.detalle,
            i.foto_path, i.foto_drive_url, i.declaracion_path, i.declaracion_drive_url,
            i.declaracion_uploaded_at, i.created_at, i.updated_at,
            col.coordinador_id AS coord_cargo_id,
            u.nombre           AS coord_cargo_nombre
       FROM incidencias i
       LEFT JOIN colaboradores col ON col.id = i.colaborador_id
       LEFT JOIN usuarios      u   ON u.id  = col.coordinador_id
       ORDER BY i.fecha DESC, i.id DESC"
);

if (!$r) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

$out = [];
while ($row = mysqli_fetch_assoc($r)) {
    $row['id']             = (int)$row['id'];
    $row['colaborador_id'] = $row['colaborador_id'] !== null ? (int)$row['colaborador_id'] : null;
    $row['coordinador_id'] = $row['coordinador_id'] !== null ? (int)$row['coordinador_id'] : null;
    $row['coord_cargo_id'] = $row['coord_cargo_id'] !== null ? (int)$row['coord_cargo_id'] : null;
    $out[] = $row;
}

echo json_encode(['success' => true, 'data' => $out]);
