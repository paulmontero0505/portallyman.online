<?php

require_once(__DIR__ . '/evades_catalogo.php');
require_once(__DIR__ . '/evades_engine.php');

/** Resumen de bloques visibles para el actor. */
function evades_listar_bloques($conn, $actorId, $rol) {
    $actorId = (int)$actorId;
    if ($actorId <= 0) return [];
    if (in_array($rol, ['Administrador', 'Supervisor'], true)) {
        $where = '1=1';
    } elseif ($rol === 'Coordinador') {
        $where = 'b.coordinador_id=' . $actorId;
    } else {
        return [];
    }

    $sql = "SELECT b.*,
                   COUNT(ev.id) AS evaluaciones_total,
                   COALESCE(ROUND(AVG(ev.puntaje_total),2),0) AS promedio,
                   SUM(CASE WHEN TRIM(COALESCE(ev.fortalezas,''))<>''
                                  AND TRIM(COALESCE(ev.aspectos_mejora,''))<>''
                                  AND TRIM(COALESCE(ev.plan_accion,''))<>'' THEN 1 ELSE 0 END) AS completas,
                   SUM(CASE WHEN ev.clasificacion='Debajo de lo esperado' THEN 1 ELSE 0 END) AS debajo,
                   SUM(CASE WHEN ev.clasificacion='En lo esperado' THEN 1 ELSE 0 END) AS esperado,
                   SUM(CASE WHEN ev.clasificacion='Sobre lo esperado' THEN 1 ELSE 0 END) AS sobre,
                   SUM(CASE WHEN ev.clasificacion='Sobresaliente' THEN 1 ELSE 0 END) AS sobresalientes
              FROM evades_bloques b
              LEFT JOIN evades_evaluaciones ev ON ev.bloque_id=b.id
             WHERE $where
             GROUP BY b.id
             ORDER BY b.periodo DESC,b.updated_at DESC,b.id DESC";
    $res = mysqli_query($conn, $sql);
    if (!$res) throw new RuntimeException(mysqli_error($conn));
    $bloques = [];
    while ($row = mysqli_fetch_assoc($res)) {
        foreach (['id','coordinador_id','total_colaboradores','version','evaluaciones_total','completas','debajo','esperado','sobre','sobresalientes'] as $campo) {
            $row[$campo] = (int)$row[$campo];
        }
        $row['promedio'] = (float)$row['promedio'];
        $bloques[] = $row;
    }
    return $bloques;
}

/** Elimina una ficha de un bloque y conserva el bloque para el resto de la nómina. Sólo Administrador. */
function evades_eliminar_evaluacion_bloque($conn, $evaluacionId, $actorId) {
    $evaluacionId = (int)$evaluacionId;
    $actorId = (int)$actorId;
    if ($evaluacionId <= 0 || $actorId <= 0) throw new RuntimeException('Evaluación o usuario inválido.');

    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn,
            "SELECT ev.id,ev.bloque_id,b.estado,b.total_colaboradores,u.rol AS actor_rol
               FROM evades_evaluaciones ev
               JOIN evades_bloques b ON b.id=ev.bloque_id
               JOIN usuarios u ON u.id=? AND u.estado='Activo'
              WHERE ev.id=? LIMIT 1 FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $actorId, $evaluacionId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$row) throw new RuntimeException('La evaluación no pertenece a un bloque EVADES activo.');
        if ($row['actor_rol'] !== 'Administrador') throw new RuntimeException('Solo un administrador puede eliminar evaluaciones EVADES.');

        $bloqueId = (int)$row['bloque_id'];
        $stmt = mysqli_prepare($conn, 'DELETE FROM evades_modificaciones WHERE evaluacion_id=?');
        mysqli_stmt_bind_param($stmt, 'i', $evaluacionId);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, 'DELETE FROM evades_evaluaciones WHERE id=? AND bloque_id=?');
        mysqli_stmt_bind_param($stmt, 'ii', $evaluacionId, $bloqueId);
        if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) throw new RuntimeException('No se pudo eliminar la evaluación.');
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, 'UPDATE evades_bloques SET total_colaboradores=GREATEST(total_colaboradores-1,0),version=version+1 WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'i', $bloqueId);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $contexto = 'Evaluación individual eliminada por Administración';
        $stmt = mysqli_prepare($conn, 'INSERT INTO evades_bloques_estados (bloque_id,estado_anterior,estado_nuevo,usuario_id,contexto) VALUES (?,?,?,?,?)');
        mysqli_stmt_bind_param($stmt, 'issis', $bloqueId, $row['estado'], $row['estado'], $actorId, $contexto);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        return ['bloque_id' => $bloqueId];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/** Elimina un bloque completo, su nómina y la auditoría vinculada. Sólo Administrador. */
