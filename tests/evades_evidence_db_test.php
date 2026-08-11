<?php

require_once(__DIR__ . '/../includes/db.php');
$evidenceFile = __DIR__ . '/../includes/evades_evidence.php';
if (is_file($evidenceFile)) require_once($evidenceFile);
require_once(__DIR__ . '/../includes/evades_engine.php');

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

echo "\n── interfaces de evidencia EVADES ─────────────────────────\n";
$funciones = [
    'evades_evidencia_incidencias',
    'evades_evidencia_reconocimientos',
    'evades_evidencia_ept',
    'evades_evidencia_asistencia',
    'evades_evidencia_propuestas',
];
foreach ($funciones as $funcion) ok(function_exists($funcion), "existe $funcion");

if ($FALLOS === 0) {
    echo "\n── fuentes normalizadas dentro del trimestre ──────────────\n";
    mysqli_begin_transaction($conn);
    try {
        $sufijo = bin2hex(random_bytes(4));
        $codigo = "EVE$sufijo";
        $nombre = "Fixture Evidencia $sufijo";
        $puesto = 'ASISTENTE DE ESTIBA';
        $stmt = mysqli_prepare($conn, "INSERT INTO colaboradores (codigo,nombre,funcion_principal,tipo_funcion,cuadrilla,activo) VALUES (?,?,?,'TALLY CALIFICADO','TEST',1)");
        mysqli_stmt_bind_param($stmt, 'sss', $codigo, $nombre, $puesto);
        mysqli_stmt_execute($stmt);
        $colaboradorId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "INSERT INTO incidencias
            (colaborador_id,colaborador_nombre,colaborador_cargo,punto_mejorar,competencia,impacto,coordinador,turno,fecha,zona_trabajo,detalle)
            VALUES (?,?,?,'Errores de pedeteo','Texto obsoleto','moderado','Coordinador Test','dia','2037-07-10','Muelle 2','Error técnico fixture')");
        mysqli_stmt_bind_param($stmt, 'iss', $colaboradorId, $nombre, $puesto);
        mysqli_stmt_execute($stmt);
        $incidenciaId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        foreach ([4, 4, 5, 5, 5] as $i => $puntaje) {
            $criterios = json_encode([
                [
                    'categoria' => 'B',
                    'item' => 'Hace uso correcto y permanente de los Equipos de Protección Personal (EPP) requeridos para la operación.',
                    'puntaje' => $puntaje,
                    'observaciones' => '',
                ],
                [
                    'categoria' => 'C',
                    'item' => 'Demuestra disposición para apoyar a otros miembros del equipo cuando la operación lo requiere.',
                    'puntaje' => 5,
                    'observaciones' => '',
                ],
            ], JSON_UNESCAPED_UNICODE);
            $fecha = '2037-07-' . str_pad((string)(11 + $i), 2, '0', STR_PAD_LEFT);
            $totalEpt = 45;
            $stmt = mysqli_prepare($conn, "INSERT INTO evaluacion_desempeno
                (colaborador_id,colaborador_nombre,colaborador_cargo,actividad,turno,fecha,evaluador,criterios,puntaje_total)
                VALUES (?,?,?,'Operación fixture','dia',?,'Evaluador Test',?,?)");
            mysqli_stmt_bind_param($stmt, 'issssi', $colaboradorId, $nombre, $puesto, $fecha, $criterios, $totalEpt);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO reconocimientos
            (colaborador_id,colaborador_nombre,colaborador_cargo,competencia,concepto,impacto,coordinador,turno,fecha,zona_trabajo,detalle,estado)
            VALUES (?,?,?,'Seguridad en el trabajo','Reporte proactivo','excelente','Coordinador Test','dia','2037-07-18','Muelle 2','Reconocimiento fixture','aprobado')");
        mysqli_stmt_bind_param($stmt, 'iss', $colaboradorId, $nombre, $puesto);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "INSERT INTO reconocimientos
            (colaborador_id,colaborador_nombre,colaborador_cargo,competencia,concepto,impacto,coordinador,turno,fecha,zona_trabajo,detalle,estado)
            VALUES (?,?,?,'Autonomía','Apoyo autónomo','excelente','Coordinador Test','dia','2037-07-18','Muelle 2','Autonomía fixture','aprobado')");
        mysqli_stmt_bind_param($stmt, 'iss', $colaboradorId, $nombre, $puesto);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conn, "INSERT INTO sugerencias_tallyman
            (canal,colaborador_id,colaborador_nombre,colaborador_cargo,detalle,viabilidad,impacto,puntaje_comentario,puntaje_por,puntaje_at,created_at)
            VALUES ('propuesta',?,?,?,'Mejora fixture',1,'alto','Viable','Supervisor Test','2037-07-20 10:00:00','2037-07-19 10:00:00')");
        mysqli_stmt_bind_param($stmt, 'iss', $colaboradorId, $nombre, $puesto);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        foreach ([21, 22, 23] as $dia) {
            $fecha = "2037-07-$dia";
            $stmt = mysqli_prepare($conn, "INSERT INTO asistencias_preoperativas
                (tema,tipo_reunion,lugar,capacitador,turno,fecha,hora,zona_trabajo,coordinador)
                VALUES ('Charla fixture','charla_preoperativa','Sala','Capacitador','dia',?,'07:00:00','Muelle 2','Coordinador Test')");
            mysqli_stmt_bind_param($stmt, 's', $fecha);
            mysqli_stmt_execute($stmt);
            $asistenciaId = (int)mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, "INSERT INTO asistencias_participantes
                (asistencia_id,colaborador_id,colaborador_nombre,colaborador_cargo,estado)
                VALUES (?,?,?,?,'asistio')");
            mysqli_stmt_bind_param($stmt, 'iiss', $asistenciaId, $colaboradorId, $nombre, $puesto);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }

        $rango = ['inicio' => '2037-07-01', 'fin' => '2037-09-30'];
        $incidencias = evades_evidencia_incidencias($conn, $colaboradorId, 'eficiencia', $rango);
        eq(count($incidencias), 1, 'punto de mejora técnico cruza hacia Eficiencia');
        eq($incidencias[0]['id'], $incidenciaId, 'conserva el id de la fuente');
        eq($incidencias[0]['es_cruce'], true, 'marca la relación cruzada');
        eq($incidencias[0]['competencia_origen'], 'dominio_solido', 'declara competencia primaria');
        eq($incidencias[0]['competencia_destino'], 'eficiencia', 'declara competencia destino');

        $ept = evades_evidencia_ept($conn, $colaboradorId, 'seguridad_trabajo', $rango);
        eq($ept['n'], 5, 'cuenta evaluaciones EPT relevantes del trimestre');
        eq($ept['promedio'], 4.6, 'promedia solo criterios EPT mapeados');
        eq($ept['nivel'], 4, 'promedio mayor a 4.5 con cobertura suficiente propone +4');

        $reconocimientos = evades_evidencia_reconocimientos($conn, $colaboradorId, 'seguridad_trabajo', $rango);
        eq($reconocimientos['nivel'], 4, 'reconocimiento excelente aprobado propone +4');
        eq(count($reconocimientos['evidencia']), 1, 'expone reconocimiento normalizado');

        $propuestas = evades_evidencia_propuestas($conn, $colaboradorId, $rango);
        eq($propuestas['nivel'], 4, 'propuesta revisada de impacto alto propone +4');

        $asistencia = evades_evidencia_asistencia($conn, $colaboradorId, $rango);
        eq($asistencia['total'], 3, 'cuenta tres eventos asignados');
        eq($asistencia['asistio'], 3, 'cuenta las tres asistencias');
        eq($asistencia['nivel'], 2, 'asistencia perfecta mínima propone +2');

        $fuera = evades_evidencia_incidencias($conn, $colaboradorId, 'eficiencia', ['inicio' => '2037-10-01', 'fin' => '2037-12-31']);
        eq($fuera, [], 'no mezcla evidencia de otro trimestre');

        echo "\n── motor integral explicable ───────────────────────────────\n";
        $sugerencias = evades_calcular_sugerencias($conn, $colaboradorId, '2037-T3');
        $porKey = [];
        foreach ($sugerencias as $sugerencia) $porKey[$sugerencia['competencia_key']] = $sugerencia;
        eq($porKey['dominio_solido']['auto_descuento'], 4, 'incidencia técnica descuenta Dominio por matriz FI');
        eq($porKey['eficiencia']['auto_descuento'], 4, 'incidencia técnica cruza y descuenta Eficiencia');
        eq($porKey['seguridad_trabajo']['auto_incremento'], 4, 'EPT y reconocimiento toman el mejor nivel, no se suman');
        eq($porKey['autonomia']['auto_incremento'], 4, 'Autonomía nunca acumula +6 aunque tenga dos fuentes');
        eq($porKey['productividad']['cobertura'], 'sin_fuente', 'Productividad no usa indicadores grupales');
        ok(strpos($porKey['dominio_solido']['resumen_calculo'], 'Frecuencia 1') !== false, 'explica frecuencia usada en el descuento');
        ok((bool)array_filter($porKey['eficiencia']['evidencia'], fn($fila) => ($fila['es_cruce'] ?? false) === true), 'evidencia de Eficiencia identifica el cruce');
        $snapshot = evades_evidencia_para_snapshot($porKey['dominio_solido']);
        eq($snapshot[count($snapshot) - 1]['tipo'], 'calculo_motor', 'el snapshot conserva la explicación del motor');
        eq($snapshot[count($snapshot) - 1]['cobertura'], 'suficiente', 'el snapshot congela la cobertura al cerrar');
    } finally {
        mysqli_rollback($conn);
    }
}

echo "\n══════════════════════════════════════════════════════════\n";
echo ($FALLOS === 0 ? 'TODO OK' : 'HAY FALLOS') . ": $TOTAL aserciones, $FALLOS fallidas\n\n";
exit($FALLOS === 0 ? 0 : 1);
