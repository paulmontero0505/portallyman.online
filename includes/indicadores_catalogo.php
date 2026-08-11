<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Módulo Indicadores — cálculo puro (sin BD)
   ───────────────────────────────────────────────────────────────────────
   Replica exactamente las fórmulas de la hoja "Datos Mensuales" del Excel
   Panel_Indicadores_Tally_2026-2.xlsx:
     · Valor por team      → según Tipo Cálculo del indicador
     · Resultado General   → agregado de los 4 teams
     · % vs Meta            → Resultado General / Meta
     · Estado               → semáforo SIN DATO / CUMPLE / EN RIESGO / NO CUMPLE
   ═══════════════════════════════════════════════════════════════════════ */

/** Valor de un indicador para UN team, según su tipo de cálculo. */
function ind_valor_team($tipoCalculo, $numerador, $denominador) {
    if ($tipoCalculo === 'Ratio') {
        if ($numerador === null || $denominador === null || (float)$denominador == 0.0) return null;
        return (float)$numerador / (float)$denominador;
    }
    // Suma, Promedio y Binario solo usan el numerador (igual que la nota de la hoja).
    if ($numerador === null) return null;
    if ($tipoCalculo === 'Binario') return ((float)$numerador > 0) ? 1 : 0;
    if ($tipoCalculo === 'Suma' || $tipoCalculo === 'Promedio') return $numerador;
    return null;
}

/**
 * Resultado General agregando los 4 teams.
 * $valoresPorTeam = array de Valor-por-team ya calculados (puede tener menos de 4 si faltan datos).
 * $totalN / $totalD = suma de numeradores/denominadores de los teams con dato.
 */
function ind_resultado_general($tipoCalculo, $valoresPorTeam, $totalN, $totalD) {
    if ($tipoCalculo === 'Ratio') {
        if ($totalN === null || $totalD === null || (float)$totalD == 0.0) return null;
        return (float)$totalN / (float)$totalD;
    }
    if ($tipoCalculo === 'Suma') {
        return $totalN === null ? null : $totalN;
    }
    if ($tipoCalculo === 'Promedio') {
        $valores = array_values(array_filter($valoresPorTeam, fn($v) => $v !== null));
        if (empty($valores)) return null;
        return array_sum($valores) / count($valores);
    }
    if ($tipoCalculo === 'Binario') {
        if ($totalN === null) return null;
        return ((float)$totalN > 0) ? 1 : 0;
    }
    return null;
}

/**
 * % vs Meta. La hoja "Datos Mensuales" NO usa una sola formula: cada fila trae
 * la que corresponde al operador de su indicador, y son inversas entre si.
 *
 *   operador '>='  (mas es mejor):   =IFERROR(IF(X="","",X/F),"")
 *   operador '<='  (menos es mejor): =IFERROR(IF(X="","",IF(X=0,2,F/X)),"")
 *
 * Invertir la razon en los '<=' es lo que hace que el semaforo signifique lo
 * mismo en ambos casos (>=1 es cumplir). Sin esa inversion, cero incumplimientos
 * de EPP puntuaria 0 ("NO CUMPLE") y un 70% de reincidencia puntuaria 3.5
 * ("CUMPLE"): exactamente al reves de la realidad.
 *
 * El tope de 2 para resultado 0 evita la division por cero y refleja que el
 * cumplimiento perfecto de un indicador '<=' no es infinito, es "2x la meta".
 */
function ind_pct_vs_meta($resultadoGeneral, $meta, $operador = '>=') {
    if ($resultadoGeneral === null || $meta === null) return null;
    $resultado = (float)$resultadoGeneral;
    $meta = (float)$meta;

    if ($operador === '<=') {
        if ($resultado == 0.0) return 2.0;
        return $meta / $resultado;
    }

    if ($meta == 0.0) return null;
    return $resultado / $meta;
}

/** Semáforo de Estado. Umbrales identicos a la hoja: SIN DATO / CUMPLE >=1 / EN RIESGO >=0.8 / NO CUMPLE. */
function ind_estado($pctVsMeta) {
    if ($pctVsMeta === null) return 'SIN DATO';
    if ($pctVsMeta >= 1) return 'CUMPLE';
    if ($pctVsMeta >= 0.8) return 'EN RIESGO';
    return 'NO CUMPLE';
}

/** Rango de fechas [inicio,fin] del mes de un periodo 'YYYY-MM'. Null si el mes es invalido. */
function ind_periodo_fechas($periodo) {
    if (!preg_match('/^(\d{4})-(\d{2})$/', (string)$periodo, $m)) return null;
    $anio = (int)$m[1]; $mes = (int)$m[2];
    if ($mes < 1 || $mes > 12) return null;
    $inicio = sprintf('%04d-%02d-01', $anio, $mes);
    $fin = date('Y-m-t', strtotime($inicio));
    return ['inicio' => $inicio, 'fin' => $fin];
}

/** Trimestre 'YYYY-T#' (formato evades_periodo_fechas) al que pertenece un periodo 'YYYY-MM'. */
function ind_trimestre_de_periodo($periodo) {
    if (!preg_match('/^(\d{4})-(\d{2})$/', (string)$periodo, $m)) return null;
    $anio = (int)$m[1]; $mes = (int)$m[2];
    if ($mes < 1 || $mes > 12) return null;
    $trimestre = (int)ceil($mes / 3);
    return "$anio-T$trimestre";
}

/**
 * Extrae 'TEAM A'..'TEAM D' de un valor libre de colaboradores.cuadrilla
 * (hoy inconsistente en produccion: "TEAM A", "G1 TEAM A", "DIURNO", "SIN ASIGNAR").
 * Devuelve null si no matchea — nunca se inventa un team para que cuadre.
 */
function ind_team_normalizado($cuadrilla) {
    if ($cuadrilla === null) return null;
    if (preg_match('/\bTEAM\s*([A-D])\b/i', (string)$cuadrilla, $m)) {
        return 'TEAM ' . strtoupper($m[1]);
    }
    return null;
}

/** Los 4 teams validos, en orden fijo (igual que las columnas del Excel). */
function ind_teams() {
    return ['TEAM A', 'TEAM B', 'TEAM C', 'TEAM D'];
}
