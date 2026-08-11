<?php

require_once(__DIR__ . '/../includes/db.php');
require_once(__DIR__ . '/../includes/evades_bloques.php');

$TOTAL = 0;
$FALLOS = 0;

function ok($cond, $msg) {
    global $TOTAL, $FALLOS;
    $TOTAL++;
    if ($cond) echo "  ok    $msg\n";
    else { $FALLOS++; echo "  FALLA $msg\n"; }
}

function eq($actual, $esperado, $msg) {
    ok($actual === $esperado, $msg . ' (esperado: ' . var_export($esperado, true)
        . ', obtenido: ' . var_export($actual, true) . ')');
}

function tabla_existe($conn, $tabla) {
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    mysqli_stmt_bind_param($stmt, 's', $tabla);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int)$row['n'] === 1;
}

function columna_existe($conn, $tabla, $columna) {
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    mysqli_stmt_bind_param($stmt, 'ss', $tabla, $columna);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int)$row['n'] === 1;
}

function indice_existe($conn, $tabla, $indice) {
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) n FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    mysqli_stmt_bind_param($stmt, 'ss', $tabla, $indice);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int)$row['n'] > 0;
}

echo "\n── esquema EVADES por bloques ─────────────────────────────\n";
ok(tabla_existe($conn, 'evades_bloques'), 'existe evades_bloques');
ok(tabla_existe($conn, 'evades_bloques_estados'), 'existe evades_bloques_estados');
ok(tabla_existe($conn, 'evades_modificaciones'), 'existe evades_modificaciones');
ok(columna_existe($conn, 'evades_evaluaciones', 'bloque_id'), 'evaluaciones tiene bloque_id');
ok(columna_existe($conn, 'evades_evaluaciones', 'version'), 'evaluaciones tiene version');
ok(indice_existe($conn, 'evades_bloques', 'uq_evades_bloque'), 'bloque impide duplicar coordinador, puesto y periodo');

