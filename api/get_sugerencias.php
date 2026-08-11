<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Listado de Sugerencias Tallyman (JSON) · Solo Administrador
   ───────────────────────────────────────────────────────────────────────
   El coordinador a cargo se resuelve EN VIVO con LEFT JOIN, no se guarda
   copia en sugerencias_tallyman: el dato responde a «¿de quién es esta
   persona hoy?». El precio es que reasignar un colaborador mueve también
   sus sugerencias pasadas al nuevo coordinador (mismo criterio que
   api/get_incidencias.php).

   Los JOIN son LEFT y no INNER a propósito: las observaciones tienen
   colaborador_id NULL por diseño (anonimato) y un INNER JOIN las borraría
   del listado.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();

header('Content-Type: application/json');

$r = mysqli_query(
    $conn,
    "SELECT s.id, s.canal, s.colaborador_id, s.colaborador_nombre, s.colaborador_cargo,
            s.detalle, s.viabilidad, s.impacto, s.puntaje,
            s.puntaje_comentario, s.puntaje_por, s.puntaje_at,
            s.respuesta_whatsapp, s.respuesta_whatsapp_por, s.respuesta_whatsapp_at,
            col.celular AS colaborador_celular,
            s.created_at, s.updated_at,
            col.coordinador_id AS coord_cargo_id,
            u.nombre           AS coord_cargo_nombre
       FROM sugerencias_tallyman s
       LEFT JOIN colaboradores col ON col.id = s.colaborador_id
       LEFT JOIN usuarios      u   ON u.id   = col.coordinador_id
      ORDER BY s.created_at DESC, s.id DESC"
);

if (!$r) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

$out = [];
while ($row = mysqli_fetch_assoc($r)) {
    $row['id']             = (int)$row['id'];
    $row['colaborador_id'] = $row['colaborador_id'] !== null ? (int)$row['colaborador_id'] : null;
    $row['viabilidad']     = $row['viabilidad']     !== null ? (int)$row['viabilidad']     : null;
    $row['puntaje']        = $row['puntaje']        !== null ? (int)$row['puntaje']        : null;
    $row['coord_cargo_id'] = $row['coord_cargo_id'] !== null ? (int)$row['coord_cargo_id'] : null;
    $row['adjuntos']       = [];
    $out[$row['id']] = $row;
}

// Adjuntos de Drive, agrupados por sugerencia (una sola consulta).
$ra = mysqli_query(
    $conn,
    "SELECT sugerencia_id, nombre_archivo, mime, peso_bytes, drive_url, estado
       FROM sugerencias_adjuntos
       ORDER BY id ASC"
);
if ($ra) {
    while ($a = mysqli_fetch_assoc($ra)) {
        $sid = (int)$a['sugerencia_id'];
        if (!isset($out[$sid])) continue;
        $a['peso_bytes'] = (int)$a['peso_bytes'];
        unset($a['sugerencia_id']);
        $out[$sid]['adjuntos'][] = $a;
    }
}

echo json_encode(['success' => true, 'data' => array_values($out)]);
