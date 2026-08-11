<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Reporte de tardanzas en charlas pre-operativas (JSON)
   Devuelve:
     - kpis:      resumen (total tardanzas / asistencias / faltas / charlas)
     - charlas:   una fila por charla con sus contadores de estado
     - tardanzas: detalle de cada colaborador marcado con estado 'tardanza'
   Filtros: desde, hasta (YYYY-MM-DD) y q (búsqueda por nombre/DNI/cargo).
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_report();
header('Content-Type: application/json; charset=utf-8');

$desde = trim($_GET['desde'] ?? date('Y-m-d', strtotime('-29 days')));
$hasta = trim($_GET['hasta'] ?? date('Y-m-d'));
$q     = trim($_GET['q'] ?? '');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) || $desde > $hasta) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'El rango de fechas no es válido.']); exit;
}

/* ── KPI generales: conteo de participantes por estado en el rango ── */
$kpiSql = "SELECT a.estado, COUNT(*) AS total
             FROM asistencias_participantes a
             JOIN asistencias_preoperativas c ON c.id = a.asistencia_id
            WHERE c.fecha BETWEEN ? AND ?";
if ($q !== '') {
    $kpiSql .= " AND (a.colaborador_nombre LIKE ? OR a.colaborador_dni LIKE ? OR a.colaborador_cargo LIKE ?)";
}
$kpiSql .= " GROUP BY a.estado";

$kpiSt = mysqli_prepare($conn, $kpiSql);
if ($q !== '') {
    $like = '%' . $q . '%';
    mysqli_stmt_bind_param($kpiSt, 'sssss', $desde, $hasta, $like, $like, $like);
} else {
    mysqli_stmt_bind_param($kpiSt, 'ss', $desde, $hasta);
}
mysqli_stmt_execute($kpiSt);
$kpiRes = mysqli_stmt_get_result($kpiSt);
$kpis = ['tardanzas' => 0, 'asistio' => 0, 'falta' => 0, 'participantes' => 0, 'charlas' => 0, 'charlas_con_tardanza' => 0];
while ($row = mysqli_fetch_assoc($kpiRes)) {
    $k = $row['estado'];
    if (isset($kpis[$k])) $kpis[$k] = (int)$row['total'];
    $kpis['participantes'] += (int)$row['total'];
}
mysqli_stmt_close($kpiSt);

/* ── Charlas del rango, con contadores por estado de sus participantes ── */
$charlas = [];
$chSql = "SELECT c.id, c.tema, c.tipo_reunion, c.lugar, c.capacitador, c.turno, c.fecha, c.hora,
                 c.coordinador, c.coordinador_id,
                 SUM(a.estado = 'asistio')  AS asistio,
                 SUM(a.estado = 'tardanza') AS tardanzas,
                 SUM(a.estado = 'falta')    AS faltas,
                 COUNT(a.id)                AS total
            FROM asistencias_preoperativas c
            LEFT JOIN asistencias_participantes a ON a.asistencia_id = c.id
           WHERE c.fecha BETWEEN ? AND ?";
if ($q !== '') {
    $chSql .= " AND (a.colaborador_nombre LIKE ? OR a.colaborador_dni LIKE ? OR a.colaborador_cargo LIKE ?)";
}
$chSql .= " GROUP BY c.id, c.tema, c.tipo_reunion, c.lugar, c.capacitador, c.turno, c.fecha, c.hora,
                     c.coordinador, c.coordinador_id
            ORDER BY c.fecha DESC, c.hora DESC, c.id DESC";

$chSt = mysqli_prepare($conn, $chSql);
if ($q !== '') {
    $like = '%' . $q . '%';
    mysqli_stmt_bind_param($chSt, 'sssss', $desde, $hasta, $like, $like, $like);
} else {
    mysqli_stmt_bind_param($chSt, 'ss', $desde, $hasta);
}
mysqli_stmt_execute($chSt);
$chRes = mysqli_stmt_get_result($chSt);
while ($row = mysqli_fetch_assoc($chRes)) {
    $row['id'] = (int)$row['id'];
    $row['coordinador_id'] = $row['coordinador_id'] !== null ? (int)$row['coordinador_id'] : null;
    $row['asistio']     = (int)$row['asistio'];
    $row['tardanzas']   = (int)$row['tardanzas'];
    $row['faltas']      = (int)$row['faltas'];
    $row['total']       = (int)$row['total'];
    $kpis['charlas']++;
    if ($row['tardanzas'] > 0) $kpis['charlas_con_tardanza']++;
    $charlas[] = $row;
}
mysqli_stmt_close($chSt);

/* ── Detalle de colaboradores que llegaron tarde (estado = 'tardanza') ── */
$tardanzas = [];
$tSql = "SELECT a.id, a.colaborador_id, a.colaborador_nombre, a.colaborador_dni, a.colaborador_cargo,
                c.tema, c.tipo_reunion, c.lugar, c.capacitador, c.turno, c.fecha, c.hora,
                c.coordinador, c.coordinador_id
           FROM asistencias_participantes a
           JOIN asistencias_preoperativas c ON c.id = a.asistencia_id
          WHERE a.estado = 'tardanza' AND c.fecha BETWEEN ? AND ?";
if ($q !== '') {
    $tSql .= " AND (a.colaborador_nombre LIKE ? OR a.colaborador_dni LIKE ? OR a.colaborador_cargo LIKE ?)";
}
$tSql .= " ORDER BY c.fecha DESC, c.hora DESC, c.id DESC";

$tSt = mysqli_prepare($conn, $tSql);
if ($q !== '') {
    $like = '%' . $q . '%';
    mysqli_stmt_bind_param($tSt, 'sssss', $desde, $hasta, $like, $like, $like);
} else {
    mysqli_stmt_bind_param($tSt, 'ss', $desde, $hasta);
}
mysqli_stmt_execute($tSt);
$tRes = mysqli_stmt_get_result($tSt);
while ($row = mysqli_fetch_assoc($tRes)) {
    $row['id'] = (int)$row['id'];
    $row['colaborador_id'] = $row['colaborador_id'] !== null ? (int)$row['colaborador_id'] : null;
    $row['coordinador_id'] = $row['coordinador_id'] !== null ? (int)$row['coordinador_id'] : null;
    $tardanzas[] = $row;
}
mysqli_stmt_close($tSt);

echo json_encode([
    'success' => true, 'desde' => $desde, 'hasta' => $hasta,
    'kpis' => $kpis, 'charlas' => $charlas, 'tardanzas' => $tardanzas
]);
