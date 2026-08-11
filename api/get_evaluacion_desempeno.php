<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Listado de evaluaciones de desempeño (JSON)
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_report();

header('Content-Type: application/json');

$r = mysqli_query(
    $conn,
    "SELECT id, colaborador_id, colaborador_nombre, colaborador_cargo,
            actividad, turno, fecha, evaluador, evaluador_id,
            criterios, comentarios_categoria, puntaje_total, comentarios_generales,
            created_at, updated_at
       FROM evaluacion_desempeno
       ORDER BY fecha DESC, id DESC"
);

if (!$r) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

$out = [];
while ($row = mysqli_fetch_assoc($r)) {
    $row['id']                    = (int)$row['id'];
    $row['colaborador_id']        = $row['colaborador_id'] !== null ? (int)$row['colaborador_id'] : null;
    $row['evaluador_id']          = $row['evaluador_id'] !== null ? (int)$row['evaluador_id'] : null;
    $row['puntaje_total']         = (int)$row['puntaje_total'];
    $row['criterios']             = json_decode($row['criterios'], true) ?: [];
    $row['comentarios_categoria'] = json_decode($row['comentarios_categoria'], true) ?: [];
    $out[] = $row;
}

echo json_encode(['success' => true, 'data' => $out]);
