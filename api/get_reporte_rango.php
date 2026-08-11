<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Reporte consolidado por rango de fechas
   GET ?desde=YYYY-MM-DD&hasta=YYYY-MM-DD
   Devuelve turnos del rango con agregados por turno, por persona y la
   bitácora consolidada. Las duraciones se calculan solo para eventos con
   hora de fin (si fin < inicio, cruza medianoche → +24h).
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_login();

header('Content-Type: application/json');

$desde = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$re = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($re, $desde) || !preg_match($re, $hasta)) {
    echo json_encode(['success' => false, 'error' => 'Fechas inválidas (YYYY-MM-DD).']); exit;
}
if ($desde > $hasta) { $tmp = $desde; $desde = $hasta; $hasta = $tmp; }

function _hm($t) { if ($t === null || $t === '') return null; $p = explode(':', $t); return (int)$p[0] * 60 + (int)($p[1] ?? 0); }
function _dur($ini, $fin) {
    $a = _hm($ini); $b = _hm($fin);
    if ($a === null || $b === null) return null;
    $d = $b - $a; if ($d < 0) $d += 1440;
    return $d;
}

// ── Turnos del rango + su personal (LEFT JOIN para incluir turnos vacíos) ──
$turnos = [];   // turno_id → fila
$tpInfo = [];   // tp_id → { codigo, nombre, funcion, ubicacion, turno_id }

$stmt = mysqli_prepare(
    $conn,
    "SELECT t.id AS turno_id, t.fecha, j.nombre AS jornada, j.hora_inicio, j.hora_fin,
            tp.id AS tp_id, tp.estado, tp.funcion, tp.ubicacion,
            c.codigo, c.nombre
       FROM turnos t
       JOIN jornadas j ON j.id = t.jornada_id
       LEFT JOIN turno_personal tp ON tp.turno_id = t.id
       LEFT JOIN colaboradores c ON c.id = tp.colaborador_id
      WHERE t.fecha BETWEEN ? AND ?
      ORDER BY t.fecha, j.orden, c.nombre"
);
mysqli_stmt_bind_param($stmt, 'ss', $desde, $hasta);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $tid = (int)$row['turno_id'];
    if (!isset($turnos[$tid])) {
        $turnos[$tid] = [
            'turnoId'       => $tid,
            'fecha'         => $row['fecha'],
            'jornada'       => $row['jornada'],
            'horaInicio'    => substr($row['hora_inicio'], 0, 5),
            'horaFin'       => substr($row['hora_fin'], 0, 5),
            'totalPersonal' => 0,
            'activos'       => 0,
            'refrigerio'    => 0,
            'incidencia'    => 0,
            'eventos'       => ['traslado' => 0, 'refrigerio' => 0, 'permiso' => 0],
        ];
    }
    if ($row['tp_id'] !== null) {
        $turnos[$tid]['totalPersonal']++;
        if ($row['estado'] === 'activo') $turnos[$tid]['activos']++;
        elseif ($row['estado'] === 'refrigerio') $turnos[$tid]['refrigerio']++;
        elseif ($row['estado'] === 'incidencia') $turnos[$tid]['incidencia']++;
        $tpInfo[(int)$row['tp_id']] = [
            'turno_id'  => $tid,
            'codigo'    => $row['codigo'],
            'nombre'    => $row['nombre'],
            'funcion'   => $row['funcion'],
            'ubicacion' => $row['ubicacion'],
        ];
    }
}
mysqli_stmt_close($stmt);

// ── Eventos del rango ──
$bitacora = [];
$personas = [];   // codigo → agregado

$stmt = mysqli_prepare(
    $conn,
    "SELECT t.fecha, j.nombre AS jornada,
            tp.funcion, tp.ubicacion, c.codigo, c.nombre,
            e.tipo, e.hora_inicio, e.hora_fin, e.observaciones
       FROM turno_eventos e
       JOIN turno_personal tp ON tp.id = e.turno_personal_id
       JOIN turnos t ON t.id = tp.turno_id
       JOIN jornadas j ON j.id = t.jornada_id
       JOIN colaboradores c ON c.id = tp.colaborador_id
      WHERE t.fecha BETWEEN ? AND ?
      ORDER BY t.fecha, j.orden, e.hora_inicio, e.id"
);
mysqli_stmt_bind_param($stmt, 'ss', $desde, $hasta);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $ini = $row['hora_inicio'] !== null ? substr($row['hora_inicio'], 0, 5) : '';
    $fin = $row['hora_fin'] !== null ? substr($row['hora_fin'], 0, 5) : '';
    $dur = _dur($ini, $fin);

    $bitacora[] = [
        'fecha'         => $row['fecha'],
        'jornada'       => $row['jornada'],
        'codigo'        => $row['codigo'],
        'nombre'        => $row['nombre'],
        'funcion'       => $row['funcion'],
        'ubicacion'     => $row['ubicacion'],
        'tipo'          => $row['tipo'],
        'horaInicio'    => $ini,
        'horaFin'       => $fin,
        'duracionMin'   => $dur,
        'observaciones' => $row['observaciones'] ?? '',
    ];
}
mysqli_stmt_close($stmt);

