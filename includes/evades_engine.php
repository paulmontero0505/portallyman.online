<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Motor de cálculo EVADES
   ───────────────────────────────────────────────────────────────────────
   Dado un colaborador y un trimestre, sugiere incremento/descuento por
   competencia a partir de reconocimientos aprobados e incidencias del
   período, más el bono de Autonomía por evaluaciones diarias. No escribe
   nada en la base: solo lee y devuelve sugerencias. Quien persiste es
   api/save_evades.php.
   ═══════════════════════════════════════════════════════════════════════ */

require_once(__DIR__ . '/evades_catalogo.php');
require_once(__DIR__ . '/incidencias_catalogo.php'); // inc_impactos(): orden de severidad
require_once(__DIR__ . '/evades_evidence.php');

/** Añade al JSON histórico la regla y cobertura usadas en ese cálculo. */
function evades_evidencia_para_snapshot($sugerencia) {
    $evidencia = is_array($sugerencia['evidencia'] ?? null) ? $sugerencia['evidencia'] : [];
    $evidencia[] = [
        'tipo' => 'calculo_motor',
        'cobertura' => $sugerencia['cobertura'] ?? 'parcial',
        'regla' => $sugerencia['regla'] ?? null,
        'frecuencia' => (int)($sugerencia['frecuencia'] ?? 0),
        'impacto_mayor' => $sugerencia['impacto_mayor'] ?? null,
        'descripcion' => $sugerencia['resumen_calculo'] ?? 'Sin evidencia suficiente; conserva la base 6.',
    ];
    return $evidencia;
}

/**
 * Sugerencias para las 10 competencias de un colaborador en un trimestre.
 * Devuelve null si el período no es válido. Cada fila trae:
 *   competencia_key, label, tipo, base(6),
 *   auto_incremento (int|null), auto_descuento (int|null), evidencia[]
 * null en auto_incremento/auto_descuento = "sin evidencia automática"
 * (puede ser porque no hay catálogo para ese lado, o porque sí lo hay
 * pero no se encontró ningún registro en el período).
 */
