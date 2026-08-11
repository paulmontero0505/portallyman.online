<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_tareas();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('Adjunto inválido.'); }

$st = mysqli_prepare($conn,
    "SELECT a.nombre_archivo, a.mime, a.drive_url, a.ruta_local,
            t.asignado_id, t.coordinador_ref_id, t.estado AS tarea_estado,
            u.soporte_de_id AS asignado_soporte_de
       FROM tareas_adjuntos a
       JOIN tareas t         ON t.id = a.tarea_id
       LEFT JOIN usuarios u  ON u.id = t.asignado_id
      WHERE a.id = ? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if (!$row) { http_response_code(404); exit('El adjunto no existe.'); }
if (!tk_puede_ver($row)) {
    http_response_code(403);
    exit('No tienes permiso para ver este archivo.');
}

if (!empty($row['drive_url'])) {
    header('Location: ' . $row['drive_url']);
    exit;
}

if (empty($row['ruta_local'])) { http_response_code(404); exit('El archivo no está disponible.'); }

$ruta = realpath(__DIR__ . '/../' . $row['ruta_local']);
$base = realpath(__DIR__ . '/../uploads/sugerencias');
if ($ruta === false || $base === false || strpos($ruta, $base) !== 0 || !is_file($ruta)) {
    http_response_code(404); exit('El archivo no está disponible.');
}

header('Content-Type: ' . ($row['mime'] ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($ruta));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $row['nombre_archivo']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