echo "\n── nómina congelable por coordinador y puesto ─────────────\n";
eq(evades_resolver_coordinador_objetivo(['user_rol' => 'Coordinador', 'user_id' => 7], 0), 7, 'coordinador opera con su propia sesión');
eq(evades_resolver_coordinador_objetivo(['user_rol' => 'Administrador', 'user_id' => 1], 22), 22, 'administrador selecciona coordinador objetivo');
$overrideRechazado = false;
try {
    evades_resolver_coordinador_objetivo(['user_rol' => 'Coordinador', 'user_id' => 7], 8);
} catch (Throwable $e) {
    $overrideRechazado = true;
}
ok($overrideRechazado, 'coordinador no puede operar por otro coordinador');
mysqli_begin_transaction($conn);
try {
    $sufijo = bin2hex(random_bytes(4));
    $email = "evades-$sufijo@example.test";
    $stmt = mysqli_prepare($conn, "INSERT INTO usuarios (email,password,nombre,rol,estado) VALUES (?,'test','Coordinador EVADES','Coordinador','Activo')");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $coordId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $ids = [];
    foreach ([
        ['ASISTENTE DE ESTIBA', 1],
        ['ANALISTA DE TROUBLE DESK', 1],
        ['ANALISTA DE TROUBLE DESK', 0],
    ] as $i => [$puesto, $activo]) {
        $codigo = "EVT{$sufijo}{$i}";
        $nombre = "Fixture EVADES $i";
        $stmt = mysqli_prepare($conn, "INSERT INTO colaboradores (codigo,nombre,funcion_principal,tipo_funcion,cuadrilla,coordinador_id,activo) VALUES (?, ?, ?, 'TALLY CALIFICADO', 'TEST', ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sssii', $codigo, $nombre, $puesto, $coordId, $activo);
        mysqli_stmt_execute($stmt);
        $ids[] = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
    }

    $asistentes = evades_obtener_nomina($conn, $coordId, 'ASISTENTE DE ESTIBA');
    eq(array_column($asistentes, 'id'), [$ids[0]], 'incluye solo el asistente activo del coordinador');
    $cobertura = evades_obtener_cobertura_nomina($conn, $coordId, 'ASISTENTE DE ESTIBA', '2097-T4');
    eq($cobertura['resumen']['total_colaboradores'], 1, 'la previsualización calcula la cobertura de toda la nómina');
    eq($cobertura['resumen']['total_competencias'], 10, 'la cobertura considera las diez competencias');
    eq($cobertura['resumen']['sin_fuente'], 1, 'identifica Productividad sin una fuente individual');
    eq($cobertura['colaboradores'][0]['puntaje_estimado'], 60, 'proyecta el puntaje sin persistir evaluaciones');
    ok(isset($cobertura['colaboradores'][0]['cobertura']['suficiente']), 'expone cobertura por colaborador');
    $analistas = evades_obtener_nomina($conn, $coordId, 'ANALISTA DE TROUBLE DESK');
    eq(array_column($analistas, 'id'), [$ids[1]], 'excluye al analista inactivo');
} finally {
    mysqli_rollback($conn);
}

echo "\n── generación masiva transaccional ────────────────────────\n";
$sufijo = bin2hex(random_bytes(4));
$email = "evades-gen-$sufijo@example.test";
$periodoFixture = '2098-T1';
$coordGenId = 0;
$colGenId = 0;
try {
    $stmt = mysqli_prepare($conn, "INSERT INTO usuarios (email,password,nombre,rol,estado) VALUES (?,'test','Coordinador Generación','Coordinador','Activo')");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $coordGenId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $codigo = "EVG$sufijo";
    $nombre = 'Fixture Analista Generación';
    $puesto = 'ANALISTA DE TROUBLE DESK';
    $stmt = mysqli_prepare($conn, "INSERT INTO colaboradores (codigo,nombre,funcion_principal,tipo_funcion,cuadrilla,coordinador_id,activo) VALUES (?, ?, ?, 'TALLY CALIFICADO', 'TEST', ?, 1)");
    mysqli_stmt_bind_param($stmt, 'sssi', $codigo, $nombre, $puesto, $coordGenId);
    mysqli_stmt_execute($stmt);
    $colGenId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $bloque = evades_generar_bloque($conn, $coordGenId, $puesto, $periodoFixture, $coordGenId);
    eq($bloque['estado'], 'generado', 'el bloque nace generado');
    eq($bloque['total_colaboradores'], 1, 'congela una persona');
    eq(count($bloque['evaluaciones']), 1, 'crea una evaluación hija');
    eq(count($bloque['evaluaciones'][0]['competencias']), 10, 'persiste las diez competencias');
    eq($bloque['evaluaciones'][0]['puntaje_total'], 60, 'sin evidencia inicia en base 60');

    $abierto = evades_marcar_revisado($conn, $bloque['id'], $coordGenId);
    eq($abierto['estado'], 'revisado', 'la primera apertura marca revisado');
    eq($abierto['version'], 2, 'la primera apertura incrementa versión');
    $abiertoOtraVez = evades_marcar_revisado($conn, $bloque['id'], $coordGenId);
    eq($abiertoOtraVez['version'], 2, 'abrir otra vez es idempotente');

    $competenciasEditadas = [];
    foreach ($bloque['evaluaciones'][0]['competencias'] as $comp) {
        $incremento = $comp['incremento_final'];
        if ($comp['competencia_key'] === 'productividad') $incremento = 2;
        $competenciasEditadas[] = [
            'competencia_key' => $comp['competencia_key'],
            'incremento_final' => $incremento,
            'descuento_final' => $comp['descuento_final'],
            'motivo_ajuste' => '',
        ];
    }
    $payloadGuardar = [
        'bloque_id' => $bloque['id'],
        'id' => $bloque['evaluaciones'][0]['id'],
        'version' => $abierto['version'],
        'fecha_evaluacion' => '2098-03-31',
        'competencias' => $competenciasEditadas,
        'fortalezas' => 'Cumple los procedimientos establecidos.',
        'aspectos_mejora' => 'Reforzar la priorización operativa.',
        'plan_accion' => 'Revisión semanal durante el siguiente trimestre.',
    ];
    $guardado = evades_guardar_evaluacion_bloque($conn, $payloadGuardar, $coordGenId);
    eq($guardado['estado'], 'modificado', 'el primer cambio marca modificado');
    eq($guardado['version'], 3, 'guardar incrementa la versión del bloque');
    eq($guardado['puntaje_total'], 62, 'recalcula el total de la persona');
    eq($guardado['modificaciones_creadas'], 1, 'registra una modificación real');

    $conflictoDetectado = false;
    try {
        evades_guardar_evaluacion_bloque($conn, $payloadGuardar, $coordGenId);
    } catch (Throwable $e) {
        $conflictoDetectado = strpos($e->getMessage(), 'actualizado en otra sesión') !== false;
    }
    ok($conflictoDetectado, 'rechaza una versión desactualizada');

    $cerrado = evades_cerrar_bloque($conn, $bloque['id'], $guardado['version'], $coordGenId);
    eq($cerrado['estado'], 'cerrado', 'el cierre cambia el estado final');
    eq($cerrado['version'], 4, 'el cierre incrementa la versión');

    $payloadGuardar['version'] = $cerrado['version'];
    $edicionCerradaRechazada = false;
    try {
        evades_guardar_evaluacion_bloque($conn, $payloadGuardar, $coordGenId);
    } catch (Throwable $e) {
        $edicionCerradaRechazada = strpos($e->getMessage(), 'cerrado') !== false;
    }
    ok($edicionCerradaRechazada, 'un bloque cerrado no admite guardados');

    $detalleBloque = evades_obtener_bloque($conn, $bloque['id'], $coordGenId, 'Coordinador');
    eq(count($detalleBloque['evaluaciones']), 1, 'el detalle devuelve la nómina congelada');
    eq(count($detalleBloque['historial_estados']), 4, 'el detalle devuelve las cuatro transiciones');
    eq(count($detalleBloque['modificaciones']), 1, 'el detalle devuelve la auditoría de cambios');
    $listaBloques = evades_listar_bloques($conn, $coordGenId, 'Coordinador');
    eq(count($listaBloques), 1, 'el coordinador lista solo su bloque fixture');
    eq($listaBloques[0]['promedio'], 62.0, 'el resumen calcula el promedio del bloque');

    $duplicadoRechazado = false;
    try {
        evades_generar_bloque($conn, $coordGenId, $puesto, $periodoFixture, $coordGenId);
    } catch (Throwable $e) {
        $duplicadoRechazado = true;
    }
    ok($duplicadoRechazado, 'rechaza un bloque duplicado');
} finally {
    if ($coordGenId > 0) {
        mysqli_query($conn, "DELETE ec FROM evades_competencias ec JOIN evades_evaluaciones ev ON ev.id=ec.evaluacion_id JOIN evades_bloques b ON b.id=ev.bloque_id WHERE b.coordinador_id=$coordGenId AND b.periodo='$periodoFixture'");
        mysqli_query($conn, "DELETE m FROM evades_modificaciones m JOIN evades_bloques b ON b.id=m.bloque_id WHERE b.coordinador_id=$coordGenId AND b.periodo='$periodoFixture'");
        mysqli_query($conn, "DELETE h FROM evades_bloques_estados h JOIN evades_bloques b ON b.id=h.bloque_id WHERE b.coordinador_id=$coordGenId AND b.periodo='$periodoFixture'");
        mysqli_query($conn, "DELETE ev FROM evades_evaluaciones ev JOIN evades_bloques b ON b.id=ev.bloque_id WHERE b.coordinador_id=$coordGenId AND b.periodo='$periodoFixture'");
        mysqli_query($conn, "DELETE FROM evades_bloques WHERE coordinador_id=$coordGenId AND periodo='$periodoFixture'");
    }
    if ($colGenId > 0) mysqli_query($conn, "DELETE FROM colaboradores WHERE id=$colGenId");
    if ($coordGenId > 0) mysqli_query($conn, "DELETE FROM usuarios WHERE id=$coordGenId");
}

echo "\n══════════════════════════════════════════════════════════\n";
echo ($FALLOS === 0 ? 'TODO OK' : 'HAY FALLOS') . ": $TOTAL aserciones, $FALLOS fallidas\n\n";
exit($FALLOS === 0 ? 0 : 1);
