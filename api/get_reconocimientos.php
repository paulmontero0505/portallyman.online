<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Listado de reconocimientos (JSON)
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_report();

header('Content-Type: application/json');

$r = mysqli_query(
    $conn,
    "SELECT id, colaborador_id, colaborador_nombre, colaborador_cargo,
            competencia, concepto, coordinador, coordinador_id,
            turno, fecha, zona_trabajo, detalle,
            estado, supervisor, supervisor_id, aprobado_at,
            foto_path, foto_drive_url,
            created_at, updated_at
       FROM reconocimientos
       ORDER BY fecha DESC, id DESC"
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
    $out[] = $row;
}

echo json_encode(['success' => true, 'data' => $out]);
