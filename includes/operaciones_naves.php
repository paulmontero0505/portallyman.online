<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Histórico de naves · lógica de cálculo
   ───────────────────────────────────────────────────────────────────────
   Única fuente de verdad para los números del submódulo Operaciones →
   Histórico de naves. Vive aparte del endpoint para que las reglas de
   cálculo —que son la parte delicada— se puedan probar sin montar una
   petición.

   ALCANCE: sólo operación en MUELLE (ubicacion_tipo='BERTH'), desde que la
   nave empieza a operar hasta que termina y zarpa. Patio queda fuera por
   decisión de alcance: son operaciones distintas y mezclarlas distorsiona
   tanto los días de estadía como el avance.

   Fuente: operaciones.tallyman_registros. NO se usa avances_nave: está
   vacía y su turno es ENUM('Mañana','Noche'), un vocabulario que no existe
   en el catálogo portally_system.jornadas (donde el día es 'D').
   ═══════════════════════════════════════════════════════════════════════ */

/**
 * Normaliza el texto libre de `ubicacion` para poder agrupar.
 *
 * Hace falta porque el campo NO está validado contra el catálogo: los datos
 * reales ya tienen «Muelle 2» y «Berth 02» conviviendo para el mismo
 * atracadero. Sin normalizar, la combinación nave+actividad+ubicación se
 * parte en dos y el plan se cuenta dos veces (ver opn_planeado_por_nave).
 */
function opn_normalizar_ubicacion($u) {
    $s = strtoupper(trim((string)$u));
    $s = preg_replace('/\s+/', ' ', $s);
    // BERTH nn ≡ MUELLE nn — mismo atracadero, distinta forma de escribirlo.
    if (preg_match('/^(?:BERTH|MUELLE)\s*0*(\d+)$/', $s, $m)) {
        return 'MUELLE ' . (int)$m[1];
    }
    return $s;
}

/**
 * Valida un nave_id contra la base de Operaciones y devuelve su nombre.
 *
 * Vive aquí y no en cada endpoint porque tres rutas distintas escriben
 * turno_personal.nave_id y las tres deben validar igual. Como NO hay clave
 * foránea —`naves` está en otra base— esta comprobación es lo único que
 * impide guardar una referencia rota.
 *
 * @return array ['ok' => bool, 'nombre' => string|null, 'error' => string|null]
 */
function opn_validar_nave($naveId) {
    if ($naveId === null || $naveId === '' || (int)$naveId <= 0) {
        return ['ok' => true, 'nombre' => null, 'error' => null];   // sin nave es válido
    }
    $oper = conn_operaciones();
    if (!$oper) {
        // Sin la base de Operaciones no se puede verificar. Se rechaza en vez de
        // guardar a ciegas: una referencia inválida es peor que un error visible.
        return ['ok' => false, 'nombre' => null,
                'error' => 'No se pudo verificar la nave: base de Operaciones no disponible.'];
    }
    $stmt = mysqli_prepare($oper, "SELECT nombre FROM naves WHERE id = ? LIMIT 1");
    $id = (int)$naveId;
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    mysqli_close($oper);

    if (!$row) return ['ok' => false, 'nombre' => null, 'error' => 'La nave indicada no existe.'];
    return ['ok' => true, 'nombre' => $row['nombre'], 'error' => null];
}

/** Nombres de nave para un conjunto de ids. Devuelve id => nombre. */
function opn_nombres_naves(array $ids) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $oper = conn_operaciones();
    if (!$oper) return [];
    $in = implode(',', $ids);
    $r = mysqli_query($oper, "SELECT id, nombre FROM naves WHERE id IN ($in)");
    $out = [];
    while ($r && ($row = mysqli_fetch_assoc($r))) $out[(int)$row['id']] = $row['nombre'];
    mysqli_close($oper);
    return $out;
}

/** Primer y último día del mes 'YYYY-MM'. Devuelve null si el formato no calza. */
function opn_rango_mes($mes) {
    if (!preg_match('/^(\d{4})-(\d{2})$/', (string)$mes, $m)) return null;
    $y = (int)$m[1]; $mo = (int)$m[2];
    if ($mo < 1 || $mo > 12) return null;
    $desde = sprintf('%04d-%02d-01', $y, $mo);
    $hasta = date('Y-m-t', strtotime($desde));
    return [$desde, $hasta];
}

