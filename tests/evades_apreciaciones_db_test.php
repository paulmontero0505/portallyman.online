<?php

require_once(__DIR__ . '/../includes/db.php');
require_once(__DIR__ . '/../includes/evades_bloques.php');
require_once(__DIR__ . '/../includes/evades_evidence.php');
$domainFile = __DIR__ . '/../includes/evades_apreciaciones.php';
if (is_file($domainFile)) require_once($domainFile);

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

function evades_test_tabla_existe($conn, $tabla) {
    $stmt = mysqli_prepare($conn, 'SELECT COUNT(*) n FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    mysqli_stmt_bind_param($stmt, 's', $tabla);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return (int)$row['n'] === 1;
}

echo "\n── esquema y dominio de apreciaciones EVADES ──────────────\n";
ok(evades_test_tabla_existe($conn, 'evades_apreciaciones'), 'existe evades_apreciaciones');
ok(function_exists('evades_guardar_apreciacion'), 'existe evades_guardar_apreciacion');
ok(function_exists('evades_listar_apreciaciones'), 'existe evades_listar_apreciaciones');
ok(function_exists('evades_evidencia_apreciaciones'), 'existe lector normalizado de apreciaciones');

if ($FALLOS === 0) {
    $sufijo = bin2hex(random_bytes(4));
    $actorId = 0;
    $colaboradorId = 0;
    $bloqueId = 0;
    $evaluacionId = 0;
    try {
        $email = "evades-apr-$sufijo@example.test";
        $stmt = mysqli_prepare($conn, "INSERT INTO usuarios (email,password,nombre,rol,estado) VALUES (?,'test','Coordinador Apreciación','Coordinador','Activo')");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $actorId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $codigo = "EVA$sufijo";
        $nombre = "Fixture Apreciación $sufijo";
        $puesto = 'ASISTENTE DE ESTIBA';
        $stmt = mysqli_prepare($conn, "INSERT INTO colaboradores (codigo,nombre,funcion_principal,tipo_funcion,cuadrilla,coordinador_id,activo) VALUES (?,?,?,'TALLY CALIFICADO','TEST',?,1)");
        mysqli_stmt_bind_param($stmt, 'sssi', $codigo, $nombre, $puesto, $actorId);
        mysqli_stmt_execute($stmt);
        $colaboradorId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $periodo = '2036-T1';
        $stmt = mysqli_prepare($conn, "INSERT INTO evades_bloques (coordinador_id,coordinador_nombre,puesto,periodo,estado,total_colaboradores,version,generado_por) VALUES (?,'Coordinador Apreciación',?,?,'revisado',1,1,?)");
        mysqli_stmt_bind_param($stmt, 'issi', $actorId, $puesto, $periodo, $actorId);
        mysqli_stmt_execute($stmt);
        $bloqueId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "INSERT INTO evades_evaluaciones (bloque_id,version,colaborador_id,colaborador_nombre,colaborador_codigo,colaborador_cargo,coordinador_id,coordinador_nombre,periodo,fecha_evaluacion,puntaje_total,clasificacion) VALUES (?,1,?,?,?,?,?,'Coordinador Apreciación',?,'2036-03-31',60,'En lo esperado')");
        mysqli_stmt_bind_param($stmt, 'iisssis', $bloqueId, $colaboradorId, $nombre, $codigo, $puesto, $actorId, $periodo);
        mysqli_stmt_execute($stmt);
        $evaluacionId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        echo "\n── guardado validado y auditable ──────────────────────────\n";
        $guardada = evades_guardar_apreciacion($conn, [
            'evaluacion_id' => $evaluacionId,
            'competencia_key' => 'productividad',
            'direccion' => 'positiva',
            'nivel' => 2,
            'descripcion' => 'Superó el promedio documentado del turno.',
        ], $actorId);
        eq($guardada['nivel'], 2, 'guarda apreciación positiva estructurada');
        eq($guardada['competencia_key'], 'productividad', 'conserva competencia evaluada');
        eq($guardada['vigente'], true, 'nace vigente');
        eq(count(evades_listar_apreciaciones($conn, $evaluacionId)), 1, 'lista la apreciación guardada');
        $evidenciaApreciacion = evades_evidencia_apreciaciones($conn, $colaboradorId, 'productividad', ['inicio' => '2036-01-01', 'fin' => '2036-03-31']);
        eq($evidenciaApreciacion['nivel'], 2, 'la apreciación vigente alimenta el motor');
        eq(count($evidenciaApreciacion['evidencia']), 1, 'normaliza la apreciación como evidencia');
        $audit = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) n FROM evades_modificaciones WHERE bloque_id=$bloqueId AND evaluacion_id=$evaluacionId"));
        eq((int)$audit['n'], 1, 'registra la apreciación en auditoría');
        $estado = mysqli_fetch_assoc(mysqli_query($conn, "SELECT estado,version FROM evades_bloques WHERE id=$bloqueId"));
        eq($estado['estado'], 'modificado', 'la apreciación marca el bloque modificado');
        eq((int)$estado['version'], 2, 'la apreciación incrementa la versión del bloque');

        $rechazaVacia = false;
        try {
            evades_guardar_apreciacion($conn, [
                'evaluacion_id' => $evaluacionId,
                'competencia_key' => 'productividad',
                'direccion' => 'positiva',
                'nivel' => 2,
                'descripcion' => ' ',
            ], $actorId);
        } catch (Throwable $e) {
            $rechazaVacia = strpos($e->getMessage(), 'descripción') !== false;
        }
        ok($rechazaVacia, 'rechaza apreciación sin descripción');

        $rechazaSeis = false;
        try {
            evades_guardar_apreciacion($conn, [
                'evaluacion_id' => $evaluacionId,
                'competencia_key' => 'productividad',
                'direccion' => 'positiva',
                'nivel' => 6,
                'descripcion' => 'Nivel inválido.',
            ], $actorId);
        } catch (Throwable $e) {
            $rechazaSeis = strpos($e->getMessage(), 'nivel') !== false;
        }
        ok($rechazaSeis, 'rechaza incremento +6');

        mysqli_query($conn, "UPDATE evades_bloques SET estado='cerrado' WHERE id=$bloqueId");
        $rechazaCerrado = false;
        try {
            evades_guardar_apreciacion($conn, [
                'evaluacion_id' => $evaluacionId,
                'competencia_key' => 'seguridad_trabajo',
                'direccion' => 'negativa',
                'impacto' => 'bajo',
                'descripcion' => 'Observación cerrada.',
            ], $actorId);
        } catch (Throwable $e) {
            $rechazaCerrado = strpos($e->getMessage(), 'cerrado') !== false;
        }
        ok($rechazaCerrado, 'bloque cerrado no admite apreciaciones');
    } finally {
        if ($evaluacionId > 0) mysqli_query($conn, "DELETE FROM evades_apreciaciones WHERE evaluacion_id=$evaluacionId");
        if ($bloqueId > 0) mysqli_query($conn, "DELETE FROM evades_modificaciones WHERE bloque_id=$bloqueId");
        if ($bloqueId > 0) mysqli_query($conn, "DELETE FROM evades_bloques_estados WHERE bloque_id=$bloqueId");
        if ($evaluacionId > 0) mysqli_query($conn, "DELETE FROM evades_evaluaciones WHERE id=$evaluacionId");
        if ($bloqueId > 0) mysqli_query($conn, "DELETE FROM evades_bloques WHERE id=$bloqueId");
        if ($colaboradorId > 0) mysqli_query($conn, "DELETE FROM colaboradores WHERE id=$colaboradorId");
        if ($actorId > 0) mysqli_query($conn, "DELETE FROM usuarios WHERE id=$actorId");
    }
}

echo "\n══════════════════════════════════════════════════════════\n";
echo ($FALLOS === 0 ? 'TODO OK' : 'HAY FALLOS') . ": $TOTAL aserciones, $FALLOS fallidas\n\n";
exit($FALLOS === 0 ? 0 : 1);
