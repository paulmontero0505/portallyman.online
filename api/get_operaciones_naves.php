<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Histórico de naves · resumen mensual (JSON)
   ───────────────────────────────────────────────────────────────────────
   GET ?mes=YYYY-MM  → { success, data:{ mes, desde, hasta, jornadas, naves[] } }

   Lee MySQL directo contra la base de Operaciones en vez de pasar por la API
   Node: esto es una vista agregada de sólo lectura (GROUP BY sobre un mes
   completo) que la API no expone, y hacerlo por ahí obligaría a traer todos
   los registros al cliente para agregarlos en JavaScript.
   api/operaciones_proxy.php ya lee esa base directamente como fallback.

   Todo el cálculo vive en includes/operaciones_naves.php.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/operaciones_naves.php');
api_require_operaciones();

header('Content-Type: application/json; charset=utf-8');

$mes = $_GET['mes'] ?? date('Y-m');

if (!opn_rango_mes($mes)) {
    echo json_encode(['success' => false, 'error' => 'Mes inválido. Formato esperado YYYY-MM.']);
    exit;
}

$oper = conn_operaciones();
if (!$oper) {
    echo json_encode([
        'success' => false,
        'error'   => 'No se pudo conectar con la base de Operaciones (' . OPER_DB_NAME . ').',
    ]);
    exit;
}

$data = opn_resumen_mes($oper, $conn, $mes);
mysqli_close($oper);

if (isset($data['error'])) {
    echo json_encode(['success' => false, 'error' => $data['error']]);
    exit;
}

// KPIs del mes, sobre el mismo conjunto que alimenta la tabla.
$naves = $data['naves'];
$data['kpis'] = [
    'naves'          => count($naves),
    'dias_estadia'   => array_sum(array_column($naves, 'dias_estadia')),
    'turnos'         => array_sum(array_column($naves, 'turnos_trabajados')),
    'tm'             => round(array_sum(array_column($naves, 'ejecutado_mes')), 2),
    'sin_operacion'  => array_sum(array_column($naves, 'turnos_sin_operacion')),
];

// Composición por tipo de nave (días de estadía y número de naves).
$mix = [];
foreach ($naves as $n) {
    $t = $n['nave']['tipo'] ?: 'Sin tipo';
    if (!isset($mix[$t])) $mix[$t] = ['tipo' => $t, 'dias' => 0, 'naves' => 0];
    $mix[$t]['dias']  += $n['dias_estadia'];
    $mix[$t]['naves'] += 1;
}
usort($mix, function ($a, $b) { return $b['dias'] <=> $a['dias']; });
$data['mix'] = array_values($mix);

echo json_encode(['success' => true, 'data' => $data]);
