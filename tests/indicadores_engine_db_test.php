<?php

require_once(__DIR__ . '/../includes/db.php');
require_once(__DIR__ . '/../includes/indicadores_catalogo.php');
require_once(__DIR__ . '/../includes/indicadores_engine.php');

$TOTAL = 0; $FALLOS = 0;

function ok($cond, $msg) {
    global $TOTAL, $FALLOS;
    $TOTAL++;
    if ($cond) echo "  ok    $msg\n";
    else { $FALLOS++; echo "  FALLA $msg\n"; }
}
function eq($actual, $esperado, $msg) {
    ok($actual === $esperado, $msg . '  (esperado: ' . var_export($esperado, true)
        . ', obtenido: ' . var_export($actual, true) . ')');
}

echo "\n── dispatcher ind_calcular_automatico ──────────────────────\n";
ok(function_exists('ind_calcular_automatico'), 'existe el dispatcher');
eq(ind_calcular_automatico($conn, 'G1.2', '2026-06', 'TEAM A'), null, 'indicador sin fuente_automatica devuelve null');
eq(ind_calcular_automatico($conn, 'NO-EXISTE', '2026-06', 'TEAM A'), null, 'codigo inexistente devuelve null');

mysqli_begin_transaction($conn);
try {
    $sufijo = bin2hex(random_bytes(4));
    $periodo = '2037-05';

    // ── Fixture: dos colaboradores, uno por team, ambos Asistente de Estiba ──
    $stmt = mysqli_prepare($conn, "INSERT INTO colaboradores (codigo,nombre,funcion_principal,tipo_funcion,cuadrilla,activo) VALUES (?,?, 'ASISTENTE DE ESTIBA','TALLY CALIFICADO','G1 TEAM A',1)");
    $codigoA = "IND$sufijo" . 'A'; $nombreA = "Fixture Ind A $sufijo";
    mysqli_stmt_bind_param($stmt, 'ss', $codigoA, $nombreA);
    mysqli_stmt_execute($stmt);
    $colabA = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($conn, "INSERT INTO colaboradores (codigo,nombre,funcion_principal,tipo_funcion,cuadrilla,activo) VALUES (?,?, 'ASISTENTE DE ESTIBA','TALLY CALIFICADO','TEAM B',1)");
    $codigoB = "IND$sufijo" . 'B'; $nombreB = "Fixture Ind B $sufijo";
    mysqli_stmt_bind_param($stmt, 'ss', $codigoB, $nombreB);
    mysqli_stmt_execute($stmt);
    $colabB = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    echo "\n── ind_auto_g14 (reincidencia grupal) ──────────────────────\n";
    // TEAM A: 2 incidencias "Errores de pedeteo" (reincidencia) + 1 "Otro tipo" -> num=2, den=3
    foreach (['Errores de pedeteo', 'Errores de pedeteo', 'Otro tipo'] as $punto) {
        $stmt = mysqli_prepare($conn, "INSERT INTO incidencias (colaborador_id,colaborador_nombre,punto_mejorar,competencia,impacto,coordinador,turno,fecha,zona_trabajo,detalle) VALUES (?,?,?,'x','bajo','Test','dia',?,'Muelle 1','fixture')");
        $fecha = '2037-05-10';
        mysqli_stmt_bind_param($stmt, 'isss', $colabA, $nombreA, $punto, $fecha);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    $g14 = ind_calcular_automatico($conn, 'G1.4', $periodo, 'TEAM A');
    eq($g14['numerador'], 2.0, 'G1.4 numerador cuenta las filas de tipo repetido');
    eq($g14['denominador'], 3.0, 'G1.4 denominador cuenta el total de incidencias del team');
    $g14b = ind_calcular_automatico($conn, 'G1.4', $periodo, 'TEAM B');
    eq($g14b['numerador'], 0.0, 'G1.4 sin incidencias en TEAM B es 0 (no null: se sabe que no hubo)');
    eq($g14b['denominador'], 0.0, 'G1.4 denominador tambien 0');

    echo "\n── ind_auto_g22 (cumplimiento de capacitaciones) ───────────\n";
    foreach (['realizada', 'realizada', 'programada'] as $estado) {
        $stmt = mysqli_prepare($conn, "INSERT INTO capacitaciones (titulo,fecha,hora,coordinador,estado) VALUES ('Fixture','2037-05-15','08:00:00','Test',?)");
        mysqli_stmt_bind_param($stmt, 's', $estado);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    $g22 = ind_calcular_automatico($conn, 'G2.2', $periodo, null);
    eq($g22['numerador'], 2.0, 'G2.2 cuenta solo las capacitaciones realizadas del mes');
    eq($g22['denominador'], 4.0, 'G2.2 denominador fijo = 4 (programacion mensual)');

    echo "\n── ind_auto_g31 / g32 / g33 (reportes de inspeccion) ───────\n";
    $criteriosConEpp = json_encode([
        ['item' => 'Uso de Epps en la zona', 'estado' => 'no_conforme', 'observaciones' => ''],
        ['item' => 'Señalización', 'estado' => 'conforme', 'observaciones' => ''],
    ]);
    $stmt = mysqli_prepare($conn, "INSERT INTO reporte_inspeccion (tally_id,tally_nombre,zona_trabajo,fecha,inspector,criterios,accion_fecha) VALUES (?,?, 'Muelle 1','2037-05-12','Test',?, '2037-05-13 10:00:00')");
    mysqli_stmt_bind_param($stmt, 'iss', $colabA, $nombreA, $criteriosConEpp);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $criteriosOk = json_encode([
        ['item' => 'Uso de Epps en la zona', 'estado' => 'conforme', 'observaciones' => ''],
    ]);
    $stmt = mysqli_prepare($conn, "INSERT INTO reporte_inspeccion (tally_id,tally_nombre,zona_trabajo,fecha,inspector,criterios) VALUES (?,?, 'Muelle 1','2037-05-14','Test',?)");
    mysqli_stmt_bind_param($stmt, 'iss', $colabA, $nombreA, $criteriosOk);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $g31 = ind_calcular_automatico($conn, 'G3.1', $periodo, 'TEAM A');
    eq($g31['numerador'], 2.0, 'G3.1 cuenta los 2 reportes del team en el mes');

    $g32 = ind_calcular_automatico($conn, 'G3.2', $periodo, 'TEAM A');
    eq($g32['numerador'], 1.0, 'G3.2 numerador: reportes con accion_fecha');
    eq($g32['denominador'], 1.0, 'G3.2 denominador: reportes con algun criterio no_conforme');

    $g33 = ind_calcular_automatico($conn, 'G3.3', $periodo, 'TEAM A');
    eq($g33['numerador'], 1.0, 'G3.3 numerador: reportes con EPP no_conforme');
    eq($g33['denominador'], 2.0, 'G3.3 denominador: total de reportes del mes');

    echo "\n── ind_auto_g11 (charlas pre-operativas, solo numerador) ───\n";
    $stmt = mysqli_prepare($conn, "INSERT INTO asistencias_preoperativas (tema,tipo_reunion,capacitador,turno,fecha,coordinador) VALUES ('Fixture','charla_seguridad','Test','dia','2037-05-11','Test')");
    mysqli_stmt_execute($stmt);
    $asistId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    $stmt = mysqli_prepare($conn, "INSERT INTO asistencias_participantes (asistencia_id,colaborador_id,colaborador_nombre,estado) VALUES (?,?,?, 'asistio')");
    mysqli_stmt_bind_param($stmt, 'iis', $asistId, $colabA, $nombreA);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $g11 = ind_calcular_automatico($conn, 'G1.1', $periodo, 'TEAM A');
    eq($g11['numerador'], 1.0, 'G1.1 numerador cuenta las charlas del team en el mes');
    eq($g11['denominador'], null, 'G1.1 denominador queda null: se captura a mano');

    // El KPI cuenta CHARLAS, no asistentes. Con un solo participante por charla
    // las dos lecturas dan el mismo numero, asi que el fixture necesita una
    // charla con VARIOS participantes del mismo team: si el provider contara
    // filas de asistencia, esta charla sumaria 3 en vez de 1.
    $stmt = mysqli_prepare($conn, "INSERT INTO asistencias_preoperativas (tema,tipo_reunion,capacitador,turno,fecha,coordinador) VALUES ('Fixture multitudinaria','charla_seguridad','Test','dia','2037-05-12','Test')");
    mysqli_stmt_execute($stmt);
    $asistMulti = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    foreach (['Uno', 'Dos', 'Tres'] as $i => $sufijoPart) {
        $codigoP = "IN$sufijo$i";
        $nombreP = "Fixture Part $sufijoPart $sufijo";
        $stmt = mysqli_prepare($conn, "INSERT INTO colaboradores (codigo,nombre,funcion_principal,tipo_funcion,cuadrilla,activo) VALUES (?,?, 'ASISTENTE DE ESTIBA','TALLY CALIFICADO','TEAM A',1)");
        mysqli_stmt_bind_param($stmt, 'ss', $codigoP, $nombreP);
        mysqli_stmt_execute($stmt);
        $colabP = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
        $stmt = mysqli_prepare($conn, "INSERT INTO asistencias_participantes (asistencia_id,colaborador_id,colaborador_nombre,estado) VALUES (?,?,?, 'asistio')");
        mysqli_stmt_bind_param($stmt, 'iis', $asistMulti, $colabP, $nombreP);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    $g11multi = ind_calcular_automatico($conn, 'G1.1', $periodo, 'TEAM A');
    eq($g11multi['numerador'], 2.0, 'G1.1 cuenta 2 charlas (1 + 1 con tres asistentes), no 4 participantes');

    echo "\n── ind_auto_g41 / g42 (participacion y analisis de propuestas) ─\n";
    $stmt = mysqli_prepare($conn, "INSERT INTO sugerencias_tallyman (canal,colaborador_id,colaborador_nombre,detalle,viabilidad,puntaje_at) VALUES ('propuesta',?,?, 'fixture', 7, '2037-05-20 00:00:00')");
    mysqli_stmt_bind_param($stmt, 'is', $colabA, $nombreA);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    $stmt = mysqli_prepare($conn, "INSERT INTO sugerencias_tallyman (canal,colaborador_id,colaborador_nombre,detalle) VALUES ('propuesta',?,?, 'fixture sin calificar')");
    mysqli_stmt_bind_param($stmt, 'is', $colabA, $nombreA);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    // Forzar la fecha de created_at al periodo de fixture (no acepta parametro directo, created_at es DEFAULT CURRENT_TIMESTAMP)
    mysqli_query($conn, "UPDATE sugerencias_tallyman SET created_at='2037-05-20 00:00:00' WHERE colaborador_id=$colabA");

    $g41 = ind_calcular_automatico($conn, 'G4.1', $periodo, 'TEAM A');
    eq($g41['numerador'], 2.0, 'G4.1 cuenta las 2 propuestas del team en el mes');

    $g42 = ind_calcular_automatico($conn, 'G4.2', $periodo, 'TEAM A');
    eq($g42['numerador'], 1.0, 'G4.2 cuenta solo la propuesta ya calificada (puntaje_at no nulo)');
    eq($g42['denominador'], 2.0, 'G4.2 denominador: total de propuestas recibidas');

    echo "\n── ind_team_de_coordinador ──────────────────────────────────\n";
    $stmt = mysqli_prepare($conn, "INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, 'x', 'Coordinador')");
    $nombreCoord = "Fixture Coord $sufijo"; $emailCoord = "fixture_$sufijo@example.test";
    mysqli_stmt_bind_param($stmt, 'ss', $nombreCoord, $emailCoord);
    mysqli_stmt_execute($stmt);
    $coordId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    mysqli_query($conn, "UPDATE colaboradores SET coordinador_id=$coordId WHERE id=$colabA");

    eq(ind_team_de_coordinador($conn, $coordId), 'TEAM A', 'el team del coordinador se deriva de sus colaboradores a cargo');
    eq(ind_team_de_coordinador($conn, 999999), null, 'coordinador sin colaboradores a cargo devuelve null');

    echo "\n── ind_auto_g21 (EVADES dentro de plazo) ────────────────────\n";
    $trimestreG21 = ind_trimestre_de_periodo($periodo);
    $stmt = mysqli_prepare($conn, "INSERT INTO evades_evaluaciones (colaborador_id,colaborador_nombre,coordinador_nombre,periodo,fecha_evaluacion,clasificacion) VALUES (?,?, 'Test',?, '2037-05-15','Cumple')");
    mysqli_stmt_bind_param($stmt, 'iss', $colabA, $nombreA, $trimestreG21);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $denG21A = (float)ind_conteo_asistentes_estiba($conn, 'TEAM A');
    $g21 = ind_calcular_automatico($conn, 'G2.1', $periodo, 'TEAM A');
    eq($g21['numerador'], 1.0, 'G2.1 numerador cuenta la evaluacion EVADES del trimestre para el team');
    eq($g21['denominador'], $denG21A, 'G2.1 denominador es el headcount activo de Asistente de Estiba del team');

    $g21b = ind_calcular_automatico($conn, 'G2.1', $periodo, 'TEAM B');
    eq($g21b['numerador'], 0.0, 'G2.1 sin evaluaciones EVADES en TEAM B es 0');

    echo "\n── ind_auto_g23 (tiempo de respuesta de incidencias) ───────\n";
    // 2 incidencias de TEAM A en mayo/2037: gaps de 2 y 5 dias entre created_at y declaracion_uploaded_at -> promedio 3.5
    // (gaps que no dividen exacto: PHP "/" devuelve int cuando el resultado es entero, y aqui se espera float)
    $filasG23 = [
        ['2037-05-01', '2037-05-01 08:00:00', '2037-05-03 08:00:00'],
        ['2037-05-05', '2037-05-05 08:00:00', '2037-05-10 08:00:00'],
    ];
    foreach ($filasG23 as [$fecha, $creado, $declarado]) {
        $stmt = mysqli_prepare($conn, "INSERT INTO incidencias (colaborador_id,colaborador_nombre,punto_mejorar,competencia,impacto,coordinador,turno,fecha,zona_trabajo,detalle,created_at,declaracion_uploaded_at) VALUES (?,?, 'x','x','bajo','Test','dia',?,'Muelle 1','fixture g23',?,?)");
        mysqli_stmt_bind_param($stmt, 'issss', $colabA, $nombreA, $fecha, $creado, $declarado);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    $g23 = ind_calcular_automatico($conn, 'G2.3', $periodo, 'TEAM A');
    eq($g23['numerador'], 3.5, 'G2.3 numerador: promedio de dias entre created_at y declaracion_uploaded_at');
    eq($g23['denominador'], null, 'G2.3 denominador queda null (indicador tipo Promedio)');

    echo "\n── ind_auto_g25 (EPT realizadas) ────────────────────────────\n";
    foreach (['2037-05-02', '2037-05-20'] as $fechaEd) {
        $stmt = mysqli_prepare($conn, "INSERT INTO evaluacion_desempeno (colaborador_id,colaborador_nombre,turno,fecha,evaluador,criterios) VALUES (?,?, 'dia',?, 'Test','[]')");
        mysqli_stmt_bind_param($stmt, 'iss', $colabA, $nombreA, $fechaEd);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    $g25 = ind_calcular_automatico($conn, 'G2.5', $periodo, 'TEAM A');
    eq($g25['numerador'], 2.0, 'G2.5 cuenta las 2 evaluaciones de desempeno del team en el mes');
    eq($g25['denominador'], null, 'G2.5 denominador queda null: se captura a mano');

    mysqli_rollback($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo "  FALLA excepcion inesperada: " . $e->getMessage() . "\n";
    $FALLOS++;
}

echo "\n══════════════════════════════════════════════════════════\n";
echo ($FALLOS === 0 ? "TODO OK" : "HAY FALLOS") . ": $TOTAL aserciones, $FALLOS fallidas\n\n";
exit($FALLOS === 0 ? 0 : 1);
