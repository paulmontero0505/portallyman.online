<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Alta / edición de una evaluación EVADES (JSON)
   ───────────────────────────────────────────────────────────────────────
   · Recalcula las sugerencias en el servidor (no confía en lo que mande
     el cliente) para poder exigir motivo cuando el coordinador se aparta
     de la sugerencia en una competencia con automatización.
   · El puntaje total, la clasificación y la variación % se calculan aquí.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/evades_catalogo.php');
require_once('../includes/evades_engine.php');
require_once('../includes/evades_bloques.php');
api_require_report();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']); exit;
}

// Las evaluaciones nuevas se guardan dentro de su bloque y usan versión
// optimista. Se conserva debajo el contrato histórico sin bloque.
if ((int)($data['bloque_id'] ?? 0) > 0) {
    try {
        $guardado = evades_guardar_evaluacion_bloque($conn, $data, (int)($_SESSION['user_id'] ?? 0));
        echo json_encode(['success' => true, 'data' => $guardado], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        $conflicto = strpos($e->getMessage(), 'otra sesión') !== false;
        http_response_code($conflicto ? 409 : 422);
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$id             = isset($data['id']) ? (int)$data['id'] : 0;
$colaboradorId  = isset($data['colaborador_id']) ? (int)$data['colaborador_id'] : 0;
$periodo        = trim($data['periodo'] ?? '');
$fechaEval      = trim($data['fecha_evaluacion'] ?? '');
$filasIn        = is_array($data['competencias'] ?? null) ? $data['competencias'] : [];
$fortalezas     = trim($data['fortalezas'] ?? '');
$aspectosMejora = trim($data['aspectos_mejora'] ?? '');
$planAccion     = trim($data['plan_accion'] ?? '');

if ($colaboradorId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Selecciona el colaborador evaluado.']); exit;
}
$rango = evades_periodo_fechas($periodo);
if (!$rango) {
    echo json_encode(['success' => false, 'error' => 'Período inválido.']); exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEval)) {
    echo json_encode(['success' => false, 'error' => 'Fecha de evaluación inválida.']); exit;
}

// ─── Colaborador: validar cargo + pertenencia al coordinador ───
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, nombre, codigo, funcion_principal, dni, coordinador_id
       FROM colaboradores WHERE id=? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'i', $colaboradorId);
mysqli_stmt_execute($stmt);
$col = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$col) {
    echo json_encode(['success' => false, 'error' => 'Selecciona un colaborador válido.']); exit;
}
if (strtoupper(trim($col['funcion_principal'])) !== 'ASISTENTE DE ESTIBA') {
    echo json_encode(['success' => false, 'error' => 'EVADES solo evalúa a Asistentes de Estiba.']); exit;
}
$rol = $_SESSION['user_rol'] ?? '';
$uid = (int)($_SESSION['user_id'] ?? 0);
if ($rol === 'Coordinador' && (int)($col['coordinador_id'] ?? 0) !== $uid) {
    echo json_encode(['success' => false, 'error' => 'Ese colaborador no está a tu cargo.']); exit;
}

// ─── Recalcular sugerencias en servidor y validar cada fila ───
$sugerido = evades_calcular_sugerencias($conn, $colaboradorId, $periodo);
$sugeridoPorKey = [];
foreach ($sugerido as $s) $sugeridoPorKey[$s['competencia_key']] = $s;

$incValidos  = evades_incrementos_validos();
$descValidos = evades_descuentos_validos();
$catalogo    = evades_competencias();

$porKeyIn = [];
foreach ($filasIn as $f) $porKeyIn[$f['competencia_key'] ?? ''] = $f;

$filasFinal = [];
$puntajeTotal = 0;

foreach ($catalogo as $key => $meta) {
    $in = $porKeyIn[$key] ?? [];
    $incrementoFinal = isset($in['incremento_final']) ? (int)$in['incremento_final'] : 0;
    $descuentoFinal  = isset($in['descuento_final'])  ? (int)$in['descuento_final']  : 0;
    $motivo          = trim($in['motivo_ajuste'] ?? '');

    if (!in_array($incrementoFinal, $incValidos, true)) {
        echo json_encode(['success' => false, 'error' => "Incremento inválido en \"{$meta['label']}\"."]); exit;
    }
    if (!in_array($descuentoFinal, $descValidos, true)) {
        echo json_encode(['success' => false, 'error' => "Descuento inválido en \"{$meta['label']}\"."]); exit;
    }

    $tieneAuto = evades_tiene_automatizacion($key);
    $s = $sugeridoPorKey[$key] ?? null;
    $autoIncremento = $tieneAuto ? (int)($s['auto_incremento'] ?? 0) : null;
    $autoDescuento  = $tieneAuto ? (int)($s['auto_descuento'] ?? 0) : null;

    if ($tieneAuto && ($incrementoFinal !== $autoIncremento || $descuentoFinal !== $autoDescuento)) {
        if ($motivo === '') {
            echo json_encode(['success' => false, 'error' => "\"{$meta['label']}\": ajustaste la sugerencia automática, indica el motivo."]); exit;
        }
    }

    $puntajeFinal = max(0, min(10, 6 + $incrementoFinal - $descuentoFinal));
    $puntajeTotal += $puntajeFinal;

    $filasFinal[] = [
        'competencia_key' => $key,
        'tipo' => $meta['tipo'],
        'auto_incremento' => $autoIncremento,
        'auto_descuento' => $autoDescuento,
        'incremento_final' => $incrementoFinal,
        'descuento_final' => $descuentoFinal,
        'puntaje_final' => $puntajeFinal,
        'motivo_ajuste' => $motivo !== '' ? $motivo : null,
        'evidencia_json' => json_encode(evades_evidencia_para_snapshot($s), JSON_UNESCAPED_UNICODE),
    ];
}

