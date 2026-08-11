<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_tareas();

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de tarea inválido.']); exit;
}

$st = mysqli_prepare($conn,
    "SELECT t.*,
            u.soporte_de_id AS asignado_soporte_de,
            u.estado        AS asignado_estado,
            cr.nombre       AS coordinador_ref_nombre
       FROM tareas t
       LEFT JOIN usuarios u  ON u.id  = t.asignado_id
       LEFT JOIN usuarios cr ON cr.id = t.coordinador_ref_id
      WHERE t.id = ? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'La tarea no existe.']); exit;
}
if (!tk_puede_ver($row)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No tienes permiso para ver esta tarea.']); exit;
}

$adjuntos = [];
$ra = mysqli_query($conn,
    "SELECT id, nombre_archivo, mime, peso_bytes, drive_url, ruta_local, estado,
            error_msg, origen, entrega_nro, subido_por, subido_por_id, created_at
       FROM tareas_adjuntos WHERE tarea_id = $id
      ORDER BY entrega_nro ASC, id ASC");
while ($a = mysqli_fetch_assoc($ra)) {
    $a['id']            = (int)$a['id'];
    $a['peso_bytes']    = (int)$a['peso_bytes'];
    $a['entrega_nro']   = (int)$a['entrega_nro'];
    $a['subido_por_id'] = $a['subido_por_id'] !== null ? (int)$a['subido_por_id'] : null;
    $adjuntos[] = $a;
}

$etiquetas = tk_acciones();
$historial = [];
$rh = mysqli_query($conn,
    "SELECT id, accion, usuario_id, usuario_nombre, usuario_rol, detalle, created_at
       FROM tareas_historial WHERE tarea_id = $id ORDER BY id ASC");
while ($h = mysqli_fetch_assoc($rh)) {
    $h['id']            = (int)$h['id'];
    $h['accion_label']  = $etiquetas[$h['accion']] ?? $h['accion'];
    $h['usuario_rol_label'] = tk_rol_label($h['usuario_rol']);
    $historial[] = $h;
}

$ahora = time();
$tarea = array_merge($row, [
    'id'                  => (int)$row['id'],
    'lote_id'             => $row['lote_id'] !== null ? (int)$row['lote_id'] : null,
    'asignado_id'         => $row['asignado_id'] !== null ? (int)$row['asignado_id'] : null,
    'asignado_soporte_de' => $row['asignado_soporte_de'] !== null ? (int)$row['asignado_soporte_de'] : null,
    'asignado_rol_label'  => tk_rol_label($row['asignado_rol']),
    'entregas_count'      => (int)$row['entregas_count'],
    'nota'                => $row['nota'] !== null ? (int)$row['nota'] : null,
    'nota_label'          => tk_nota_label($row['nota']),
    'plazo_vigente'       => tk_plazo_vigente($row),
    'tiene_prorroga'      => tk_plazo_vigente($row) !== $row['fecha_limite'],
    'atrasada'            => tk_esta_atrasada($row, $ahora),
    'dias_atraso'         => tk_dias_atraso($row, $ahora),
    'entregada_tarde'     => tk_entregada_tarde($row),
    'semaforo'            => tk_semaforo($row, $ahora),
    'es_abierta'          => tk_es_abierta($row['estado']),
    'es_terminal'         => tk_es_terminal($row['estado']),
    'adjuntos'            => $adjuntos,
    'historial'           => $historial,
]);

$tarea['permisos'] = [
    'editar'    => tk_puede_editar($row)['ok'],
    'entregar'  => tk_puede_entregar($row)['ok'],
    'revisar'   => tk_puede_revisar($row)['ok'],
    'prorrogar' => tk_puede_prorrogar($row)['ok'],
    'eliminar'  => is_admin(),
];

echo json_encode(['success' => true, 'data' => $tarea]);