/**
 * Catálogo de jornadas desde portally_system: codigo => [nombre, activo].
 *
 * `tallyman_registros.turno` es VARCHAR libre (el validador sólo exige que no
 * venga vacío) y guarda estos códigos. Resolver la etiqueta aquí permite
 * mostrar el código crudo cuando no se reconoce, en vez de descartar la fila.
 */
function opn_jornadas(mysqli $sys) {
    $out = [];
    $r = mysqli_query($sys, "SELECT codigo, nombre, activo FROM jornadas WHERE codigo IS NOT NULL AND codigo <> '' ORDER BY orden ASC");
    if ($r) {
        while ($j = mysqli_fetch_assoc($r)) {
            $out[strtoupper($j['codigo'])] = [
                'codigo' => strtoupper($j['codigo']),
                'nombre' => $j['nombre'],
                'corta'  => opn_jornada_corta($j['nombre'], $j['codigo']),
                'activo' => (int)$j['activo'] === 1,
            ];
        }
    }
    return $out;
}

/** Etiqueta corta para la rejilla: «TURNO DÍA (07:00 - 19:00)» → «DÍA». */
function opn_jornada_corta($nombre, $codigo) {
    $n = trim((string)$nombre);
    if (preg_match('/^TURNO\s+([^\(]+)/iu', $n, $m)) return mb_strtoupper(trim($m[1]), 'UTF-8');
    if ($n !== '') return mb_strtoupper(mb_substr($n, 0, 8, 'UTF-8'), 'UTF-8');
    return strtoupper((string)$codigo);
}

/**
 * Plan total por nave.
 *
 * `planned` es UN SOLO total por nave, repetido en cada registro de muelle —
 * no un plan por actividad. Lo fija tallyman.controller.js: al guardar el
 * registro con status_act='Inicio' empuja `planned` a la nave con
 * NavesModel.mergeDatos() bajo una única clave (cantidad_total / teus /
 * vehiculos según el tipo), sobrescribiendo. El validador lo dice igual: «el
 * total contra el que se mide el avance».
 *
 * De ahí las dos reglas:
 *   · SUM(planned) está MAL: multiplica el plan por el número de registros.
 *     La nave 4 tiene 2 350 en tres registros y SUM daría 7 050.
 *   · Agrupar por actividad+ubicación TAMBIÉN está mal, por lo mismo: esa
 *     nave daría 4 700 al tener dos actividades en el mismo muelle.
 *
 * El plan correcto es el del registro que marcó 'Inicio'. Si la nave no tiene
 * ese marcador —pasa con datos migrados o ciclos incompletos— se cae al mayor
 * `planned` visto, que es el mismo número cuando se repite bien.
 *
 * @return array nave_id => ['plan' => float, 'origen' => 'inicio'|'maximo']
 */
function opn_planeado_por_nave(array $registros) {
    $inicio = [];   // nave_id => ['fecha' => ..., 'plan' => ...]
    $maximo = [];   // nave_id => plan
    foreach ($registros as $r) {
        $p = $r['planned'];
        if ($p === null || $p <= 0) continue;
        $nid = $r['nave_id'];
        if (!isset($maximo[$nid]) || $p > $maximo[$nid]) $maximo[$nid] = $p;
        if ($r['status_act'] === 'Inicio') {
            // El 'Inicio' más reciente manda: una nave puede tener más de un ciclo.
            if (!isset($inicio[$nid]) || $r['fecha_turno'] >= $inicio[$nid]['fecha']) {
                $inicio[$nid] = ['fecha' => $r['fecha_turno'], 'plan' => $p];
            }
        }
    }
    $out = [];
    foreach ($maximo as $nid => $p) {
        $out[$nid] = isset($inicio[$nid])
            ? ['plan' => $inicio[$nid]['plan'], 'origen' => 'inicio']
            : ['plan' => $p, 'origen' => 'maximo'];
    }
    return $out;
}