function evades_eliminar_bloque($conn, $bloqueId, $actorId) {
    $bloqueId = (int)$bloqueId;
    $actorId = (int)$actorId;
    if ($bloqueId <= 0 || $actorId <= 0) throw new RuntimeException('Bloque o usuario inválido.');

    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn,
            "SELECT b.id,u.rol AS actor_rol FROM evades_bloques b
               JOIN usuarios u ON u.id=? AND u.estado='Activo'
              WHERE b.id=? LIMIT 1 FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $actorId, $bloqueId);
        mysqli_stmt_execute($stmt);
        $bloque = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$bloque) throw new RuntimeException('Bloque EVADES no encontrado.');
        if ($bloque['actor_rol'] !== 'Administrador') throw new RuntimeException('Solo un administrador puede eliminar bloques EVADES.');

        $stmt = mysqli_prepare($conn, 'DELETE FROM evades_modificaciones WHERE bloque_id=?');
        mysqli_stmt_bind_param($stmt, 'i', $bloqueId);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, 'DELETE FROM evades_evaluaciones WHERE bloque_id=?');
        mysqli_stmt_bind_param($stmt, 'i', $bloqueId);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, 'DELETE FROM evades_bloques WHERE id=?');
        mysqli_stmt_bind_param($stmt, 'i', $bloqueId);
        if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) throw new RuntimeException('No se pudo eliminar el bloque EVADES.');
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        return ['id' => $bloqueId];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/** Cabecera, nómina e historiales de un bloque visible. */
function evades_obtener_bloque($conn, $bloqueId, $actorId, $rol) {
    $bloqueId = (int)$bloqueId;
    $actorId = (int)$actorId;
    if ($bloqueId <= 0 || $actorId <= 0) throw new RuntimeException('Bloque EVADES inválido.');

    $stmt = mysqli_prepare($conn, 'SELECT * FROM evades_bloques WHERE id=? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $bloqueId);
    mysqli_stmt_execute($stmt);
    $bloque = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$bloque) throw new RuntimeException('Bloque EVADES no encontrado.');
    $esGlobal = in_array($rol, ['Administrador', 'Supervisor'], true);
    if (!$esGlobal && !($rol === 'Coordinador' && (int)$bloque['coordinador_id'] === $actorId)) {
        throw new RuntimeException('No tienes acceso a este bloque EVADES.');
    }

    foreach (['id','coordinador_id','total_colaboradores','version'] as $campo) $bloque[$campo] = (int)$bloque[$campo];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT id,bloque_id,version,colaborador_id,colaborador_nombre,colaborador_codigo,colaborador_cargo,fecha_ingreso,
                fecha_evaluacion,puntaje_total,clasificacion,puntaje_anterior,variacion_pct,
                fortalezas,aspectos_mejora,plan_accion,updated_at
           FROM evades_evaluaciones WHERE bloque_id=? ORDER BY colaborador_nombre,id"
    );
    mysqli_stmt_bind_param($stmt, 'i', $bloqueId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $evaluaciones = [];
    while ($row = mysqli_fetch_assoc($res)) {
        foreach (['id','bloque_id','version','colaborador_id','puntaje_total'] as $campo) $row[$campo] = (int)$row[$campo];
        $row['puntaje_anterior'] = $row['puntaje_anterior'] !== null ? (int)$row['puntaje_anterior'] : null;
        $row['variacion_pct'] = $row['variacion_pct'] !== null ? (float)$row['variacion_pct'] : null;
        $row['completa'] = trim((string)$row['fortalezas']) !== '' && trim((string)$row['aspectos_mejora']) !== '' && trim((string)$row['plan_accion']) !== '';
        $evaluaciones[] = $row;
    }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, 'SELECT estado_anterior,estado_nuevo,usuario_id,contexto,created_at FROM evades_bloques_estados WHERE bloque_id=? ORDER BY id');
    mysqli_stmt_bind_param($stmt, 'i', $bloqueId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $historial = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['usuario_id'] = $row['usuario_id'] !== null ? (int)$row['usuario_id'] : null;
        $historial[] = $row;
    }
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, 'SELECT id,evaluacion_id,colaborador_id,usuario_id,motivo,antes_json,despues_json,created_at FROM evades_modificaciones WHERE bloque_id=? ORDER BY id');
    mysqli_stmt_bind_param($stmt, 'i', $bloqueId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $modificaciones = [];
    while ($row = mysqli_fetch_assoc($res)) {
        foreach (['id','evaluacion_id','colaborador_id','usuario_id'] as $campo) $row[$campo] = $row[$campo] !== null ? (int)$row[$campo] : null;
        $row['antes'] = json_decode($row['antes_json'], true) ?: [];
        $row['despues'] = json_decode($row['despues_json'], true) ?: [];
        unset($row['antes_json'], $row['despues_json']);
        $modificaciones[] = $row;
    }
    mysqli_stmt_close($stmt);

    $bloque['evaluaciones'] = $evaluaciones;
    $bloque['historial_estados'] = $historial;
    $bloque['modificaciones'] = $modificaciones;
    return $bloque;
}

/** Resuelve el coordinador del bloque sin confiar en un id arbitrario del cliente. */
function evades_resolver_coordinador_objetivo($sesion, $coordinadorSolicitado = 0) {
    $rol = (string)($sesion['user_rol'] ?? '');
    $usuarioId = (int)($sesion['user_id'] ?? 0);
    $solicitado = (int)$coordinadorSolicitado;

    if ($rol === 'Coordinador' && $usuarioId > 0) {
        if ($solicitado > 0 && $solicitado !== $usuarioId) {
            throw new RuntimeException('No puedes generar evaluaciones para otro coordinador.');
        }
        return $usuarioId;
    }
    if (in_array($rol, ['Administrador', 'Supervisor'], true) && $solicitado > 0) {
        return $solicitado;
    }
    throw new RuntimeException('Selecciona un coordinador válido para el bloque.');
}

