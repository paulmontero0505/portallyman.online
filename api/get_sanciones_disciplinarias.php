<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_report();
header('Content-Type: application/json');

$r = mysqli_query($conn, 'SELECT s.*, i.declaracion_path AS incidencia_declaracion_path FROM sanciones_disciplinarias s LEFT JOIN incidencias i ON i.id=s.incidencia_id ORDER BY s.fecha_incidencia DESC,s.id DESC');
if (!$r) { echo json_encode(['success' => false, 'error' => mysqli_error($conn)]); exit; }
$out = [];
while ($row = mysqli_fetch_assoc($r)) {
  $row['id'] = (int)$row['id'];
  $row['incidencia_id'] = $row['incidencia_id'] !== null ? (int)$row['incidencia_id'] : null;
  $row['colaborador_id'] = $row['colaborador_id'] !== null ? (int)$row['colaborador_id'] : null;
  $out[] = $row;
}
echo json_encode(['success' => true, 'data' => $out]);