function evades_calcular_sugerencias($conn, $colaboradorId, $periodo) {
    $rango = evades_periodo_fechas($periodo);
    if (!$rango) return null;

    $catalogo = evades_competencias();
    $reglas = evades_reglas_evidencia();
    $resultado = [];

    foreach ($catalogo as $key => $comp) {
        $regla = $reglas[$key] ?? [
            'puntos_incidencia' => [], 'cruzadas' => [], 'criterios_ept' => [], 'fuentes_positivas' => [],
        ];
        $fila = [
            'competencia_key' => $key,
            'label' => $comp['label'],
            'tipo' => $comp['tipo'],
            'base' => 6,
            'auto_incremento' => null,
            'auto_descuento' => null,
            'evidencia' => [],
            'cobertura' => 'parcial',
            'regla' => null,
            'resumen_calculo' => 'Sin evidencia suficiente; conserva la base 6.',
            'frecuencia' => 0,
            'impacto_mayor' => null,
            'incidencias' => 0,
        ];

        $incidencias = evades_evidencia_incidencias($conn, $colaboradorId, $key, $rango);
        $apreciaciones = evades_evidencia_apreciaciones($conn, $colaboradorId, $key, $rango);
        $negativas = array_merge($incidencias, $apreciaciones['negativas']);
        $candidatosPositivos = [];
        $evidencia = $incidencias;

        if (in_array('reconocimiento', $regla['fuentes_positivas'], true)) {
            $reconocimientos = evades_evidencia_reconocimientos($conn, $colaboradorId, $key, $rango);
            if ($reconocimientos['nivel'] !== null) $candidatosPositivos[] = (int)$reconocimientos['nivel'];
            $evidencia = array_merge($evidencia, $reconocimientos['evidencia']);
        }

        if (!empty($regla['criterios_ept'])) {
            $ept = evades_evidencia_ept($conn, $colaboradorId, $key, $rango);
            $permitePositivo = !in_array('ept_sin_incidencias', $regla['fuentes_positivas'], true) || empty($incidencias);
            if ($permitePositivo && $ept['nivel'] !== null) $candidatosPositivos[] = (int)$ept['nivel'];
            $evidencia = array_merge($evidencia, $ept['evidencia']);
        }

        if (in_array('asistencia', $regla['fuentes_positivas'], true)) {
            $asistencia = evades_evidencia_asistencia($conn, $colaboradorId, $rango);
            if ($asistencia['nivel'] !== null) $candidatosPositivos[] = (int)$asistencia['nivel'];
            foreach ($asistencia['evidencia'] as $item) {
                if ($key === 'organizacion_tiempo') {
                    $item['competencia_destino'] = $key;
                    $item['es_cruce'] = true;
                }
                $evidencia[] = $item;
            }
        }

        if (in_array('propuesta', $regla['fuentes_positivas'], true)) {
            $propuestas = evades_evidencia_propuestas($conn, $colaboradorId, $rango);
            if ($propuestas['nivel'] !== null) $candidatosPositivos[] = (int)$propuestas['nivel'];
            foreach ($propuestas['evidencia'] as $item) {
                if ($key === 'adaptabilidad') {
                    $item['competencia_destino'] = $key;
                    $item['es_cruce'] = true;
                }
                $evidencia[] = $item;
            }
        }

        if ($apreciaciones['nivel'] !== null) $candidatosPositivos[] = (int)$apreciaciones['nivel'];
        $evidencia = array_merge($evidencia, $apreciaciones['evidencia']);

        if (!empty($candidatosPositivos)) {
            $fila['auto_incremento'] = min(4, max($candidatosPositivos));
        }

        if (!empty($negativas)) {
            $ordenImpacto = array_flip(array_keys(inc_impactos()));
            $peorImpacto = null;
            $peorRank = -1;
            foreach ($negativas as $negativa) {
                $impacto = $negativa['impacto'] ?? null;
                $rank = $ordenImpacto[$impacto] ?? -1;
                if ($rank > $peorRank) { $peorRank = $rank; $peorImpacto = $impacto; }
            }
            $frecuencia = min(count($negativas), 5);
            $fila['auto_descuento'] = evades_matriz_fi()[$frecuencia][$peorImpacto] ?? 0;
            $fila['frecuencia'] = $frecuencia;
            $fila['impacto_mayor'] = $peorImpacto;
            $fila['incidencias'] = count($incidencias);
            $impactoLabel = inc_impactos()[$peorImpacto]['label'] ?? ucfirst((string)$peorImpacto);
            $fila['regla'] = 'matriz_frecuencia_impacto';
            $fila['resumen_calculo'] = 'Frecuencia ' . $frecuencia . ' × impacto ' . $impactoLabel
                . ' = -' . $fila['auto_descuento'] . '.';
        } elseif ($fila['auto_incremento'] !== null && $fila['auto_incremento'] > 0) {
            $fila['regla'] = 'mejor_evidencia_positiva';
            $fila['resumen_calculo'] = 'Mejor evidencia positiva = +' . $fila['auto_incremento'] . '.';
        }

        $fila['evidencia'] = $evidencia;
        if ($key === 'productividad' && empty($evidencia)) {
            $fila['cobertura'] = 'sin_fuente';
            $fila['resumen_calculo'] = 'Fuente individual de productividad pendiente; conserva la base 6.';
        } elseif (!empty($negativas) || $fila['auto_incremento'] !== null) {
            $fila['cobertura'] = 'suficiente';
        } else {
            $fila['cobertura'] = 'parcial';
        }

        $resultado[] = $fila;
    }

    return $resultado;
}

/**
 * Incremento sugerido a partir de reconocimientos aprobados de esa
 * competencia en el rango. bueno=+2, excelente/sobresaliente=+4 (se toma
 * el nivel más alto encontrado, no se suman varios reconocimientos).
 */