/** Nómina activa actual que quedará congelada al generar un bloque. */
function evades_obtener_nomina($conn, $coordinadorId, $puesto) {
    $puestoCanonico = evades_normalizar_puesto($puesto);
    if ($puestoCanonico === null || (int)$coordinadorId <= 0) return [];

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, codigo, dni, nombre, funcion_principal, fecha_ingreso, coordinador_id, activo
           FROM colaboradores
          WHERE coordinador_id=? AND activo=1
            AND UPPER(TRIM(funcion_principal))=?
          ORDER BY nombre ASC, id ASC"
    );
    mysqli_stmt_bind_param($stmt, 'is', $coordinadorId, $puestoCanonico);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $nomina = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['id'] = (int)$row['id'];
        $row['coordinador_id'] = (int)$row['coordinador_id'];
        $row['activo'] = (int)$row['activo'];
        $nomina[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $nomina;
}

/**
 * Simula el cálculo de todo el bloque sin escribir en base de datos.
 * Sirve para que el coordinador conozca la cobertura real antes de congelar
 * la nómina: suficiente, parcial o sin fuente individual por competencia.
 */
function evades_obtener_cobertura_nomina($conn, $coordinadorId, $puesto, $periodo) {
    $rango = evades_periodo_fechas($periodo);
    if (!$rango) throw new RuntimeException('Período EVADES inválido.');

    $nomina = evades_obtener_nomina($conn, $coordinadorId, $puesto);
    $resumen = [
        'total_colaboradores' => count($nomina),
        'total_competencias' => 0,
        'suficiente' => 0,
        'parcial' => 0,
        'sin_fuente' => 0,
        'con_evidencia' => 0,
    ];
    $colaboradores = [];

    foreach ($nomina as $colaborador) {
        $sugerencias = evades_calcular_sugerencias($conn, (int)$colaborador['id'], $periodo);
        if (!is_array($sugerencias) || count($sugerencias) !== 10) {
            throw new RuntimeException('No se pudo calcular la cobertura de ' . $colaborador['nombre'] . '.');
        }

        $cobertura = ['suficiente' => 0, 'parcial' => 0, 'sin_fuente' => 0];
        $fuentes = [];
        $puntaje = 0;
        foreach ($sugerencias as $sugerencia) {
            $estado = $sugerencia['cobertura'] ?? 'parcial';
            if (!array_key_exists($estado, $cobertura)) $estado = 'parcial';
            $cobertura[$estado]++;
            $resumen[$estado]++;
            $resumen['total_competencias']++;
            foreach (($sugerencia['evidencia'] ?? []) as $evidencia) {
                $tipo = (string)($evidencia['tipo'] ?? 'evidencia');
                $fuentes[$tipo] = ($fuentes[$tipo] ?? 0) + 1;
                $resumen['con_evidencia']++;
            }
            $puntaje += max(0, min(10, 6 + (int)($sugerencia['auto_incremento'] ?? 0) - (int)($sugerencia['auto_descuento'] ?? 0)));
        }

        $colaboradores[] = array_merge($colaborador, [
            'puntaje_estimado' => $puntaje,
            'cobertura' => $cobertura,
            'fuentes' => $fuentes,
        ]);
    }

    $resumen['porcentaje_suficiente'] = $resumen['total_competencias'] > 0
        ? round(($resumen['suficiente'] / $resumen['total_competencias']) * 100, 1)
        : 0.0;

    return ['resumen' => $resumen, 'colaboradores' => $colaboradores];
}

