<?php
/* Reporte de puntualidad: cada fila es la apertura de un turno atribuida
   al usuario que lo registró. Las ventanas siguen la regla del tablero:
   Día 06:00–08:00 y Noche 18:00–20:00. */
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();
header('Content-Type: application/json; charset=utf-8');

$todos = ($_GET['todos'] ?? '') === '1';
$desde = trim($_GET['desde'] ?? date('Y-m-d', strtotime('-29 days')));
$hasta = trim($_GET['hasta'] ?? date('Y-m-d'));
$coord = (int)($_GET['coordinador'] ?? 0);
if (!$todos && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) || $desde > $hasta)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'El rango de fechas no es válido.']); exit;
}

$coordinadores = [];
$q = mysqli_query($conn,
    "SELECT u.id, u.nombre, COUNT(c.id) AS tally_count
       FROM usuarios u
       LEFT JOIN colaboradores c ON c.coordinador_id=u.id AND c.activo=1
      WHERE u.rol='Coordinador' AND u.estado='Activo'
      GROUP BY u.id, u.nombre
      ORDER BY u.nombre");
while ($r = mysqli_fetch_assoc($q)) {
    $coordinadores[] = ['id' => (int)$r['id'], 'nombre' => $r['nombre'], 'tally_count' => (int)$r['tally_count']];
}

$sql = "SELECT t.id, t.fecha, t.created_at, t.estado, j.codigo AS jornada_codigo, j.nombre AS jornada,
               u.id AS coordinador_id, u.nombre AS coordinador,
                (SELECT COUNT(*)
                   FROM turno_personal tp
                  WHERE tp.turno_id=t.id) AS tallys_registrados
          FROM turnos t
          JOIN jornadas j ON j.id=t.jornada_id
          JOIN usuarios u ON u.id=t.abierto_por
         WHERE u.rol='Coordinador'";
$paramsFecha = !$todos;
if ($paramsFecha) $sql .= ' AND t.fecha BETWEEN ? AND ?';
$filtraCoordinador = $coord > 0;
if ($filtraCoordinador) $sql .= ' AND u.id=?';
$sql .= ' ORDER BY t.fecha DESC, t.created_at DESC';
$st = mysqli_prepare($conn, $sql);
if ($paramsFecha && $filtraCoordinador) mysqli_stmt_bind_param($st, 'ssi', $desde, $hasta, $coord);
elseif ($paramsFecha) mysqli_stmt_bind_param($st, 'ss', $desde, $hasta);
elseif ($filtraCoordinador) mysqli_stmt_bind_param($st, 'i', $coord);
mysqli_stmt_execute($st);
$res = mysqli_stmt_get_result($st);
$registros = [];
while ($row = mysqli_fetch_assoc($res)) {
    $hora = substr((string)$row['created_at'], 11, 5);
    $min = ((int)substr($hora, 0, 2)) * 60 + (int)substr($hora, 3, 2);
    $noche = strtoupper((string)$row['jornada_codigo']) === 'N';
    $onTime = $noche ? ($min >= 1080 && $min <= 1200) : ($min >= 360 && $min <= 480);
    $row['hora_registro'] = $hora;
    $row['estado_puntualidad'] = $onTime ? 'on' : 'off';
    $row['ventana'] = $noche ? '18:00–20:00' : '06:00–08:00';
    $registros[] = $row;
}
mysqli_stmt_close($st);

$tallysPorTurno = [];
$minutosTurnoPorTurno = [];
$turnoIds = array_map(static fn($r) => (int)$r['id'], $registros);
if ($turnoIds) {
    $idsSql = implode(',', $turnoIds);
    $tallyRes = mysqli_query($conn,
        "SELECT tp.turno_id, c.codigo, c.nombre, tp.funcion AS posicion, c.tipo_funcion, c.cuadrilla,
                tp.ubicacion AS zona,
                CASE WHEN j.hora_fin > j.hora_inicio
                     THEN TIME_TO_SEC(TIMEDIFF(j.hora_fin, j.hora_inicio)) / 60
                     ELSE TIME_TO_SEC(TIMEDIFF(j.hora_fin, j.hora_inicio)) / 60 + 1440
                END AS minutos_turno
           FROM turno_personal tp
            JOIN colaboradores c ON c.id=tp.colaborador_id
           JOIN turnos t ON t.id=tp.turno_id
           JOIN jornadas j ON j.id=t.jornada_id
           WHERE tp.turno_id IN ($idsSql)
           ORDER BY tp.turno_id, c.nombre");
    while ($row = mysqli_fetch_assoc($tallyRes)) {
        $turnoId = (string)$row['turno_id'];
        $row['minutos_turno'] = (int)$row['minutos_turno'];
        if (!isset($tallysPorTurno[$turnoId])) $tallysPorTurno[$turnoId] = [];
        $tallysPorTurno[$turnoId][] = $row;
        $minutosTurnoPorTurno[$turnoId] = ($minutosTurnoPorTurno[$turnoId] ?? 0) + $row['minutos_turno'];
    }
}

foreach ($registros as &$registro) {
    $registro['minutos_turno'] = $minutosTurnoPorTurno[(string)$registro['id']] ?? 0;
}
unset($registro);

echo json_encode([
    'success' => true, 'desde' => $todos ? null : $desde, 'hasta' => $todos ? null : $hasta,
    'coordinadores' => $coordinadores, 'registros' => $registros, 'tallys_por_turno' => $tallysPorTurno
]);