function evades_incremento_de_reconocimientos($conn, $colaboradorId, $competencia, $rango) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, fecha, impacto FROM reconocimientos
          WHERE colaborador_id=? AND competencia=? AND estado='aprobado'
            AND fecha BETWEEN ? AND ?"
    );
    mysqli_stmt_bind_param($stmt, 'isss', $colaboradorId, $competencia, $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $rows = [];
    $mejorNivel = null;
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
        $nivel = in_array($row['impacto'], ['excelente', 'sobresaliente'], true) ? 4 : 2;
        if ($mejorNivel === null || $nivel > $mejorNivel) $mejorNivel = $nivel;
    }
    mysqli_stmt_close($stmt);

    if (empty($rows)) return ['incremento' => null, 'evidencia' => []];

    $evidencia = array_map(function ($r) {
        return ['tipo' => 'reconocimiento', 'id' => (int)$r['id'], 'fecha' => $r['fecha'], 'impacto' => $r['impacto']];
    }, $rows);

    return ['incremento' => $mejorNivel, 'evidencia' => $evidencia];
}

/**
 * Descuento sugerido a partir de incidencias de esa competencia en el
 * rango: Frecuencia = cantidad (tope 5), Impacto = el más severo
 * presente, cruzados contra evades_matriz_fi().
 */
function evades_descuento_de_incidencias($conn, $colaboradorId, $competencia, $rango) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, fecha, impacto, punto_mejorar, detalle FROM incidencias
          WHERE colaborador_id=? AND competencia=? AND fecha BETWEEN ? AND ?"
    );
    mysqli_stmt_bind_param($stmt, 'isss', $colaboradorId, $competencia, $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $rows = [];
    // Orden de severidad derivado de inc_impactos() (includes/incidencias_catalogo.php),
    // que ya define el orden canónico minimo→critico: así no se duplica esa lista aquí.
    $ordenImpacto = array_flip(array_keys(inc_impactos()));
    $peorImpacto = null;
    $peorRank = -1;
    while ($row = mysqli_fetch_assoc($res)) {
        $rows[] = $row;
        $rank = $ordenImpacto[$row['impacto']] ?? -1;
        if ($rank > $peorRank) { $peorRank = $rank; $peorImpacto = $row['impacto']; }
    }
    mysqli_stmt_close($stmt);

    if (empty($rows)) return ['descuento' => null, 'evidencia' => []];

    $frecuencia = min(count($rows), 5);
    $matriz = evades_matriz_fi();
    $descuento = $matriz[$frecuencia][$peorImpacto] ?? 0;

    $evidencia = array_map(function ($r) {
        return [
            'tipo' => 'incidencia', 'id' => (int)$r['id'], 'fecha' => $r['fecha'], 'impacto' => $r['impacto'],
            'punto_mejorar' => $r['punto_mejorar'], 'detalle' => $r['detalle'],
        ];
    }, $rows);

    return ['descuento' => $descuento, 'evidencia' => $evidencia];
}

/**
 * Bono Autonomía: +2 si el colaborador tiene >= 5 evaluaciones diarias
 * (evaluacion_desempeno) en el trimestre con promedio de puntaje_total > 38.
 * Devuelve null si no aplica.
 */
function evades_bono_autonomia($conn, $colaboradorId, $rango) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT COUNT(*) AS n, AVG(puntaje_total) AS prom FROM evaluacion_desempeno
          WHERE colaborador_id=? AND fecha BETWEEN ? AND ?"
    );
    mysqli_stmt_bind_param($stmt, 'iss', $colaboradorId, $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $n = (int)($row['n'] ?? 0);
    $prom = $row['prom'] !== null ? (float)$row['prom'] : null;

    if ($n >= 5 && $prom !== null && $prom > 38) {
        return [
            'puntos' => 2,
            'evidencia' => [
                'tipo' => 'bono_evaluacion_diaria',
                'n' => $n,
                'promedio' => round($prom, 1),
            ],
        ];
    }
    return null;
}