/**
 * Dotación por nave y turno, desde portally_system.turno_personal.
 *
 * Fase 2: existe gracias a `turno_personal.nave_id` (migración 027). Antes de
 * esa columna no había NINGÚN vínculo colaborador↔nave y este dato no se podía
 * calcular — sólo inferir, y las tres inferencias posibles daban cero.
 *
 * Devuelve nave_id => [
 *   'personas_mes' => nº de personas distintas en el mes,
 *   'por_slot'     => 'fecha|CODIGO' => ['n' => int, 'ubicaciones' => [ubic => n]],
 * ]
 *
 * Los turnos anteriores a la migración tienen nave_id NULL y simplemente no
 * aparecen: la UI los muestra como «sin trazabilidad», que es la verdad.
 */
function opn_dotacion_por_nave(mysqli $sys, array $naveIds, $desde, $hasta) {
    if (!$naveIds) return [];
    $in = implode(',', array_map('intval', $naveIds));
    $stmt = mysqli_prepare(
        $sys,
        "SELECT tp.nave_id, tp.colaborador_id, tp.ubicacion, t.fecha, j.codigo AS jornada
           FROM turno_personal tp
           JOIN turnos   t ON t.id = tp.turno_id
           JOIN jornadas j ON j.id = t.jornada_id
          WHERE tp.nave_id IN ($in) AND t.fecha BETWEEN ? AND ?"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $desde, $hasta);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $acc = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $nid  = (int)$row['nave_id'];
        $slot = $row['fecha'] . '|' . strtoupper($row['jornada']);
        $ubic = $row['ubicacion'] ?: '—';
        if (!isset($acc[$nid])) $acc[$nid] = ['personas' => [], 'por_slot' => []];
        $acc[$nid]['personas'][(int)$row['colaborador_id']] = true;
        if (!isset($acc[$nid]['por_slot'][$slot])) {
            $acc[$nid]['por_slot'][$slot] = ['personas' => [], 'ubicaciones' => []];
        }
        $acc[$nid]['por_slot'][$slot]['personas'][(int)$row['colaborador_id']] = true;
        $acc[$nid]['por_slot'][$slot]['ubicaciones'][$ubic] =
            ($acc[$nid]['por_slot'][$slot]['ubicaciones'][$ubic] ?? 0) + 1;
    }
    mysqli_stmt_close($stmt);

    $out = [];
    foreach ($acc as $nid => $d) {
        $slots = [];
        foreach ($d['por_slot'] as $k => $s) {
            arsort($s['ubicaciones']);
            $slots[$k] = ['n' => count($s['personas']), 'ubicaciones' => $s['ubicaciones']];
        }
        ksort($slots);
        $out[$nid] = ['personas_mes' => count($d['personas']), 'por_slot' => $slots];
    }
    return $out;
}

/**
 * Resumen del mes: una entrada por nave con operación en muelle.
 *
 * @param mysqli $oper Conexión a la base de Operaciones.
 * @param mysqli $sys  Conexión a portally_system (sólo para el catálogo de jornadas).
 * @param string $mes  'YYYY-MM'.
 */
