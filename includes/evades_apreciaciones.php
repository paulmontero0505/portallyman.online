<?php

require_once(__DIR__ . '/evades_catalogo.php');
require_once(__DIR__ . '/incidencias_catalogo.php');

/** Lista apreciaciones de una evaluación, con tipos PHP normalizados. */
function evades_listar_apreciaciones($conn, $evaluacionId, $soloVigentes = true) {
    $evaluacionId = (int)$evaluacionId;
    if ($evaluacionId <= 0) return [];
    $sql = 'SELECT id,bloque_id,evaluacion_id,colaborador_id,competencia_key,direccion,nivel,impacto,descripcion,vigente,creado_por,anulado_por,anulado_at,created_at,updated_at
              FROM evades_apreciaciones WHERE evaluacion_id=?'
        . ($soloVigentes ? ' AND vigente=1' : '') . ' ORDER BY created_at,id';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $evaluacionId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $filas = [];
    while ($row = mysqli_fetch_assoc($res)) {
        foreach (['id','bloque_id','evaluacion_id','colaborador_id','creado_por','anulado_por'] as $campo) {
            $row[$campo] = $row[$campo] !== null ? (int)$row[$campo] : null;
        }
        $row['nivel'] = $row['nivel'] !== null ? (int)$row['nivel'] : null;
        $row['vigente'] = (bool)$row['vigente'];
        $filas[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $filas;
}

/**
 * Guarda una apreciación positiva (+2/+4) o negativa (impacto FI), cambia el
 * bloque a modificado y deja la misma auditoría usada por el resto de EVADES.
 */
function evades_guardar_apreciacion($conn, $payload, $actorId) {
    $actorId = (int)$actorId;
    $evaluacionId = (int)($payload['evaluacion_id'] ?? 0);
    $competenciaKey = trim((string)($payload['competencia_key'] ?? ''));
    $direccion = trim((string)($payload['direccion'] ?? ''));
    $descripcion = trim((string)($payload['descripcion'] ?? ''));
    $nivel = isset($payload['nivel']) && $payload['nivel'] !== '' ? (int)$payload['nivel'] : null;
    $impacto = trim((string)($payload['impacto'] ?? '')) ?: null;

    if ($actorId <= 0 || $evaluacionId <= 0) throw new RuntimeException('Datos de apreciación incompletos.');
    if (!isset(evades_competencias()[$competenciaKey])) throw new RuntimeException('Competencia EVADES inválida.');
    if (!in_array($direccion, ['positiva', 'negativa'], true)) throw new RuntimeException('Dirección de apreciación inválida.');
    if ($descripcion === '') throw new RuntimeException('La descripción de la apreciación es obligatoria.');
    if ($direccion === 'positiva') {
        if (!in_array($nivel, [2, 4], true)) throw new RuntimeException('El nivel positivo debe ser +2 o +4.');
        $impacto = null;
    } else {
        if ($impacto === null || !array_key_exists($impacto, inc_impactos())) {
            throw new RuntimeException('Selecciona un impacto negativo válido.');
        }
        $nivel = null;
    }

    mysqli_begin_transaction($conn);
    try {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT ev.bloque_id,ev.colaborador_id,b.estado,b.version,b.coordinador_id,u.rol actor_rol
               FROM evades_evaluaciones ev
               INNER JOIN evades_bloques b ON b.id=ev.bloque_id
               INNER JOIN usuarios u ON u.id=? AND u.estado='Activo'
              WHERE ev.id=? LIMIT 1 FOR UPDATE"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $actorId, $evaluacionId);
        mysqli_stmt_execute($stmt);
        $contexto = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$contexto) throw new RuntimeException('Evaluación EVADES no encontrada.');
        $esGlobal = in_array($contexto['actor_rol'], ['Administrador', 'Supervisor'], true);
        if (!$esGlobal && (int)$contexto['coordinador_id'] !== $actorId) {
            throw new RuntimeException('No tienes acceso a esta evaluación EVADES.');
        }
        if (!evades_bloque_editable($contexto['estado'])) {
            throw new RuntimeException('El bloque está cerrado y no admite apreciaciones.');
        }

        $bloqueId = (int)$contexto['bloque_id'];
        $colaboradorId = (int)$contexto['colaborador_id'];
        $stmt = mysqli_prepare(
            $conn,
            'INSERT INTO evades_apreciaciones
                (bloque_id,evaluacion_id,colaborador_id,competencia_key,direccion,nivel,impacto,descripcion,creado_por)
             VALUES (?,?,?,?,?,?,?,?,?)'
        );
        mysqli_stmt_bind_param(
            $stmt, 'iiississi',
            $bloqueId, $evaluacionId, $colaboradorId, $competenciaKey, $direccion,
            $nivel, $impacto, $descripcion, $actorId
        );
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        $apreciacionId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $antes = json_encode(['apreciacion' => null], JSON_UNESCAPED_UNICODE);
        $despuesData = [
            'apreciacion' => [
                'id' => $apreciacionId,
                'competencia_key' => $competenciaKey,
                'direccion' => $direccion,
                'nivel' => $nivel,
                'impacto' => $impacto,
                'descripcion' => $descripcion,
            ],
        ];
        $despues = json_encode($despuesData, JSON_UNESCAPED_UNICODE);
        $motivo = 'Apreciación EVADES: ' . $descripcion;
        $stmt = mysqli_prepare($conn, 'INSERT INTO evades_modificaciones (bloque_id,evaluacion_id,colaborador_id,usuario_id,motivo,antes_json,despues_json) VALUES (?,?,?,?,?,?,?)');
        mysqli_stmt_bind_param($stmt, 'iiiisss', $bloqueId, $evaluacionId, $colaboradorId, $actorId, $motivo, $antes, $despues);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $estadoAnterior = $contexto['estado'];
        if ($estadoAnterior !== 'modificado') {
            $estadoNuevo = 'modificado';
            $detalleEstado = 'Apreciación documentada en ' . (evades_competencias()[$competenciaKey]['label'] ?? $competenciaKey);
            $stmt = mysqli_prepare($conn, 'INSERT INTO evades_bloques_estados (bloque_id,estado_anterior,estado_nuevo,usuario_id,contexto) VALUES (?,?,?,?,?)');
            mysqli_stmt_bind_param($stmt, 'issis', $bloqueId, $estadoAnterior, $estadoNuevo, $actorId, $detalleEstado);
            if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        }

        $stmt = mysqli_prepare($conn, "UPDATE evades_bloques SET estado='modificado',modificado_at=COALESCE(modificado_at,NOW()),version=version+1 WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'i', $bloqueId);
        if (!mysqli_stmt_execute($stmt)) throw new RuntimeException(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        mysqli_commit($conn);
        $filas = evades_listar_apreciaciones($conn, $evaluacionId, false);
        foreach ($filas as $fila) {
            if ((int)$fila['id'] === $apreciacionId) return $fila;
        }
        throw new RuntimeException('No se pudo recuperar la apreciación guardada.');
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        throw $e;
    }
}
