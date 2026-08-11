<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_indicadores();

header('Content-Type: application/json');

$r = mysqli_query($conn, "SELECT gestion_codigo, periodo, team FROM indicadores_cronograma ORDER BY periodo, gestion_codigo");

if (!$r) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

$out = [];
while ($row = mysqli_fetch_assoc($r)) $out[] = $row;

echo json_encode(['success' => true, 'data' => $out]);