/** Genera el bloque completo o revierte toda la operación. */
function evades_generar_bloque($conn, $coordinadorId, $puesto, $periodo, $actorId) {
    $coordinadorId = (int)$coordinadorId;
    $actorId = (int)$actorId;
    $puestoCanonico = evades_normalizar_puesto($puesto);
    $rango = evades_periodo_fechas($periodo);
    if ($coordinadorId <= 0) throw new RuntimeException('Selecciona un coordinador válido.');
    if ($actorId <= 0) throw new RuntimeException('Usuario de generación inválido.');
    if ($puestoCanonico === null) throw new RuntimeException('Puesto EVADES inválido.');
    if (!$rango) throw new RuntimeException('Período EVADES inválido.');

    $stmt = mysqli_prepare($conn, "SELECT id,nombre FROM usuarios WHERE id=? AND rol='Coordinador' AND estado='Activo' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $coordinadorId);
    mysqli_stmt_execute($stmt);
    $coordinador = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$coordinador) throw new RuntimeException('Coordinador no encontrado o inactivo.');

    $nomina = evades_obtener_nomina($conn, $coordinadorId, $puestoCanonico);
    if (!$nomina) throw new RuntimeException('No hay colaboradores activos para generar este bloque.');

    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn, 'SELECT id FROM evades_bloques WHERE coordinador_id=? AND puesto=? AND periodo=? LIMIT 1 FOR UPDATE');
        mysqli_stmt_bind_param($stmt, 'iss', $coordinadorId, $puestoCanonico, $periodo);
        mysqli_stmt_execute($stmt);
        $existente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($existente) throw new RuntimeException('Ya existe un bloque EVADES para ese coordinador, puesto y período.');

        $totalColaboradores = count($nomina);
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO evades_bloques
                (coordinador_id,coordinador_nombre,puesto,periodo,estado,total_colaboradores,version,generado_por)
             VALUES (?,?,?,?,'generado',?,1,?)"
        );
        mysqli_stmt_bind_param($stmt, 'isssii', $coordinadorId, $coordinador['nombre'], $puestoCanonico, $periodo, $totalColaboradores, $actorId);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        $bloqueId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $evaluaciones = [];
        foreach ($nomina as $col) {
            $colaboradorId = (int)$col['id'];
            $sugerencias = evades_calcular_sugerencias($conn, $colaboradorId, $periodo);
            if (!is_array($sugerencias) || count($sugerencias) !== 10) {
                throw new RuntimeException('No se pudieron calcular las diez competencias de ' . $col['nombre'] . '.');
            }

            $competencias = [];
            $puntajeTotal = 0;
            foreach ($sugerencias as $s) {
                $incremento = (int)($s['auto_incremento'] ?? 0);
                $descuento = (int)($s['auto_descuento'] ?? 0);
                $puntajeFinal = max(0, min(10, 6 + $incremento - $descuento));
                $puntajeTotal += $puntajeFinal;
                $competencias[] = [
                    'competencia_key' => $s['competencia_key'],
                    'tipo' => $s['tipo'],
                    'auto_incremento' => $s['auto_incremento'],
                    'auto_descuento' => $s['auto_descuento'],
                    'incremento_final' => $incremento,
                    'descuento_final' => $descuento,
                    'puntaje_final' => $puntajeFinal,
                    'evidencia_json' => json_encode(evades_evidencia_para_snapshot($s), JSON_UNESCAPED_UNICODE),
                ];
            }

            $puntajeAnterior = null;
            $stmt = mysqli_prepare($conn, 'SELECT puntaje_total FROM evades_evaluaciones WHERE colaborador_id=? AND periodo<>? ORDER BY fecha_evaluacion DESC,id DESC LIMIT 1');
            mysqli_stmt_bind_param($stmt, 'is', $colaboradorId, $periodo);
            mysqli_stmt_execute($stmt);
            $prev = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($prev) {
                $puntajeAnterior = (int)$prev['puntaje_total'];
            } else {
                $stmt = mysqli_prepare($conn, 'SELECT puntaje_total FROM evades_historico WHERE colaborador_id=? ORDER BY periodo DESC LIMIT 1');
                mysqli_stmt_bind_param($stmt, 'i', $colaboradorId);
                mysqli_stmt_execute($stmt);
                $prev = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
                if ($prev) $puntajeAnterior = (int)$prev['puntaje_total'];
            }
            $variacionPct = ($puntajeAnterior !== null && $puntajeAnterior > 0)
                ? round((($puntajeTotal - $puntajeAnterior) / $puntajeAnterior) * 100, 2)
                : null;
            $clasificacion = evades_clasificacion($puntajeTotal);
            $vacio = '';

            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO evades_evaluaciones
                    (bloque_id,colaborador_id,colaborador_nombre,colaborador_codigo,colaborador_cargo,colaborador_dni,fecha_ingreso,
                     coordinador_id,coordinador_nombre,periodo,fecha_evaluacion,puntaje_total,clasificacion,puntaje_anterior,variacion_pct,
                     fortalezas,aspectos_mejora,plan_accion)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            mysqli_stmt_bind_param(
                $stmt,
                'iisssssisssisidsss',
                $bloqueId, $colaboradorId, $col['nombre'], $col['codigo'], $col['funcion_principal'], $col['dni'], $col['fecha_ingreso'],
                $coordinadorId, $coordinador['nombre'], $periodo, $rango['fin'], $puntajeTotal, $clasificacion, $puntajeAnterior, $variacionPct,
                $vacio, $vacio, $vacio
            );
            if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
            $evaluacionId = (int)mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $stmtComp = mysqli_prepare(
                $conn,
                'INSERT INTO evades_competencias
                    (evaluacion_id,competencia_key,tipo,base,auto_incremento,auto_descuento,incremento_final,descuento_final,puntaje_final,motivo_ajuste,evidencia_json)
                 VALUES (?,?,?,6,?,?,?,?,?,NULL,?)'
            );
            foreach ($competencias as $comp) {
                mysqli_stmt_bind_param(
                    $stmtComp,
                    'issiiiiis',
                    $evaluacionId, $comp['competencia_key'], $comp['tipo'], $comp['auto_incremento'], $comp['auto_descuento'],
                    $comp['incremento_final'], $comp['descuento_final'], $comp['puntaje_final'], $comp['evidencia_json']
                );
                if (!mysqli_stmt_execute($stmtComp)) throw new RuntimeException(mysqli_stmt_error($stmtComp));
            }
            mysqli_stmt_close($stmtComp);

            $evaluaciones[] = [
                'id' => $evaluacionId,
                'colaborador_id' => $colaboradorId,
                'colaborador_nombre' => $col['nombre'],
                'puntaje_total' => $puntajeTotal,
                'clasificacion' => $clasificacion,
                'competencias' => $competencias,
            ];
        }

        $estadoNuevo = 'generado';
        $contexto = 'Bloque generado con nómina congelada';
        $stmt = mysqli_prepare($conn, 'INSERT INTO evades_bloques_estados (bloque_id,estado_anterior,estado_nuevo,usuario_id,contexto) VALUES (?,NULL,?,?,?)');
        mysqli_stmt_bind_param($stmt, 'isis', $bloqueId, $estadoNuevo, $actorId, $contexto);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        return [
            'id' => $bloqueId,
            'coordinador_id' => $coordinadorId,
            'coordinador_nombre' => $coordinador['nombre'],
            'puesto' => $puestoCanonico,
            'periodo' => $periodo,
            'estado' => 'generado',
            'version' => 1,
            'total_colaboradores' => $totalColaboradores,
            'evaluaciones' => $evaluaciones,
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/** La revisión administrativa bloquea la edición del coordinador. */
function evades_marcar_revisado($conn, $bloqueId, $actorId) {
    $bloqueId = (int)$bloqueId;
    $actorId = (int)$actorId;
    if ($bloqueId <= 0 || $actorId <= 0) throw new RuntimeException('Bloque o usuario inválido.');

    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT b.*,u.rol AS actor_rol
               FROM evades_bloques b
               JOIN usuarios u ON u.id=? AND u.estado='Activo'
              WHERE b.id=? LIMIT 1 FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $actorId, $bloqueId);
        mysqli_stmt_execute($stmt);
        $bloque = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$bloque) throw new RuntimeException('Bloque EVADES no encontrado.');

        if ($bloque['actor_rol'] !== 'Administrador') {
            throw new RuntimeException('Solo un administrador puede revisar el bloque EVADES.');
        }

        if ($bloque['estado'] === 'generado') {
            $stmt = mysqli_prepare($conn, "UPDATE evades_bloques SET estado='revisado',revisado_at=NOW(),version=version+1 WHERE id=? AND estado='generado'");
            mysqli_stmt_bind_param($stmt, 'i', $bloqueId);
            if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);

            $anterior = 'generado';
            $nuevo = 'revisado';
            $contexto = 'Bloque revisado por Administración; coordinador bloqueado para edición';
            $stmt = mysqli_prepare($conn, 'INSERT INTO evades_bloques_estados (bloque_id,estado_anterior,estado_nuevo,usuario_id,contexto) VALUES (?,?,?,?,?)');
            mysqli_stmt_bind_param($stmt, 'issis', $bloqueId, $anterior, $nuevo, $actorId, $contexto);
            if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);

            $bloque['estado'] = 'revisado';
            $bloque['version'] = (int)$bloque['version'] + 1;
            $bloque['revisado_at'] = date('Y-m-d H:i:s');
        } else {
            $bloque['version'] = (int)$bloque['version'];
        }

        mysqli_commit($conn);
        unset($bloque['actor_rol']);
        return $bloque;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/** Guarda una evaluación hija, audita el cambio e incrementa la versión del bloque. */
