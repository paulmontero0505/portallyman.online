<?php

require_once(__DIR__ . '/evades_catalogo.php');

/** Construye el contrato común que consume el motor y presenta la interfaz. */
function evades_evidencia_normalizada($tipo, $fuente, $id, $fecha, $origen, $destino, $esCruce, $valor, $impacto, $descripcion) {
    return [
        'tipo' => (string)$tipo,
        'fuente' => (string)$fuente,
        'id' => (int)$id,
        'fecha' => $fecha !== null ? (string)$fecha : null,
        'competencia_origen' => (string)$origen,
        'competencia_destino' => (string)$destino,
        'es_cruce' => (bool)$esCruce,
        'valor' => $valor,
        'impacto' => $impacto !== null ? (string)$impacto : null,
        'descripcion' => trim((string)$descripcion),
    ];
}

/** Incidencias canónicas por punto a mejorar, incluidas relaciones cruzadas. */
function evades_evidencia_incidencias($conn, $colaboradorId, $competenciaKey, $rango) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT id,fecha,punto_mejorar,impacto,detalle FROM incidencias
          WHERE colaborador_id=? AND fecha BETWEEN ? AND ? ORDER BY fecha,id'
    );
    mysqli_stmt_bind_param($stmt, 'iss', $colaboradorId, $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $evidencia = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $afectadas = evades_claves_afectadas_por_punto($row['punto_mejorar']);
        if (!in_array($competenciaKey, $afectadas, true)) continue;
        $origen = $afectadas[0];
        $evidencia[] = evades_evidencia_normalizada(
            'incidencia',
            'incidencias',
            $row['id'],
            $row['fecha'],
            $origen,
            $competenciaKey,
            $origen !== $competenciaKey,
            null,
            $row['impacto'],
            $row['punto_mejorar'] . (($row['detalle'] ?? '') !== '' ? ': ' . $row['detalle'] : '')
        );
    }
    mysqli_stmt_close($stmt);
    return $evidencia;
}

/** Reconocimientos aprobados; varias filas aportan como máximo el mejor nivel. */
function evades_evidencia_reconocimientos($conn, $colaboradorId, $competenciaKey, $rango) {
    $catalogo = evades_competencias();
    $competencia = $catalogo[$competenciaKey]['rec_competencia'] ?? null;
    if ($competencia === null) return ['nivel' => null, 'evidencia' => []];

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id,fecha,impacto,detalle FROM reconocimientos
          WHERE colaborador_id=? AND competencia=? AND estado='aprobado'
            AND fecha BETWEEN ? AND ? ORDER BY fecha,id"
    );
    mysqli_stmt_bind_param($stmt, 'isss', $colaboradorId, $competencia, $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $nivel = null;
    $evidencia = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $filaNivel = in_array($row['impacto'], ['excelente', 'sobresaliente'], true) ? 4 : 2;
        $nivel = max($nivel ?? 0, $filaNivel);
        $evidencia[] = evades_evidencia_normalizada(
            'reconocimiento', 'reconocimientos', $row['id'], $row['fecha'],
            $competenciaKey, $competenciaKey, false, $filaNivel, $row['impacto'],
            $row['detalle'] ?: ('Reconocimiento ' . $row['impacto'])
        );
    }
    mysqli_stmt_close($stmt);
    return ['nivel' => $nivel, 'evidencia' => $evidencia];
}

/** Clave estable para los nueve ítems de la Evaluación en Puesto de Trabajo. */
function evades_clave_criterio_ept($texto) {
    $texto = mb_strtolower(trim((string)$texto), 'UTF-8');
    $patrones = [
        'procedimientos' => 'cumple con los procedimientos',
        'registro_preciso' => 'registra y valida la información',
        'respuesta_procedimientos' => 'responde consultas y solicitudes',
        'uso_epp' => 'hace uso correcto y permanente',
        'zona_segura' => 'se mantiene dentro de las zonas seguras',
        'reporte_riesgos' => 'identifica y reporta oportunamente',
        'trato_colaborativo' => 'mantiene un trato cordial',
        'comunicacion_novedades' => 'comunica de forma clara y oportuna',
        'apoyo_equipo' => 'demuestra disposición para apoyar',
    ];
    foreach ($patrones as $key => $patron) {
        if (strpos($texto, $patron) !== false) return $key;
    }
    return null;
}

