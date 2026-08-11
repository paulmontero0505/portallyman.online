<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Módulo Indicadores — motor de auto-fill
   ───────────────────────────────────────────────────────────────────────
   Un provider por indicador con fuente_automatica. Mismo patrón que
   includes/evades_evidence.php: cada función consulta las tablas fuente,
   agrupa por colaboradores.cuadrilla normalizada, y devuelve
   {numerador, denominador, fuente, n_registros}. $team = null significa
   "toda la planta" (para denominadores tipo "Personal asistente de estiba").
   ═══════════════════════════════════════════════════════════════════════ */

require_once(__DIR__ . '/indicadores_catalogo.php');

function ind_resultado_normalizado($numerador, $denominador, $fuente, $nRegistros) {
    return [
        'numerador' => $numerador,
        'denominador' => $denominador,
        'fuente' => $fuente,
        'n_registros' => $nRegistros,
    ];
}

/** Personal activo con funcion_principal = Asistente de Estiba, opcionalmente filtrado por team. */
function ind_conteo_asistentes_estiba($conn, $team = null) {
    $r = mysqli_query($conn, "SELECT cuadrilla FROM colaboradores WHERE activo=1 AND funcion_principal='ASISTENTE DE ESTIBA'");
    $n = 0;
    while ($row = mysqli_fetch_assoc($r)) {
        if ($team === null || ind_team_normalizado($row['cuadrilla']) === $team) $n++;
    }
    return $n;
}

/** Team (TEAM A..D) a cargo de un usuario Coordinador, derivado de sus colaboradores. Null si no hay ninguno o son mixtos. */
function ind_team_de_coordinador($conn, $coordinadorId) {
    $stmt = mysqli_prepare($conn, "SELECT cuadrilla FROM colaboradores WHERE coordinador_id=? AND activo=1");
    mysqli_stmt_bind_param($stmt, 'i', $coordinadorId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $teams = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $t = ind_team_normalizado($row['cuadrilla']);
        if ($t !== null) $teams[$t] = true;
    }
    mysqli_stmt_close($stmt);
    if (count($teams) !== 1) return null;
    return array_key_first($teams);
}

// ── G1.1 · % charlas pre-operativas (solo numerador) ────────────────
// El KPI cuenta CHARLAS EJECUTADAS, no asistentes. Una charla reune a varias
// personas, asi que contar filas de asistencias_participantes multiplicaba el
// numerador por el tamaño de la lista de asistencia. Se cuentan charlas
// distintas, y una charla pertenece al team si al menos un participante suyo
// es de ese team (una misma charla puede sumar a dos teams si fue mixta, que
// es lo correcto: se ejecuto para ambos).
function ind_auto_g11($conn, $periodo, $team) {
    if ($team === null) return null;
    $rango = ind_periodo_fechas($periodo);
    if ($rango === null) return null;
    $stmt = mysqli_prepare($conn,
        "SELECT a.id, c.cuadrilla FROM asistencias_participantes ap
           INNER JOIN asistencias_preoperativas a ON a.id = ap.asistencia_id
           LEFT JOIN colaboradores c ON c.id = ap.colaborador_id
          WHERE a.fecha BETWEEN ? AND ?"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $charlas = [];
    while ($row = mysqli_fetch_assoc($res)) {
        if (ind_team_normalizado($row['cuadrilla']) === $team) $charlas[(int)$row['id']] = true;
    }
    mysqli_stmt_close($stmt);
    $n = count($charlas);
    return ind_resultado_normalizado((float)$n, null, 'asistencias_preoperativas', $n);
}

// ── G1.4 · Índice de reincidencia grupal ─────────────────────────────
function ind_auto_g14($conn, $periodo, $team) {
    if ($team === null) return null;
    $rango = ind_periodo_fechas($periodo);
    if ($rango === null) return null;
    $stmt = mysqli_prepare($conn,
        "SELECT i.punto_mejorar, c.cuadrilla FROM incidencias i
           LEFT JOIN colaboradores c ON c.id = i.colaborador_id
          WHERE i.fecha BETWEEN ? AND ?"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $porTipo = [];
    $total = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        if (ind_team_normalizado($row['cuadrilla']) !== $team) continue;
        $total++;
        $porTipo[$row['punto_mejorar']] = ($porTipo[$row['punto_mejorar']] ?? 0) + 1;
    }
    mysqli_stmt_close($stmt);
    $reincidentes = 0;
    foreach ($porTipo as $cnt) if ($cnt >= 2) $reincidentes += $cnt;
    return ind_resultado_normalizado((float)$reincidentes, (float)$total, 'incidencias', $total);
}

// ── G2.1 · EVADES dentro de plazo (trimestral) ───────────────────────
function ind_auto_g21($conn, $periodo, $team) {
    if ($team === null) return null;
    $trimestre = ind_trimestre_de_periodo($periodo);
    if ($trimestre === null) return null;
    $stmt = mysqli_prepare($conn,
        "SELECT c.cuadrilla FROM evades_evaluaciones e
           LEFT JOIN colaboradores c ON c.id = e.colaborador_id
          WHERE e.periodo = ?"
    );
    mysqli_stmt_bind_param($stmt, 's', $trimestre);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $n = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        if (ind_team_normalizado($row['cuadrilla']) === $team) $n++;
    }
    mysqli_stmt_close($stmt);
    $denominador = ind_conteo_asistentes_estiba($conn, $team);
    return ind_resultado_normalizado((float)$n, (float)$denominador, 'evades_evaluaciones', $n);
}