function evades_guardar_evaluacion_bloque($conn, $payload, $actorId) {
    $bloqueId = (int)($payload['bloque_id'] ?? 0);
    $evaluacionId = (int)($payload['id'] ?? 0);
    $versionEsperada = (int)($payload['version'] ?? 0);
    $actorId = (int)$actorId;
    $fechaEvaluacion = trim((string)($payload['fecha_evaluacion'] ?? ''));
    $fortalezas = trim((string)($payload['fortalezas'] ?? ''));
    $aspectos = trim((string)($payload['aspectos_mejora'] ?? ''));
    $plan = trim((string)($payload['plan_accion'] ?? ''));
    $filasEntrada = is_array($payload['competencias'] ?? null) ? $payload['competencias'] : [];

    if ($bloqueId <= 0 || $evaluacionId <= 0 || $versionEsperada <= 0 || $actorId <= 0) {
        throw new RuntimeException('Datos de guardado EVADES incompletos.');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEvaluacion)) {
        throw new RuntimeException('Fecha de evaluación inválida.');
    }

    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT b.*,u.rol AS actor_rol
               FROM evades_bloques b
               JOIN usuarios u ON u.id=? AND u.estado='Activo'
              WHERE b.id=? LIMIT 1 FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $actorId, $bloqueId);
        mysqli_stmt_execute($stmt);
        $bloque = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$bloque) throw new RuntimeException('Bloque EVADES no encontrado.');
        $esGlobal = in_array($bloque['actor_rol'], ['Administrador', 'Supervisor'], true);
        if (!$esGlobal && (int)$bloque['coordinador_id'] !== $actorId) {
            throw new RuntimeException('No tienes acceso a este bloque EVADES.');
        }
        if ($bloque['estado'] === 'revisado' && $bloque['actor_rol'] === 'Coordinador') {
            throw new RuntimeException('El bloque fue revisado por Administración y ya no admite edición del coordinador.');
        }
        if (!evades_bloque_editable($bloque['estado'])) {
            throw new RuntimeException('El bloque está cerrado y no admite cambios.');
        }
        if ((int)$bloque['version'] !== $versionEsperada) {
            throw new RuntimeException('El bloque fue actualizado en otra sesión. Recarga antes de guardar.');
        }

        $stmt = mysqli_prepare($conn, 'SELECT * FROM evades_evaluaciones WHERE id=? AND bloque_id=? LIMIT 1 FOR UPDATE');
        mysqli_stmt_bind_param($stmt, 'ii', $evaluacionId, $bloqueId);
        mysqli_stmt_execute($stmt);
        $evaluacion = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$evaluacion) throw new RuntimeException('La evaluación no pertenece a este bloque.');

        $rango = evades_periodo_fechas($bloque['periodo']);
        if (!$rango || $fechaEvaluacion < $rango['inicio'] || $fechaEvaluacion > $rango['fin']) {
            throw new RuntimeException('La fecha debe pertenecer al trimestre del bloque.');
        }

        $antesCompetencias = [];
        $stmt = mysqli_prepare($conn, 'SELECT competencia_key,incremento_final,descuento_final,puntaje_final,motivo_ajuste FROM evades_competencias WHERE evaluacion_id=? ORDER BY id');
        mysqli_stmt_bind_param($stmt, 'i', $evaluacionId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) $antesCompetencias[] = $row;
        mysqli_stmt_close($stmt);

        $sugerencias = evades_calcular_sugerencias($conn, (int)$evaluacion['colaborador_id'], $bloque['periodo']);
        $sugeridoPorKey = [];
        foreach ($sugerencias as $s) $sugeridoPorKey[$s['competencia_key']] = $s;
        $entradaPorKey = [];
        foreach ($filasEntrada as $fila) $entradaPorKey[(string)($fila['competencia_key'] ?? '')] = $fila;

        $filasFinales = [];
        $puntajeTotal = 0;
        $motivos = [];
        foreach (evades_competencias() as $key => $meta) {
            if (!isset($entradaPorKey[$key], $sugeridoPorKey[$key])) {
                throw new RuntimeException('La evaluación debe contener las diez competencias EVADES.');
            }
            $entrada = $entradaPorKey[$key];
            $sugerida = $sugeridoPorKey[$key];
            $incremento = (int)($entrada['incremento_final'] ?? 0);
            $descuento = (int)($entrada['descuento_final'] ?? 0);
            $motivo = trim((string)($entrada['motivo_ajuste'] ?? ''));
            if (!in_array($incremento, evades_incrementos_validos(), true)) {
                throw new RuntimeException('Incremento inválido en "' . $meta['label'] . '".');
            }
            if (!in_array($descuento, evades_descuentos_validos(), true)) {
                throw new RuntimeException('Descuento inválido en "' . $meta['label'] . '".');
            }
            $tieneAuto = evades_tiene_automatizacion($key);
            $autoIncremento = (int)($sugerida['auto_incremento'] ?? 0);
            $autoDescuento = (int)($sugerida['auto_descuento'] ?? 0);
            if ($tieneAuto && ($incremento !== $autoIncremento || $descuento !== $autoDescuento) && $motivo === '') {
                throw new RuntimeException('Explica el ajuste realizado en "' . $meta['label'] . '".');
            }
            $puntaje = max(0, min(10, 6 + $incremento - $descuento));
            $puntajeTotal += $puntaje;
            if ($motivo !== '') $motivos[] = $meta['label'] . ': ' . $motivo;
            $filasFinales[] = [
                'competencia_key' => $key,
                'tipo' => $meta['tipo'],
                'auto_incremento' => $sugerida['auto_incremento'],
                'auto_descuento' => $sugerida['auto_descuento'],
                'incremento_final' => $incremento,
                'descuento_final' => $descuento,
                'puntaje_final' => $puntaje,
                'motivo_ajuste' => $motivo !== '' ? $motivo : null,
                'evidencia_json' => json_encode(evades_evidencia_para_snapshot($sugerida), JSON_UNESCAPED_UNICODE),
            ];
        }

        $clasificacion = evades_clasificacion($puntajeTotal);
        $puntajeAnterior = $evaluacion['puntaje_anterior'] !== null ? (int)$evaluacion['puntaje_anterior'] : null;
        $variacion = ($puntajeAnterior !== null && $puntajeAnterior > 0)
            ? round((($puntajeTotal - $puntajeAnterior) / $puntajeAnterior) * 100, 2)
            : null;
        $antes = [
            'fecha_evaluacion' => $evaluacion['fecha_evaluacion'],
            'puntaje_total' => (int)$evaluacion['puntaje_total'],
            'clasificacion' => $evaluacion['clasificacion'],
            'fortalezas' => $evaluacion['fortalezas'] ?? '',
            'aspectos_mejora' => $evaluacion['aspectos_mejora'] ?? '',
            'plan_accion' => $evaluacion['plan_accion'] ?? '',
            'competencias' => $antesCompetencias,
        ];
        $despues = [
            'fecha_evaluacion' => $fechaEvaluacion,
            'puntaje_total' => $puntajeTotal,
            'clasificacion' => $clasificacion,
            'fortalezas' => $fortalezas,
            'aspectos_mejora' => $aspectos,
            'plan_accion' => $plan,
            'competencias' => array_map(function ($f) {
                return [
                    'competencia_key' => $f['competencia_key'],
                    'incremento_final' => $f['incremento_final'],
                    'descuento_final' => $f['descuento_final'],
                    'puntaje_final' => $f['puntaje_final'],
                    'motivo_ajuste' => $f['motivo_ajuste'],
                ];
            }, $filasFinales),
        ];
        if ($antes === $despues) {
            mysqli_commit($conn);
            return [
                'id' => $evaluacionId,
                'bloque_id' => $bloqueId,
                'estado' => $bloque['estado'],
                'version' => (int)$bloque['version'],
                'puntaje_total' => $puntajeTotal,
                'clasificacion' => $clasificacion,
                'modificaciones_creadas' => 0,
            ];
        }

        $stmt = mysqli_prepare(
            $conn,
            'UPDATE evades_evaluaciones SET fecha_evaluacion=?,puntaje_total=?,clasificacion=?,variacion_pct=?,fortalezas=?,aspectos_mejora=?,plan_accion=?,version=version+1 WHERE id=? AND bloque_id=?'
        );
        mysqli_stmt_bind_param($stmt, 'sisdsssii', $fechaEvaluacion, $puntajeTotal, $clasificacion, $variacion, $fortalezas, $aspectos, $plan, $evaluacionId, $bloqueId);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, 'DELETE FROM evades_competencias WHERE evaluacion_id=?');
        mysqli_stmt_bind_param($stmt, 'i', $evaluacionId);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $stmtComp = mysqli_prepare(
            $conn,
            'INSERT INTO evades_competencias
                (evaluacion_id,competencia_key,tipo,base,auto_incremento,auto_descuento,incremento_final,descuento_final,puntaje_final,motivo_ajuste,evidencia_json)
             VALUES (?,?,?,6,?,?,?,?,?,?,?)'
        );
        foreach ($filasFinales as $fila) {
            mysqli_stmt_bind_param(
                $stmtComp,
                'issiiiiiss',
                $evaluacionId, $fila['competencia_key'], $fila['tipo'], $fila['auto_incremento'], $fila['auto_descuento'],
                $fila['incremento_final'], $fila['descuento_final'], $fila['puntaje_final'], $fila['motivo_ajuste'], $fila['evidencia_json']
            );
            if (!mysqli_stmt_execute($stmtComp)) throw new RuntimeException(mysqli_stmt_error($stmtComp));
        }
        mysqli_stmt_close($stmtComp);

        $estadoAnterior = $bloque['estado'];
        $stmt = mysqli_prepare($conn, "UPDATE evades_bloques SET estado='modificado',modificado_at=NOW(),version=version+1 WHERE id=? AND version=?");
        mysqli_stmt_bind_param($stmt, 'ii', $bloqueId, $versionEsperada);
        if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
            throw new RuntimeException('El bloque fue actualizado en otra sesión. Recarga antes de guardar.');
        }
        mysqli_stmt_close($stmt);

        if ($estadoAnterior !== 'modificado') {
            $estadoNuevo = 'modificado';
            $contexto = 'Primera modificación guardada';
            $stmt = mysqli_prepare($conn, 'INSERT INTO evades_bloques_estados (bloque_id,estado_anterior,estado_nuevo,usuario_id,contexto) VALUES (?,?,?,?,?)');
            mysqli_stmt_bind_param($stmt, 'issis', $bloqueId, $estadoAnterior, $estadoNuevo, $actorId, $contexto);
            if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        }

        $antesJson = json_encode($antes, JSON_UNESCAPED_UNICODE);
        $despuesJson = json_encode($despues, JSON_UNESCAPED_UNICODE);
        $motivoAuditoria = $motivos ? implode(' | ', $motivos) : null;
        $colaboradorId = (int)$evaluacion['colaborador_id'];
        $stmt = mysqli_prepare($conn, 'INSERT INTO evades_modificaciones (bloque_id,evaluacion_id,colaborador_id,usuario_id,motivo,antes_json,despues_json) VALUES (?,?,?,?,?,?,?)');
        mysqli_stmt_bind_param($stmt, 'iiiisss', $bloqueId, $evaluacionId, $colaboradorId, $actorId, $motivoAuditoria, $antesJson, $despuesJson);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        return [
            'id' => $evaluacionId,
            'bloque_id' => $bloqueId,
            'estado' => 'modificado',
            'version' => $versionEsperada + 1,
            'puntaje_total' => $puntajeTotal,
            'clasificacion' => $clasificacion,
            'modificaciones_creadas' => 1,
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/** Valida todas las fichas y deja el bloque definitivamente en solo lectura. */
function evades_cerrar_bloque($conn, $bloqueId, $versionEsperada, $actorId) {
    $bloqueId = (int)$bloqueId;
    $versionEsperada = (int)$versionEsperada;
    $actorId = (int)$actorId;
    if ($bloqueId <= 0 || $versionEsperada <= 0 || $actorId <= 0) {
        throw new RuntimeException('Datos de cierre EVADES incompletos.');
    }

    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT b.*,u.rol AS actor_rol
               FROM evades_bloques b
               JOIN usuarios u ON u.id=? AND u.estado='Activo'
              WHERE b.id=? LIMIT 1 FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $actorId, $bloqueId);
        mysqli_stmt_execute($stmt);
        $bloque = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$bloque) throw new RuntimeException('Bloque EVADES no encontrado.');
        if ($bloque['actor_rol'] !== 'Administrador') {
            throw new RuntimeException('Solo un administrador puede cerrar el bloque EVADES.');
        }
        if ($bloque['estado'] === 'cerrado') {
            mysqli_commit($conn);
            $bloque['version'] = (int)$bloque['version'];
            unset($bloque['actor_rol']);
            return $bloque;
        }
        if ((int)$bloque['version'] !== $versionEsperada) {
            throw new RuntimeException('El bloque fue actualizado en otra sesión. Recarga antes de cerrar.');
        }

        $stmt = mysqli_prepare($conn, 'SELECT * FROM evades_evaluaciones WHERE bloque_id=? ORDER BY id FOR UPDATE');
        mysqli_stmt_bind_param($stmt, 'i', $bloqueId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $evaluaciones = [];
        while ($row = mysqli_fetch_assoc($res)) $evaluaciones[] = $row;
        mysqli_stmt_close($stmt);
        if (!$evaluaciones) throw new RuntimeException('El bloque no contiene evaluaciones para cerrar.');
        if (count($evaluaciones) !== (int)$bloque['total_colaboradores']) {
            throw new RuntimeException('La nómina del bloque está incompleta.');
        }

        $catalogo = evades_competencias();
        foreach ($evaluaciones as $evaluacion) {
            if (trim((string)$evaluacion['fortalezas']) === '' || trim((string)$evaluacion['aspectos_mejora']) === '' || trim((string)$evaluacion['plan_accion']) === '') {
                throw new RuntimeException('Completa fortalezas, aspectos de mejora y plan de acción de ' . $evaluacion['colaborador_nombre'] . '.');
            }
            $evaluacionId = (int)$evaluacion['id'];
            $stmt = mysqli_prepare($conn, 'SELECT competencia_key,auto_incremento,auto_descuento,incremento_final,descuento_final,puntaje_final,motivo_ajuste FROM evades_competencias WHERE evaluacion_id=? ORDER BY id');
            mysqli_stmt_bind_param($stmt, 'i', $evaluacionId);
            mysqli_stmt_execute($stmt);
            $resComp = mysqli_stmt_get_result($stmt);
            $competencias = [];
            while ($comp = mysqli_fetch_assoc($resComp)) $competencias[] = $comp;
            mysqli_stmt_close($stmt);
            if (count($competencias) !== 10) {
                throw new RuntimeException('La evaluación de ' . $evaluacion['colaborador_nombre'] . ' no contiene las diez competencias.');
            }
            $suma = 0;
            foreach ($competencias as $comp) {
                $key = $comp['competencia_key'];
                if (!isset($catalogo[$key])) throw new RuntimeException('Competencia EVADES desconocida.');
                $suma += (int)$comp['puntaje_final'];
                $meta = $catalogo[$key];
                $tieneAuto = $meta['rec_competencia'] !== null || $meta['inc_competencia'] !== null || $key === 'autonomia';
                $cambioAuto = (int)$comp['incremento_final'] !== (int)($comp['auto_incremento'] ?? 0)
                    || (int)$comp['descuento_final'] !== (int)($comp['auto_descuento'] ?? 0);
                if ($tieneAuto && $cambioAuto && trim((string)$comp['motivo_ajuste']) === '') {
                    throw new RuntimeException('Falta justificar un ajuste automático de ' . $evaluacion['colaborador_nombre'] . '.');
                }
            }
            if ($suma !== (int)$evaluacion['puntaje_total']) {
                throw new RuntimeException('El puntaje total de ' . $evaluacion['colaborador_nombre'] . ' es inconsistente.');
            }
        }

        $estadoAnterior = $bloque['estado'];
        $stmt = mysqli_prepare($conn, "UPDATE evades_bloques SET estado='cerrado',cerrado_at=NOW(),cerrado_por=?,version=version+1 WHERE id=? AND version=?");
        mysqli_stmt_bind_param($stmt, 'iii', $actorId, $bloqueId, $versionEsperada);
        if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
            throw new RuntimeException('El bloque fue actualizado en otra sesión. Recarga antes de cerrar.');
        }
        mysqli_stmt_close($stmt);

        $estadoNuevo = 'cerrado';
        $contexto = 'Bloque cerrado y bloqueado para edición';
        $stmt = mysqli_prepare($conn, 'INSERT INTO evades_bloques_estados (bloque_id,estado_anterior,estado_nuevo,usuario_id,contexto) VALUES (?,?,?,?,?)');
        mysqli_stmt_bind_param($stmt, 'issis', $bloqueId, $estadoAnterior, $estadoNuevo, $actorId, $contexto);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        return [
            'id' => $bloqueId,
            'estado' => 'cerrado',
            'version' => $versionEsperada + 1,
            'cerrado_por' => $actorId,
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}