$clasificacion = evades_clasificacion($puntajeTotal);

// ─── Puntaje anterior: la última evaluación EVADES previa de este
//     colaborador (por fecha_evaluacion), o el histórico importado si no
//     hay ninguna evaluación previa en el sistema. ───
$puntajeAnterior = null;
$stmt = mysqli_prepare(
    $conn,
    "SELECT puntaje_total FROM evades_evaluaciones
      WHERE colaborador_id=? AND periodo <> ? AND id <> ?
      ORDER BY fecha_evaluacion DESC, id DESC LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 'isi', $colaboradorId, $periodo, $id);
mysqli_stmt_execute($stmt);
$prevRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if ($prevRow) {
    $puntajeAnterior = (int)$prevRow['puntaje_total'];
} else {
    $stmt = mysqli_prepare($conn, "SELECT puntaje_total FROM evades_historico WHERE colaborador_id=? ORDER BY periodo DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $colaboradorId);
    mysqli_stmt_execute($stmt);
    $histRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($histRow) $puntajeAnterior = (int)$histRow['puntaje_total'];
}
$variacionPct = ($puntajeAnterior !== null && $puntajeAnterior > 0)
    ? round((($puntajeTotal - $puntajeAnterior) / $puntajeAnterior) * 100, 2)
    : null;

$evaluador   = $_SESSION['user_name'] ?? 'Desconocido';
$evaluadorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

mysqli_begin_transaction($conn);
try {
    if ($id > 0) {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE evades_evaluaciones SET
                colaborador_id=?, colaborador_nombre=?, colaborador_codigo=?, colaborador_cargo=?, colaborador_dni=?,
                coordinador_id=?, coordinador_nombre=?, periodo=?, fecha_evaluacion=?,
                puntaje_total=?, clasificacion=?, puntaje_anterior=?, variacion_pct=?,
                fortalezas=?, aspectos_mejora=?, plan_accion=?
              WHERE id=?"
        );
        mysqli_stmt_bind_param(
            $stmt, 'issssisssisidsssi',
            $colaboradorId, $col['nombre'], $col['codigo'], $col['funcion_principal'], $col['dni'],
            $evaluadorId, $evaluador, $periodo, $fechaEval,
            $puntajeTotal, $clasificacion, $puntajeAnterior, $variacionPct,
            $fortalezas, $aspectosMejora, $planAccion, $id
        );
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $del = mysqli_prepare($conn, "DELETE FROM evades_competencias WHERE evaluacion_id=?");
        mysqli_stmt_bind_param($del, 'i', $id);
        if (!mysqli_stmt_execute($del)) throw new Exception(mysqli_stmt_error($del));
        mysqli_stmt_close($del);

        $evaluacionId = $id;
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO evades_evaluaciones
                (colaborador_id, colaborador_nombre, colaborador_codigo, colaborador_cargo, colaborador_dni,
                 coordinador_id, coordinador_nombre, periodo, fecha_evaluacion,
                 puntaje_total, clasificacion, puntaje_anterior, variacion_pct,
                 fortalezas, aspectos_mejora, plan_accion)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        mysqli_stmt_bind_param(
            $stmt, 'issssisssisidsss',
            $colaboradorId, $col['nombre'], $col['codigo'], $col['funcion_principal'], $col['dni'],
            $evaluadorId, $evaluador, $periodo, $fechaEval,
            $puntajeTotal, $clasificacion, $puntajeAnterior, $variacionPct,
            $fortalezas, $aspectosMejora, $planAccion
        );
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        $evaluacionId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
    }

    $insComp = mysqli_prepare(
        $conn,
        "INSERT INTO evades_competencias
            (evaluacion_id, competencia_key, tipo, base, auto_incremento, auto_descuento,
             incremento_final, descuento_final, puntaje_final, motivo_ajuste, evidencia_json)
         VALUES (?,?,?,6,?,?,?,?,?,?,?)"
    );
    foreach ($filasFinal as $f) {
        mysqli_stmt_bind_param(
            $insComp, 'issiiiiiss',
            $evaluacionId, $f['competencia_key'], $f['tipo'],
            $f['auto_incremento'], $f['auto_descuento'],
            $f['incremento_final'], $f['descuento_final'], $f['puntaje_final'],
            $f['motivo_ajuste'], $f['evidencia_json']
        );
        if (!mysqli_stmt_execute($insComp)) throw new Exception(mysqli_stmt_error($insComp));
    }
    mysqli_stmt_close($insComp);

    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    $msg = strpos($e->getMessage(), 'uq_evades_colab_periodo') !== false
        ? 'Ya existe una evaluación EVADES de este colaborador en ese período.'
        : ($e->getMessage() ?: 'No se pudo guardar.');
    echo json_encode(['success' => false, 'error' => $msg]); exit;
}

echo json_encode([
    'success' => true,
    'id' => $evaluacionId,
    'puntaje_total' => $puntajeTotal,
    'clasificacion' => $clasificacion,
    'variacion_pct' => $variacionPct,
]);
