<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_tareas();

header('Content-Type: application/json');

$where = [tk_filtro_visibilidad('t')];

$estado = $_GET['estado'] ?? '';
if ($estado !== '' && array_key_exists($estado, tk_estados())) {
    $where[] = "t.estado = '" . mysqli_real_escape_string($conn, $estado) . "'";
}

$asignado = isset($_GET['asignado']) ? (int)$_GET['asignado'] : 0;
if ($asignado > 0) $where[] = "t.asignado_id = $asignado";

$mes = $_GET['mes'] ?? '';
if (preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $where[] = "DATE_FORMAT(t.fecha_limite, '%Y-%m') = '" . mysqli_real_escape_string($conn, $mes) . "'";
}

$sql = "SELECT t.*,
               u.soporte_de_id AS asignado_soporte_de,
               u.estado        AS asignado_estado,
               cr.nombre       AS coordinador_ref_nombre
          FROM tareas t
          LEFT JOIN usuarios u  ON u.id  = t.asignado_id
          LEFT JOIN usuarios cr ON cr.id = t.coordinador_ref_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY (t.estado IN ('pendiente','observada')) DESC,
                  COALESCE(t.fecha_limite_2, t.fecha_limite) ASC,
                  t.id DESC";

$r = mysqli_query($conn, $sql);
if (!$r) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

$filas = [];
$ids   = [];
while ($row = mysqli_fetch_assoc($r)) { $filas[] = $row; $ids[] = (int)$row['id']; }

$adjuntos = [];
if (count($ids)) {
    $inList = implode(',', $ids);
    $ra = mysqli_query($conn,
        "SELECT id, tarea_id, nombre_archivo, mime, peso_bytes, drive_url, ruta_local,
                estado, origen, entrega_nro, subido_por, subido_por_id, created_at
           FROM tareas_adjuntos
          WHERE tarea_id IN ($inList)
          ORDER BY entrega_nro ASC, id ASC");
    while ($a = mysqli_fetch_assoc($ra)) {
        $tid = (int)$a['tarea_id'];
        $a['id']            = (int)$a['id'];
        $a['peso_bytes']    = (int)$a['peso_bytes'];
        $a['entrega_nro']   = (int)$a['entrega_nro'];
        $a['subido_por_id'] = $a['subido_por_id'] !== null ? (int)$a['subido_por_id'] : null;
        if (!isset($adjuntos[$tid])) $adjuntos[$tid] = [];
        $adjuntos[$tid][] = $a;
    }
}

$ahora     = time();
$soloAtras = !empty($_GET['atrasadas']);
$out       = [];

foreach ($filas as $row) {
    $atrasada = tk_esta_atrasada($row, $ahora);
    if ($soloAtras && !$atrasada) continue;

    $tid = (int)$row['id'];
    $out[] = array_merge($row, [
        'id'                  => $tid,
        'lote_id'             => $row['lote_id'] !== null ? (int)$row['lote_id'] : null,
        'asignado_id'         => $row['asignado_id'] !== null ? (int)$row['asignado_id'] : null,
        'asignado_soporte_de' => $row['asignado_soporte_de'] !== null ? (int)$row['asignado_soporte_de'] : null,
        'asignado_rol_label'  => tk_rol_label($row['asignado_rol']),
        'entregas_count'      => (int)$row['entregas_count'],
        'nota'                => $row['nota'] !== null ? (int)$row['nota'] : null,
        'nota_label'          => tk_nota_label($row['nota']),
        'plazo_vigente'       => tk_plazo_vigente($row),
        'tiene_prorroga'      => tk_plazo_vigente($row) !== $row['fecha_limite'],
        'atrasada'            => $atrasada,
        'dias_atraso'         => tk_dias_atraso($row, $ahora),
        'entregada_tarde'     => tk_entregada_tarde($row),
        'semaforo'            => tk_semaforo($row, $ahora),
        'es_abierta'          => tk_es_abierta($row['estado']),
        'es_terminal'         => tk_es_terminal($row['estado']),
        'adjuntos'            => $adjuntos[$tid] ?? [],
    ]);
}

echo json_encode(['success' => true, 'data' => $out, 'ahora' => date('Y-m-d H:i:s', $ahora)]);