/** Revisión administrativa: marca toda la nómina revisada y cierra el bloque. */
function evades_revisar_y_cerrar_bloque($conn, $bloqueId, $actorId) {
    $bloqueId = (int)$bloqueId;
    $actorId = (int)$actorId;
    if ($bloqueId <= 0 || $actorId <= 0) throw new RuntimeException('Datos de revisión EVADES incompletos.');

    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare($conn,
            "SELECT b.*,u.rol AS actor_rol FROM evades_bloques b
              JOIN usuarios u ON u.id=? AND u.estado='Activo' WHERE b.id=? LIMIT 1 FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $actorId, $bloqueId);
        mysqli_stmt_execute($stmt);
        $bloque = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$bloque) throw new RuntimeException('Bloque EVADES no encontrado.');
        if ($bloque['actor_rol'] !== 'Administrador') throw new RuntimeException('Solo un administrador puede revisar y cerrar el bloque EVADES.');
        if ($bloque['estado'] === 'cerrado') {
            mysqli_commit($conn);
            return ['id' => $bloqueId, 'estado' => 'cerrado', 'revisadas' => 0];
        }

        $stmt = mysqli_prepare($conn, 'UPDATE evades_evaluaciones SET revisado_at=COALESCE(revisado_at,NOW()),revisado_por=COALESCE(revisado_por,?) WHERE bloque_id=?');
        mysqli_stmt_bind_param($stmt, 'ii', $actorId, $bloqueId);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        $revisadas = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        $estadoAnterior = $bloque['estado'];
        $stmt = mysqli_prepare($conn, "UPDATE evades_bloques SET estado='cerrado',revisado_at=COALESCE(revisado_at,NOW()),cerrado_at=NOW(),cerrado_por=?,version=version+1 WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'ii', $actorId, $bloqueId);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $estadoNuevo = 'cerrado';
        $contexto = 'Bloque revisado y cerrado por Administración; toda la nómina fue marcada como revisada';
        $stmt = mysqli_prepare($conn, 'INSERT INTO evades_bloques_estados (bloque_id,estado_anterior,estado_nuevo,usuario_id,contexto) VALUES (?,?,?,?,?)');
        mysqli_stmt_bind_param($stmt, 'issis', $bloqueId, $estadoAnterior, $estadoNuevo, $actorId, $contexto);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        return ['id' => $bloqueId, 'estado' => 'cerrado', 'revisadas' => $revisadas];
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}