// ── G2.2 · % cumplimiento de capacitaciones (denominador fijo = 4) ───
function ind_auto_g22($conn, $periodo, $team) {
    // Capacitaciones no tiene columna de team: es un indicador General (planta completa),
    // se calcula una sola vez y se muestra igual en los 4 teams (como TEAM A en Datos Mensuales
    // del Excel original, donde los indicadores "General" solo llenan una columna).
    if ($team !== null && $team !== 'TEAM A') return ind_resultado_normalizado(null, null, 'capacitaciones', 0);
    $rango = ind_periodo_fechas($periodo);
    if ($rango === null) return null;
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM capacitaciones WHERE estado='realizada' AND fecha BETWEEN ? AND ?");
    mysqli_stmt_bind_param($stmt, 'ss', $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $n = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['n'];
    mysqli_stmt_close($stmt);
    return ind_resultado_normalizado((float)$n, 4.0, 'capacitaciones', $n);
}

// ── G2.3 · Tiempo de respuesta de incidencias (Promedio, sin team) ───
function ind_auto_g23($conn, $periodo, $team) {
    if ($team === null) return null;
    $rango = ind_periodo_fechas($periodo);
    if ($rango === null) return null;
    $stmt = mysqli_prepare($conn,
        "SELECT i.created_at, i.declaracion_uploaded_at, c.cuadrilla FROM incidencias i
           LEFT JOIN colaboradores c ON c.id = i.colaborador_id
          WHERE i.fecha BETWEEN ? AND ? AND i.declaracion_uploaded_at IS NOT NULL"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $dias = [];
    while ($row = mysqli_fetch_assoc($res)) {
        if (ind_team_normalizado($row['cuadrilla']) !== $team) continue;
        $d1 = new DateTime(date('Y-m-d', strtotime($row['created_at'])));
        $d2 = new DateTime(date('Y-m-d', strtotime($row['declaracion_uploaded_at'])));
        $dias[] = (int)$d1->diff($d2)->days;
    }
    mysqli_stmt_close($stmt);
    if (empty($dias)) return ind_resultado_normalizado(null, null, 'incidencias', 0);
    $promedio = array_sum($dias) / count($dias);
    return ind_resultado_normalizado($promedio, null, 'incidencias', count($dias));
}

// ── G2.5 · EPT realizadas al mes ─────────────────────────────────────
function ind_auto_g25($conn, $periodo, $team) {
    if ($team === null) return null;
    $rango = ind_periodo_fechas($periodo);
    if ($rango === null) return null;
    $stmt = mysqli_prepare($conn,
        "SELECT c.cuadrilla FROM evaluacion_desempeno e
           LEFT JOIN colaboradores c ON c.id = e.colaborador_id
          WHERE e.fecha BETWEEN ? AND ?"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $n = 0;
    while ($row = mysqli_fetch_assoc($res)) {
        if (ind_team_normalizado($row['cuadrilla']) === $team) $n++;
    }
    mysqli_stmt_close($stmt);
    return ind_resultado_normalizado((float)$n, null, 'evaluacion_desempeno', $n);
}

/** Trae los reportes de inspección del team+mes ya parseados (uso interno de g31/g32/g33). */
function ind_reportes_inspeccion_mes($conn, $periodo, $team) {
    $rango = ind_periodo_fechas($periodo);
    if ($rango === null) return null;
    $stmt = mysqli_prepare($conn,
        "SELECT r.criterios, r.accion_fecha, c.cuadrilla FROM reporte_inspeccion r
           LEFT JOIN colaboradores c ON c.id = r.tally_id
          WHERE r.fecha BETWEEN ? AND ?"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) {
        if (ind_team_normalizado($row['cuadrilla']) !== $team) continue;
        $out[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $out;
}

// ── G3.1 · N° de reportes de inspección ──────────────────────────────
function ind_auto_g31($conn, $periodo, $team) {
    if ($team === null) return null;
    $reportes = ind_reportes_inspeccion_mes($conn, $periodo, $team);
    if ($reportes === null) return null;
    return ind_resultado_normalizado((float)count($reportes), null, 'reporte_inspeccion', count($reportes));
}

// ── G3.2 · % acciones correctivas implementadas ──────────────────────
// La formula es "acciones correctivas / incidentes detectados": ambos lados
// tienen que salir de la MISMA poblacion, los reportes con hallazgo. Contar en
// el numerador cualquier reporte con accion_fecha (incluidos los que salieron
// conformes) mezclaba dos subconjuntos distintos y podia dar mas de 100%, o
// incluso un numerador positivo con denominador 0.
function ind_auto_g32($conn, $periodo, $team) {
    if ($team === null) return null;
    $reportes = ind_reportes_inspeccion_mes($conn, $periodo, $team);
    if ($reportes === null) return null;
    $conAccion = 0; $conHallazgo = 0;
    foreach ($reportes as $r) {
        $criterios = json_decode($r['criterios'], true);
        if (!is_array($criterios)) continue;
        $tieneHallazgo = false;
        foreach ($criterios as $c) {
            if (($c['estado'] ?? '') === 'no_conforme') { $tieneHallazgo = true; break; }
        }
        if (!$tieneHallazgo) continue;
        $conHallazgo++;
        if (!empty($r['accion_fecha'])) $conAccion++;
    }
    return ind_resultado_normalizado((float)$conAccion, (float)$conHallazgo, 'reporte_inspeccion', count($reportes));
}

// ── G3.3 · % incumplimiento uso de EPP en inspecciones ───────────────
function ind_auto_g33($conn, $periodo, $team) {
    if ($team === null) return null;
    $reportes = ind_reportes_inspeccion_mes($conn, $periodo, $team);
    if ($reportes === null) return null;
    $eppIncompleto = 0;
    foreach ($reportes as $r) {
        $criterios = json_decode($r['criterios'], true);
        if (!is_array($criterios)) continue;
        foreach ($criterios as $c) {
            if (($c['item'] ?? '') === 'Uso de Epps en la zona' && ($c['estado'] ?? '') === 'no_conforme') {
                $eppIncompleto++;
                break;
            }
        }
    }
    return ind_resultado_normalizado((float)$eppIncompleto, (float)count($reportes), 'reporte_inspeccion', count($reportes));
}

/** Propuestas del team+mes (uso interno de g41/g42). */
function ind_propuestas_mes($conn, $periodo, $team) {
    $rango = ind_periodo_fechas($periodo);
    if ($rango === null) return null;
    $inicio = $rango['inicio'] . ' 00:00:00';
    $fin = $rango['fin'] . ' 23:59:59';
    $stmt = mysqli_prepare($conn,
        "SELECT s.puntaje_at, c.cuadrilla FROM sugerencias_tallyman s
           LEFT JOIN colaboradores c ON c.id = s.colaborador_id
          WHERE s.canal='propuesta' AND s.created_at BETWEEN ? AND ?"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $inicio, $fin);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $out = [];
    while ($row = mysqli_fetch_assoc($res)) {
        if (ind_team_normalizado($row['cuadrilla']) !== $team) continue;
        $out[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $out;
}

// ── G4.1 · % de participación en propuestas ──────────────────────────
function ind_auto_g41($conn, $periodo, $team) {
    if ($team === null) return null;
    $propuestas = ind_propuestas_mes($conn, $periodo, $team);
    if ($propuestas === null) return null;
    $denominador = ind_conteo_asistentes_estiba($conn, $team);
    return ind_resultado_normalizado((float)count($propuestas), (float)$denominador, 'sugerencias_tallyman', count($propuestas));
}

// ── G4.2 · % propuestas analizadas ───────────────────────────────────
function ind_auto_g42($conn, $periodo, $team) {
    if ($team === null) return null;
    $propuestas = ind_propuestas_mes($conn, $periodo, $team);
    if ($propuestas === null) return null;
    $analizadas = 0;
    foreach ($propuestas as $p) if (!empty($p['puntaje_at'])) $analizadas++;
    return ind_resultado_normalizado((float)$analizadas, (float)count($propuestas), 'sugerencias_tallyman', count($propuestas));
}

/** Mapa código de indicador -> nombre de la función provider. */
function ind_providers() {
    return [
        'g11' => 'ind_auto_g11',
        'g14' => 'ind_auto_g14',
        'g21' => 'ind_auto_g21',
        'g22' => 'ind_auto_g22',
        'g23' => 'ind_auto_g23',
        'g25' => 'ind_auto_g25',
        'g31' => 'ind_auto_g31',
        'g32' => 'ind_auto_g32',
        'g33' => 'ind_auto_g33',
        'g41' => 'ind_auto_g41',
        'g42' => 'ind_auto_g42',
    ];
}

/**
 * Dispatcher: calcula el numerador/denominador automático de un indicador.
 * Devuelve null si el código no existe, no tiene fuente_automatica, o el team no es resoluble.
 */
function ind_calcular_automatico($conn, $codigo, $periodo, $team) {
    $stmt = mysqli_prepare($conn, "SELECT fuente_automatica FROM indicadores_catalogo WHERE codigo=?");
    mysqli_stmt_bind_param($stmt, 's', $codigo);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row || empty($row['fuente_automatica'])) return null;

    $providers = ind_providers();
    $fn = $providers[$row['fuente_automatica']] ?? null;
    if ($fn === null || !function_exists($fn)) return null;

    return $fn($conn, $periodo, $team);
}