/** Promedia solo los ítems EPT declarados para la competencia. */
function evades_evidencia_ept($conn, $colaboradorId, $competenciaKey, $rango) {
    $reglas = evades_reglas_evidencia();
    $criteriosObjetivo = $reglas[$competenciaKey]['criterios_ept'] ?? [];
    if (empty($criteriosObjetivo)) {
        return ['n' => 0, 'promedio' => null, 'nivel' => null, 'evidencia' => [], 'invalidas' => 0];
    }

    $stmt = mysqli_prepare(
        $conn,
        'SELECT id,fecha,actividad,criterios FROM evaluacion_desempeno
          WHERE colaborador_id=? AND fecha BETWEEN ? AND ? ORDER BY fecha,id'
    );
    mysqli_stmt_bind_param($stmt, 'iss', $colaboradorId, $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $puntajes = [];
    $evaluacionesRelevantes = 0;
    $invalidas = 0;
    $evidencia = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $criterios = json_decode($row['criterios'], true);
        if (!is_array($criterios)) { $invalidas++; continue; }
        $puntajesEvaluacion = [];
        foreach ($criterios as $criterio) {
            $clave = evades_clave_criterio_ept($criterio['item'] ?? '');
            if ($clave === null || !in_array($clave, $criteriosObjetivo, true)) continue;
            if (!isset($criterio['puntaje']) || !is_numeric($criterio['puntaje'])) continue;
            $puntajesEvaluacion[] = (float)$criterio['puntaje'];
        }
        if (empty($puntajesEvaluacion)) continue;
        $evaluacionesRelevantes++;
        $puntajes = array_merge($puntajes, $puntajesEvaluacion);
        $promedioFila = array_sum($puntajesEvaluacion) / count($puntajesEvaluacion);
        $evidencia[] = evades_evidencia_normalizada(
            'ept', 'evaluacion_desempeno', $row['id'], $row['fecha'],
            $competenciaKey, $competenciaKey, false, round($promedioFila, 2), null,
            $row['actividad'] ?: 'Evaluación en puesto de trabajo'
        );
    }
    mysqli_stmt_close($stmt);

    $promedio = empty($puntajes) ? null : round(array_sum($puntajes) / count($puntajes), 2);
    $umbrales = evades_umbrales_ept();
    $nivel = null;
    if ($evaluacionesRelevantes >= $umbrales['minimo'] && $promedio !== null) {
        if ($promedio >= $umbrales['nivel_4']) $nivel = 4;
        elseif ($promedio >= $umbrales['nivel_2']) $nivel = 2;
        else $nivel = 0;
    }
    return [
        'n' => $evaluacionesRelevantes,
        'promedio' => $promedio,
        'nivel' => $nivel,
        'evidencia' => $evidencia,
        'invalidas' => $invalidas,
    ];
}

/** Asistencia perfecta con tres eventos aporta +2; liderazgo requiere otra fuente. */
function evades_evidencia_asistencia($conn, $colaboradorId, $rango) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT ap.id,ap.estado,a.fecha,a.tema
           FROM asistencias_participantes ap
           INNER JOIN asistencias_preoperativas a ON a.id=ap.asistencia_id
          WHERE ap.colaborador_id=? AND a.fecha BETWEEN ? AND ? ORDER BY a.fecha,ap.id'
    );
    mysqli_stmt_bind_param($stmt, 'iss', $colaboradorId, $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $total = 0;
    $asistio = 0;
    $evidencia = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $total++;
        if ($row['estado'] === 'asistio') $asistio++;
        $evidencia[] = evades_evidencia_normalizada(
            'asistencia', 'asistencias_preoperativas', $row['id'], $row['fecha'],
            'disciplina_profesional', 'disciplina_profesional', false,
            $row['estado'] === 'asistio' ? 1 : 0, $row['estado'], $row['tema']
        );
    }
    mysqli_stmt_close($stmt);
    $nivel = $total >= 3 && $asistio === $total ? 2 : null;
    return ['total' => $total, 'asistio' => $asistio, 'nivel' => $nivel, 'evidencia' => $evidencia];
}

/** Propuestas identificadas, viables y revisadas alimentan Iniciativa. */
function evades_evidencia_propuestas($conn, $colaboradorId, $rango) {
    $inicio = $rango['inicio'] . ' 00:00:00';
    $fin = $rango['fin'] . ' 23:59:59';
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id,created_at,impacto,detalle,viabilidad FROM sugerencias_tallyman
          WHERE colaborador_id=? AND canal='propuesta' AND puntaje_at IS NOT NULL
            AND viabilidad=1 AND created_at BETWEEN ? AND ? ORDER BY created_at,id"
    );
    mysqli_stmt_bind_param($stmt, 'iss', $colaboradorId, $inicio, $fin);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $nivel = null;
    $evidencia = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $filaNivel = $row['impacto'] === 'alto' ? 4 : 2;
        $nivel = max($nivel ?? 0, $filaNivel);
        $evidencia[] = evades_evidencia_normalizada(
            'propuesta', 'sugerencias_tallyman', $row['id'], substr($row['created_at'], 0, 10),
            'iniciativa_compromiso', 'iniciativa_compromiso', false,
            $filaNivel, $row['impacto'], $row['detalle']
        );
    }
    mysqli_stmt_close($stmt);
    return ['nivel' => $nivel, 'evidencia' => $evidencia];
}

/** Apreciaciones vigentes asociadas a la evaluación del mismo trimestre. */
function evades_evidencia_apreciaciones($conn, $colaboradorId, $competenciaKey, $rango) {
    $stmt = mysqli_prepare(
        $conn,
        'SELECT a.id,a.direccion,a.nivel,a.impacto,a.descripcion,a.created_at
           FROM evades_apreciaciones a
           INNER JOIN evades_evaluaciones ev ON ev.id=a.evaluacion_id
          WHERE a.colaborador_id=? AND a.competencia_key=? AND a.vigente=1
            AND ev.fecha_evaluacion BETWEEN ? AND ?
          ORDER BY a.created_at,a.id'
    );
    mysqli_stmt_bind_param($stmt, 'isss', $colaboradorId, $competenciaKey, $rango['inicio'], $rango['fin']);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $nivel = null;
    $negativas = [];
    $evidencia = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $valor = $row['direccion'] === 'positiva' ? (int)$row['nivel'] : null;
        if ($row['direccion'] === 'positiva') $nivel = max($nivel ?? 0, $valor);
        $normalizada = evades_evidencia_normalizada(
            'apreciacion', 'evades_apreciaciones', $row['id'], substr($row['created_at'], 0, 10),
            $competenciaKey, $competenciaKey, false, $valor, $row['impacto'], $row['descripcion']
        );
        $normalizada['direccion'] = $row['direccion'];
        $evidencia[] = $normalizada;
        if ($row['direccion'] === 'negativa') $negativas[] = $normalizada;
    }
    mysqli_stmt_close($stmt);
    return ['nivel' => $nivel, 'negativas' => $negativas, 'evidencia' => $evidencia];
}
