<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Resolución del turno vigente
   ───────────────────────────────────────────────────────────────────────
   Un turno = (fecha + jornada). Jornadas de 12h:
     Día   06:30–18:30
     Noche 18:30–06:30   (cruza medianoche)

   La jornada Noche entre 00:00 y 06:30 pertenece al turno que inició el día
   anterior.
   ═══════════════════════════════════════════════════════════════════════ */

/** "HH:MM[:SS]" → minutos del día. */
function _hhmm_a_min($t) {
    $p = explode(':', (string)$t);
    return (int)$p[0] * 60 + (int)($p[1] ?? 0);
}

/**
 * Fecha (YYYY-MM-DD) del turno para una jornada ELEGIDA, según la hora actual.
 * Pensada para la "salida" del coordinador: devuelve la fecha de la instancia
 * del turno que está en curso o que acaba de terminar.
 *   · Jornada normal (inicio < fin): siempre la fecha de hoy.
 *   · Jornada que cruza medianoche (Noche): pertenece a hoy SOLO a partir de su
 *     hora de inicio (19:00). Antes de esa hora —incluida toda la franja diurna
 *     en la que el coordinador hace su salida tras la noche— pertenece a ayer.
 *     Ej.: Noche 19:00–07:00 abierto a las 08:00 → fecha = ayer.
 */
function _fecha_para_jornada($j) {
    $nowTs  = time();
    $nowMin = (int)date('H', $nowTs) * 60 + (int)date('i', $nowTs);
    $ini = _hhmm_a_min($j['hora_inicio']);
    $fin = _hhmm_a_min($j['hora_fin']);
    if ($ini < $fin) {
        return date('Y-m-d', $nowTs);                 // jornada dentro del día
    }
    // cruza medianoche: hoy desde el inicio (19:00); antes del inicio → ayer.
    return ($nowMin >= $ini) ? date('Y-m-d', $nowTs) : date('Y-m-d', $nowTs - 86400);
}

/** Devuelve la jornada vigente (fila de `jornadas` + clave 'fecha'), o null.
 *  Solo considera jornadas activas. Si ninguna contiene la hora actual, cae a
 *  la primera jornada activa (con fecha de hoy) para tener siempre un default. */
function jornada_vigente($conn) {
    $r = mysqli_query($conn, "SELECT id, codigo, nombre, hora_inicio, hora_fin FROM jornadas WHERE activo=1 ORDER BY orden");
    if (!$r) return null;

    $nowTs  = time();
    $nowMin = (int)date('H', $nowTs) * 60 + (int)date('i', $nowTs);
    $hoy    = date('Y-m-d', $nowTs);
    $ayer   = date('Y-m-d', $nowTs - 86400);

    $primera = null;
    while ($j = mysqli_fetch_assoc($r)) {
        if ($primera === null) $primera = $j;
        $ini = _hhmm_a_min($j['hora_inicio']);
        $fin = _hhmm_a_min($j['hora_fin']);
        if ($ini < $fin) {
            if ($nowMin >= $ini && $nowMin < $fin) { $j['fecha'] = $hoy; return $j; }
        } else {
            if ($nowMin >= $ini) { $j['fecha'] = $hoy;  return $j; }   // tras el inicio, mismo día
            if ($nowMin <  $fin) { $j['fecha'] = $ayer; return $j; }   // madrugada → turno de ayer
        }
    }
    if ($primera !== null) { $primera['fecha'] = $hoy; return $primera; }
    return null;
}

/** Jornada por id, sin fecha. null si no existe. */
function jornada_por_id($conn, $id) {
    $stmt = mysqli_prepare($conn, "SELECT id, codigo, nombre, hora_inicio, hora_fin FROM jornadas WHERE id=? LIMIT 1");
    $id = (int)$id;
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $j   = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $j ?: null;
}

/** Jornada por código (D/N), sin fecha. null si no existe. */
function jornada_por_codigo($conn, $codigo) {
    $stmt = mysqli_prepare($conn, "SELECT id, codigo, nombre, hora_inicio, hora_fin FROM jornadas WHERE codigo=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $codigo);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $j   = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $j ?: null;
}

/** Catálogo de jornadas. $soloActivas=true para el selector del tablero. */
function listar_jornadas($conn, $soloActivas = true) {
    $out = [];
    $sql = "SELECT id, codigo, nombre, hora_inicio, hora_fin, activo, orden FROM jornadas"
         . ($soloActivas ? " WHERE activo=1" : "")
         . " ORDER BY orden, id";
    $r = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($r)) {
        $out[] = [
            'id'          => (int)$row['id'],
            'codigo'      => $row['codigo'],
            'nombre'      => $row['nombre'],
            'hora_inicio' => substr($row['hora_inicio'], 0, 5),
            'hora_fin'    => substr($row['hora_fin'],    0, 5),
            'activo'      => (int)$row['activo'],
            'orden'       => (int)$row['orden'],
        ];
    }
    return $out;
}

/** IDs de colaboradores que trabajaron un turno ADYACENTE a (fecha + jornada).
 *  Día(X)   → adyacentes: Noche(X-1) y Noche(X)
 *  Noche(X) → adyacentes: Día(X) y Día(X+1)
 *  Devuelve un set [colaborador_id => true]. */