// Conteo de eventos por turno (recorre la bitácora ya cargada).
// Para mapear cada evento a su turno usamos (fecha, jornada) → turno.
$turnoPorClave = [];
foreach ($turnos as $t) { $turnoPorClave[$t['fecha'] . '|' . $t['jornada']] = $t['turnoId']; }
foreach ($bitacora as $b) {
    $tid = $turnoPorClave[$b['fecha'] . '|' . $b['jornada']] ?? null;
    if ($tid !== null && isset($turnos[$tid]['eventos'][$b['tipo']])) {
        $turnos[$tid]['eventos'][$b['tipo']]++;
    }
    // Agregado por persona
    $cod = $b['codigo'];
    if (!isset($personas[$cod])) {
        $personas[$cod] = ['codigo' => $cod, 'nombre' => $b['nombre'], 'traslado' => 0, 'refrigerio' => 0, 'permiso' => 0, 'minutosEventos' => 0, '_turnos' => []];
    }
    if (isset($personas[$cod][$b['tipo']])) $personas[$cod][$b['tipo']]++;
    if ($b['duracionMin'] !== null) $personas[$cod]['minutosEventos'] += $b['duracionMin'];
    $personas[$cod]['_turnos'][$b['fecha'] . '|' . $b['jornada']] = true;
}

// Turnos trabajados por persona = también desde tpInfo (incluye sin eventos).
foreach ($tpInfo as $tp) {
    $cod = $tp['codigo'];
    if (!isset($personas[$cod])) {
        $personas[$cod] = ['codigo' => $cod, 'nombre' => $tp['nombre'], 'traslado' => 0, 'refrigerio' => 0, 'permiso' => 0, 'minutosEventos' => 0, '_turnos' => []];
    }
    $personas[$cod]['_turnos'][$tp['turno_id']] = true;
}

$personasOut = [];
foreach ($personas as $p) {
    $p['turnos'] = count($p['_turnos']);
    unset($p['_turnos']);
    $personasOut[] = $p;
}
usort($personasOut, fn($a, $b) => strcmp($a['nombre'], $b['nombre']));

$turnosOut = array_values($turnos);

// ── Acciones (auditoría) del rango ──
$acciones = [];
$stmt = mysqli_prepare(
    $conn,
    "SELECT t.fecha, j.nombre AS jornada, TIME(a.created_at) AS hora,
            a.usuario_nombre, a.usuario_rol, a.tipo, a.colaborador_codigo, a.colaborador_nombre, a.detalle
       FROM turno_acciones a
       JOIN turnos t   ON t.id = a.turno_id
       JOIN jornadas j ON j.id = t.jornada_id
      WHERE t.fecha BETWEEN ? AND ?
      ORDER BY t.fecha, j.orden, a.created_at, a.id"
);
mysqli_stmt_bind_param($stmt, 'ss', $desde, $hasta);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $acciones[] = [
        'fecha'       => $row['fecha'],
        'jornada'     => $row['jornada'],
        'hora'        => substr($row['hora'], 0, 5),
        'usuario'     => $row['usuario_nombre'],
        'rol'         => $row['usuario_rol'],
        'tipo'        => $row['tipo'],
        'codigo'      => $row['colaborador_codigo'],
        'colaborador' => $row['colaborador_nombre'],
        'detalle'     => $row['detalle'],
    ];
}
mysqli_stmt_close($stmt);

echo json_encode([
    'success'  => true,
    'desde'    => $desde,
    'hasta'    => $hasta,
    'turnos'   => $turnosOut,
    'personas' => $personasOut,
    'bitacora' => $bitacora,
    'acciones' => $acciones,
]);