function opn_resumen_mes(mysqli $oper, mysqli $sys, $mes) {
    $rango = opn_rango_mes($mes);
    if (!$rango) return ['error' => 'Mes inválido. Formato esperado YYYY-MM.'];
    list($desde, $hasta) = $rango;

    $jornadas = opn_jornadas($sys);
    $activas  = array_values(array_filter($jornadas, function ($j) { return $j['activo']; }));

    // ── 1. Naves con operación en MUELLE dentro del mes ──
    $stmt = mysqli_prepare(
        $oper,
        "SELECT DISTINCT nave_id
           FROM tallyman_registros
          WHERE ubicacion_tipo = 'BERTH' AND nave_id IS NOT NULL
            AND fecha_turno BETWEEN ? AND ?"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $desde, $hasta);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $naveIds = [];
    while ($row = mysqli_fetch_assoc($res)) $naveIds[] = (int)$row['nave_id'];
    mysqli_stmt_close($stmt);

    if (!$naveIds) {
        return ['mes' => $mes, 'desde' => $desde, 'hasta' => $hasta,
                'jornadas' => array_values($jornadas), 'naves' => []];
    }
    $inIds = implode(',', array_map('intval', $naveIds));

    // ── 2. TODOS los registros de muelle de esas naves (sin acotar al mes) ──
    // El rango completo hace falta para el inicio y cierre REALES: una nave
    // puede haber empezado el mes anterior. Los conteos sí se acotan al mes.
    $rr = mysqli_query(
        $oper,
        "SELECT r.id, r.nave_id, r.fecha_turno, r.turno, r.ubicacion, r.actividad_id,
                r.planned, r.executed, r.productivity, r.status_act, r.estado_pos,
                r.coord_entrante, r.coord_saliente, r.details,
                a.nombre AS actividad
           FROM tallyman_registros r
           LEFT JOIN tallyman_actividades a ON a.id = r.actividad_id
          WHERE r.ubicacion_tipo = 'BERTH' AND r.nave_id IN ($inIds)
          ORDER BY r.nave_id, r.fecha_turno, r.id"
    );
    $registros = [];
    while ($rr && ($r = mysqli_fetch_assoc($rr))) {
        $registros[] = [
            'id'             => (int)$r['id'],
            'nave_id'        => (int)$r['nave_id'],
            'fecha_turno'    => $r['fecha_turno'],
            'turno'          => strtoupper(trim((string)$r['turno'])),
            'ubicacion'      => $r['ubicacion'],
            'ubicacion_norm' => opn_normalizar_ubicacion($r['ubicacion']),
            'actividad_id'   => (int)$r['actividad_id'],
            'actividad'      => $r['actividad'],
            'planned'        => $r['planned'] !== null ? (float)$r['planned'] : null,
            'executed'       => (float)$r['executed'],
            'productivity'   => $r['productivity'] !== null ? (float)$r['productivity'] : null,
            'status_act'     => $r['status_act'],
            'estado_pos'     => $r['estado_pos'],
            'coord_entrante' => $r['coord_entrante'],
            'coord_saliente' => $r['coord_saliente'],
            'details'        => $r['details'],
        ];
    }

    // ── 3. Datos de cabecera de las naves ──
    $rn = mysqli_query(
        $oper,
        "SELECT n.id, n.nombre, n.muelle, n.estado, n.eta, n.etb, n.etd, t.nombre AS tipo
           FROM naves n LEFT JOIN tipos_nave t ON t.id = n.tipo_nave_id
          WHERE n.id IN ($inIds)"
    );
    $naves = [];
    while ($rn && ($n = mysqli_fetch_assoc($rn))) {
        $naves[(int)$n['id']] = [
            'id' => (int)$n['id'], 'nombre' => $n['nombre'], 'muelle' => $n['muelle'],
            'tipo' => $n['tipo'], 'estado' => $n['estado'],
            'eta' => $n['eta'], 'etb' => $n['etb'], 'etd' => $n['etd'],
        ];
    }

    $planeado = opn_planeado_por_nave($registros);
    $dotacion = opn_dotacion_por_nave($sys, $naveIds, $desde, $hasta);

    // ── 4. Agrupar registros por nave ──
    $porNave = [];
    foreach ($registros as $r) $porNave[$r['nave_id']][] = $r;

    $out = [];
    foreach ($naveIds as $nid) {
        $regs = $porNave[$nid] ?? [];
        if (!$regs) continue;
        $out[] = opn_armar_nave($naves[$nid] ?? ['id' => $nid], $regs, $planeado[$nid] ?? null,
                                $desde, $hasta, $jornadas, $activas, $dotacion[$nid] ?? null);
    }

    // Orden: por inicio de operación en el mes, más reciente primero.
    usort($out, function ($a, $b) {
        return strcmp($b['ventana']['inicio_fecha'] ?? '', $a['ventana']['inicio_fecha'] ?? '');
    });

    return ['mes' => $mes, 'desde' => $desde, 'hasta' => $hasta,
            'jornadas' => array_values($jornadas), 'naves' => $out];
}

/**
 * Construye la entrada de una nave a partir de sus registros de muelle.
 * @param array|null $plan ['plan' => float, 'origen' => 'inicio'|'maximo'] o null.
 */
function opn_armar_nave(array $nave, array $regs, $plan, $desde, $hasta, array $jornadas, array $activas, $dotacion = null) {
    $planTotal  = $plan['plan']   ?? null;
    $planOrigen = $plan['origen'] ?? null;
    // ── Ventana real de operación ──
    // Los marcadores explícitos mandan; si faltan, se cae al primer/último
    // turno con avance y se deja constancia de que fue inferido.
    $fechas = array_column($regs, 'fecha_turno');
    sort($fechas);
    $inicioFecha = $fechas[0];
    $cierreFecha = end($fechas);
    $inicioMarca = 'inferido';
    $cierreMarca = 'inferido';
    $inicioTurno = null;
    $cierreTurno = null;

    foreach ($regs as $r) {
        if ($r['status_act'] === 'Inicio' && ($inicioMarca === 'inferido' || $r['fecha_turno'] < $inicioFecha)) {
            $inicioFecha = $r['fecha_turno']; $inicioTurno = $r['turno']; $inicioMarca = 'status';
        }
    }
    foreach ($regs as $r) {
        if ($r['status_act'] === 'Culminado' && ($cierreMarca === 'inferido' || $r['fecha_turno'] > $cierreFecha)) {
            $cierreFecha = $r['fecha_turno']; $cierreTurno = $r['turno']; $cierreMarca = 'status';
        }
    }
    if ($inicioTurno === null) $inicioTurno = opn_turno_de_fecha($regs, $inicioFecha, true);
    if ($cierreTurno === null) $cierreTurno = opn_turno_de_fecha($regs, $cierreFecha, false);

    // La operación sigue abierta si nadie marcó 'Culminado'.
    $enCurso = ($cierreMarca === 'inferido');

    // ── Recorte al mes consultado ──
    // El inicio y cierre reales se muestran completos (una nave puede haber
    // empezado en mayo), pero todo lo que se cuenta se acota al mes.
    $vDesde = max($inicioFecha, $desde);
    $vHasta = min($cierreFecha, $hasta);
    if ($vDesde > $vHasta) { $vDesde = $desde; $vHasta = min($cierreFecha, $hasta); }

    $diasEstadia = (int)((strtotime($vHasta) - strtotime($vDesde)) / 86400) + 1;
    if ($diasEstadia < 1) $diasEstadia = 1;

    // ── Registros del mes ──
    $regsMes = array_values(array_filter($regs, function ($r) use ($desde, $hasta) {
        return $r['fecha_turno'] >= $desde && $r['fecha_turno'] <= $hasta;
    }));

    // ── Turnos trabajados (pares fecha+turno distintos) ──
    $slots = [];            // 'fecha|TURNO' => true
    $porFecha = [];         // fecha => [codigos]
    $desglose = [];         // codigo => n
    foreach ($regsMes as $r) {
        $k = $r['fecha_turno'] . '|' . $r['turno'];
        if (!isset($slots[$k])) {
            $slots[$k] = true;
            $desglose[$r['turno']] = ($desglose[$r['turno']] ?? 0) + 1;
        }
        $porFecha[$r['fecha_turno']][$r['turno']] = true;
    }
    $turnosTrabajados = count($slots);

    // ── Rejilla y turnos sin operación ──
    // Regla: una fecha con registros de código NO reconocido se considera
    // cubierta y no genera huecos. Reclamar un hueco donde sabemos que hubo
    // trabajo —pero no podemos mapear el código— sería afirmar de más.
    $codigosActivos = array_column($activas, 'codigo');
    $rejilla = [];
    $sinOperacion = 0;
    $fechasOpacas = 0;

    for ($t = strtotime($vDesde); $t <= strtotime($vHasta); $t += 86400) {
        $f = date('Y-m-d', $t);
        $presentes = array_keys($porFecha[$f] ?? []);
        $desconocidos = array_values(array_diff($presentes, $codigosActivos));
        $opaca = count($desconocidos) > 0;
        if ($opaca) $fechasOpacas++;

        $celdas = [];
        foreach ($codigosActivos as $cod) {
            $trabajado = isset($porFecha[$f][$cod]);
            if (!$trabajado && !$opaca) $sinOperacion++;
            $celdas[$cod] = $trabajado ? 'trabajado' : ($opaca ? 'opaco' : 'sin_operacion');
        }
        $rejilla[] = ['fecha' => $f, 'celdas' => $celdas, 'otros' => $desconocidos];
    }

    // ── Tonelaje ──
    // executed es INCREMENTAL por turno (tallyman.model.js suma los turnos
    // anteriores para el acumulado previo), así que SUM es correcto.
    $ejecutadoMes   = 0.0;
    foreach ($regsMes as $r) $ejecutadoMes += $r['executed'];
    $ejecutadoTotal = 0.0;
    foreach ($regs as $r) $ejecutadoTotal += $r['executed'];

    // El avance es de la nave completa, no del mes: el plan es su total.
    $avance = ($planTotal !== null && $planTotal > 0)
        ? round($ejecutadoTotal / $planTotal * 100, 1) : null;

    return [
        'nave'     => $nave,
        'ventana'  => [
            'inicio_fecha'  => $inicioFecha,
            'inicio_turno'  => $inicioTurno,
            'inicio_label'  => opn_label_turno($jornadas, $inicioTurno),
            'inicio_marca'  => $inicioMarca,
            'cierre_fecha'  => $enCurso ? null : $cierreFecha,
            'cierre_turno'  => $enCurso ? null : $cierreTurno,
            'cierre_label'  => $enCurso ? null : opn_label_turno($jornadas, $cierreTurno),
            'cierre_marca'  => $cierreMarca,
            'en_curso'      => $enCurso,
            'mes_desde'     => $vDesde,
            'mes_hasta'     => $vHasta,
            'empezo_antes'  => $inicioFecha < $desde,
        ],
        'dias_estadia'      => $diasEstadia,
        'turnos_trabajados' => $turnosTrabajados,
        'turnos_desglose'   => opn_desglose_labels($jornadas, $desglose),
        'turnos_sin_operacion' => $sinOperacion,
        'fechas_opacas'     => $fechasOpacas,
        'rejilla'           => $rejilla,
        'ejecutado_mes'     => round($ejecutadoMes, 2),
        'ejecutado_total'   => round($ejecutadoTotal, 2),
        'planeado'          => $planTotal !== null ? round($planTotal, 2) : null,
        'plan_origen'       => $planOrigen,
        'avance_pct'        => $avance,
        // Atracaderos normalizados: delata cuando una misma nave se registró con
        // más de una forma de escribir el muelle («Muelle 2» y «Berth 02»).
        'muelles_operados'  => array_values(array_unique(array_column($regs, 'ubicacion_norm'))),
        'linea_tiempo'      => opn_linea_tiempo($regsMes, $jornadas, $rejilla, $planTotal),
        // Dotación: null = ningún turno de esta nave tiene nave_id asignado, es
        // decir es anterior a la migración 027. Distinto de 0 personas, que
        // significaría que sí hay trazabilidad y nadie estuvo asignado.
        'dotacion'          => opn_dotacion_nave($dotacion, $jornadas),
    ];
}

/** Formatea la dotación de una nave para la UI. Null si no hay trazabilidad. */
function opn_dotacion_nave($dotacion, array $jornadas) {
    if (!$dotacion || empty($dotacion['por_slot'])) return null;

    $turnos = [];
    $picos  = [];
    foreach ($dotacion['por_slot'] as $slot => $s) {
        list($fecha, $cod) = explode('|', $slot, 2);
        $turnos[] = [
            'fecha'       => $fecha,
            'turno'       => $cod,
            'label'       => opn_label_turno($jornadas, $cod),
            'personas'    => $s['n'],
            'ubicaciones' => $s['ubicaciones'],
        ];
        $picos[] = $s['n'];
    }
    return [
        'personas_mes' => $dotacion['personas_mes'],
        'turnos'       => $turnos,
        'pico'         => $picos ? max($picos) : 0,
        'promedio'     => $picos ? round(array_sum($picos) / count($picos), 1) : 0,
    ];
}

/** Primer (o último) turno registrado en una fecha dada. */
function opn_turno_de_fecha(array $regs, $fecha, $primero) {
    $t = null;
    foreach ($regs as $r) {
        if ($r['fecha_turno'] !== $fecha) continue;
        if ($primero) { return $r['turno']; }
        $t = $r['turno'];
    }
    return $t;
}

/** Etiqueta corta de un código de turno; devuelve el código crudo si no está en el catálogo. */
function opn_label_turno(array $jornadas, $codigo) {
    if ($codigo === null || $codigo === '') return '—';
    return $jornadas[$codigo]['corta'] ?? $codigo;
}

/** Desglose de turnos con etiqueta resuelta, en el orden del catálogo. */
function opn_desglose_labels(array $jornadas, array $desglose) {
    $out = [];
    foreach ($jornadas as $cod => $j) {
        if (isset($desglose[$cod])) $out[] = ['codigo' => $cod, 'label' => $j['corta'], 'n' => $desglose[$cod], 'conocido' => true];
    }
    foreach ($desglose as $cod => $n) {
        if (!isset($jornadas[$cod])) $out[] = ['codigo' => $cod, 'label' => $cod, 'n' => $n, 'conocido' => false];
    }
    return $out;
}

/**
 * Línea de tiempo del mes: un ítem por turno trabajado, más los huecos.
 * Los huecos van como ítem propio en vez de omitirse: un turno sin avance
 * dentro de la estadía es información, no ausencia de información.
 */
function opn_linea_tiempo(array $regsMes, array $jornadas, array $rejilla, $planTotal) {
    // Agrupar por turno trabajado.
    $porSlot = [];
    foreach ($regsMes as $r) {
        $k = $r['fecha_turno'] . '|' . $r['turno'];
        if (!isset($porSlot[$k])) {
            $porSlot[$k] = ['fecha' => $r['fecha_turno'], 'turno' => $r['turno'],
                            'label' => opn_label_turno($jornadas, $r['turno']),
                            'tipo' => 'turno', 'tm' => 0.0, 'actividades' => [],
                            'productividad' => null, 'status' => [],
                            'coord_entrante' => null, 'coord_saliente' => null];
        }
        $porSlot[$k]['tm'] += $r['executed'];
        if ($r['actividad'] && !in_array($r['actividad'], $porSlot[$k]['actividades'], true)) {
            $porSlot[$k]['actividades'][] = $r['actividad'];
        }
        if ($r['productivity'] !== null) {
            $porSlot[$k]['productividad'] = max($porSlot[$k]['productividad'] ?? 0, $r['productivity']);
        }
        if ($r['status_act'] && !in_array($r['status_act'], $porSlot[$k]['status'], true)) {
            $porSlot[$k]['status'][] = $r['status_act'];
        }
        if ($r['coord_entrante']) $porSlot[$k]['coord_entrante'] = $r['coord_entrante'];
        if ($r['coord_saliente']) $porSlot[$k]['coord_saliente'] = $r['coord_saliente'];
    }

    // Huecos desde la rejilla.
    foreach ($rejilla as $dia) {
        foreach ($dia['celdas'] as $cod => $estado) {
            if ($estado !== 'sin_operacion') continue;
            $k = $dia['fecha'] . '|' . $cod;
            if (isset($porSlot[$k])) continue;
            $porSlot[$k] = ['fecha' => $dia['fecha'], 'turno' => $cod,
                            'label' => opn_label_turno($jornadas, $cod),
                            'tipo' => 'hueco', 'tm' => 0.0, 'actividades' => [],
                            'productividad' => null, 'status' => [],
                            'coord_entrante' => null, 'coord_saliente' => null];
        }
    }

    ksort($porSlot);
    $items = array_values($porSlot);

    // Acumulado corrido sobre el plan, para leer el avance turno a turno.
    $acum = 0.0;
    foreach ($items as &$i) {
        $acum += $i['tm'];
        $i['tm']        = round($i['tm'], 2);
        $i['acumulado'] = round($acum, 2);
        $i['plan']      = $planTotal !== null ? round($planTotal, 2) : null;
    }
    unset($i);

    return $items;
}