function turno_conflict_set($conn, $fecha, $jornadaCodigo) {
    $sqlAdj = ($jornadaCodigo === 'D')
        ? "SELECT t.id FROM turnos t JOIN jornadas j ON j.id = t.jornada_id
             WHERE j.codigo='N' AND t.fecha IN (DATE_SUB(?, INTERVAL 1 DAY), ?)"
        : "SELECT t.id FROM turnos t JOIN jornadas j ON j.id = t.jornada_id
             WHERE j.codigo='D' AND t.fecha IN (?, DATE_ADD(?, INTERVAL 1 DAY))";
    $stmt = mysqli_prepare($conn, $sqlAdj);
    mysqli_stmt_bind_param($stmt, 'ss', $fecha, $fecha);
    mysqli_stmt_execute($stmt);
    $res    = mysqli_stmt_get_result($stmt);
    $adjIds = [];
    while ($r = mysqli_fetch_assoc($res)) $adjIds[] = (int)$r['id'];
    mysqli_stmt_close($stmt);

    $set = [];
    if ($adjIds) {
        $inIds = implode(',', $adjIds);
        $q = mysqli_query($conn, "SELECT DISTINCT colaborador_id FROM turno_personal WHERE turno_id IN ($inIds)");
        while ($r = mysqli_fetch_assoc($q)) $set[(int)$r['colaborador_id']] = true;
    }
    return $set;
}

/** Busca (o crea) el turno para (fecha + jornada). Devuelve [id, estado]. */
function _find_or_create_turno($conn, $jornadaId, $fecha) {
    $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    $stmt = mysqli_prepare($conn, "SELECT id, estado FROM turnos WHERE fecha=? AND jornada_id=?");
    mysqli_stmt_bind_param($stmt, 'si', $fecha, $jornadaId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($row) return [(int)$row['id'], $row['estado']];

    $ins = mysqli_prepare($conn, "INSERT INTO turnos (fecha, jornada_id, estado, abierto_por) VALUES (?, ?, 'abierto', ?)");
    mysqli_stmt_bind_param($ins, 'sii', $fecha, $jornadaId, $userId);
    mysqli_stmt_execute($ins);
    $id = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($ins);
    return [$id, 'abierto'];
}

/** Horas de gracia tras el fin nominal del turno antes del auto-cierre.
 *  Con Día 07:00–19:00 → cierra 22:00; Noche 19:00–07:00 → cierra 10:00. */
const TURNO_GRACIA_HORAS = 3;

/**
 * Auto-cierra el turno solo cuando ya pasó la ventana de gracia tras su fin.
 * Para jornadas que cruzan medianoche (ini >= fin), el fin es al día siguiente.
 * Devuelve el nuevo estado si lo cambió, null si no hizo nada.
 */
function _auto_cerrar_si_vencido($conn, $turnoId, $j, $fecha) {
    $ini = _hhmm_a_min($j['hora_inicio']);
    $fin = _hhmm_a_min($j['hora_fin']);
    // Determinar la fecha en que termina este turno
    $fechaFin = ($ini >= $fin)
        ? date('Y-m-d', strtotime($fecha . ' +1 day'))
        : $fecha;
    // Cierre = fin nominal + ventana de gracia.
    $finTs = strtotime($fechaFin . ' ' . substr($j['hora_fin'], 0, 5) . ':00')
           + TURNO_GRACIA_HORAS * 3600;
    if (time() <= $finTs) return null;  // aún dentro de la ventana (+ gracia)
    $upd = mysqli_prepare($conn, "UPDATE turnos SET estado='cerrado' WHERE id=? AND estado='abierto'");
    mysqli_stmt_bind_param($upd, 'i', $turnoId);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);
    return 'cerrado';
}

/** Arma el payload de turno a partir de la jornada y la fecha.
 *  $autoClose=false cuando se accede a un turno histórico con fecha explícita:
 *  evita revertir un reabrir manual hecho por Supervisor/Administrador. */
function _payload_turno($conn, $j, $fecha, $autoClose = true) {
    [$turnoId, $estado] = _find_or_create_turno($conn, (int)$j['id'], $fecha);
    // Auto-cierre solo para el turno activo; no tocar turnos reabiertos manualmente.
    if ($autoClose && $estado === 'abierto') {
        $nuevo = _auto_cerrar_si_vencido($conn, $turnoId, $j, $fecha);
        if ($nuevo) $estado = $nuevo;
    }
    $hi = substr($j['hora_inicio'], 0, 5);
    $hf = substr($j['hora_fin'],    0, 5);
    return [
        'id'          => $turnoId,
        'fecha'       => $fecha,
        'jornada_id'  => (int)$j['id'],
        'jornada'     => $j['codigo'],
        'estado'      => $estado,
        'codigo'      => $j['codigo'],
        'nombre'      => $j['nombre'],
        'hora_inicio' => $hi,
        'hora_fin'    => $hf,
        'label'       => $j['nombre'],   // el nombre ya incluye las horas formales
    ];
}

/** Turno vigente según la hora del servidor (find-or-create). null si no hay jornadas. */
function obtener_turno_actual($conn) {
    $j = jornada_vigente($conn);
    if (!$j) return null;
    return _payload_turno($conn, $j, $j['fecha']);
}

/** Turno para una jornada elegida (D/N), con la fecha resuelta por la hora actual. */
function obtener_turno_por_codigo($conn, $codigo) {
    $j = jornada_por_codigo($conn, $codigo);
    if (!$j) return null;
    return _payload_turno($conn, $j, _fecha_para_jornada($j));
}

/** Turno para una jornada (por id) y una fecha explícitas (selector del tablero).
 *  Si no se da fecha, se resuelve por la hora actual para esa jornada. */
function obtener_turno($conn, $jornadaId, $fecha = null) {
    $j = jornada_por_id($conn, $jornadaId);
    if (!$j) return null;
    $fechaExplicita = ($fecha !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha));
    if (!$fechaExplicita) {
        $fecha = _fecha_para_jornada($j);
    }
    // Si se accede por fecha explícita (turno pasado), no auto-cerrar: puede haber
    // sido reabierto manualmente por Supervisor/Administrador.
    return _payload_turno($conn, $j, $fecha, !$fechaExplicita);
}
