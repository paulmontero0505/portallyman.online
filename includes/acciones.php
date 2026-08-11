<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Auditoría de acciones del turno
   Toda acción (alta/baja de personal, cambio de puesto/estado, eventos,
   cierre) se registra en `turno_acciones` con autor y hora, y aparece en
   los reportes.
   ═══════════════════════════════════════════════════════════════════════ */

require_once __DIR__ . '/sheets.php';

/** Etiquetas legibles de cada tipo de acción (usadas en BD-espejo y reportes). */
function label_accion($tipo) {
    $m = [
        'alta' => 'Alta', 'baja' => 'Baja', 'cambio' => 'Cambio de puesto',
        'estado' => 'Cambio de estado', 'evento' => 'Evento',
        'evento_borrado' => 'Evento eliminado', 'cierre' => 'Cierre',
    ];
    return $m[$tipo] ?? $tipo;
}

/** Registra una acción en la bitácora de auditoría del turno. */
function registrar_accion($conn, $turnoId, $tipo, $detalle, $colabCodigo = null, $colabNombre = null) {
    $uid    = isset($_SESSION['user_id'])   ? (int)$_SESSION['user_id'] : null;
    $uname  = $_SESSION['user_name'] ?? null;
    $urol   = $_SESSION['user_rol']  ?? null;
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO turno_acciones
            (turno_id, usuario_id, usuario_nombre, usuario_rol, tipo, colaborador_codigo, colaborador_nombre, detalle)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'iissssss', $turnoId, $uid, $uname, $urol, $tipo, $colabCodigo, $colabNombre, $detalle);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    // ── Espejo a Google Sheets ──────────────────────────────────────────
    // Cambios de puesto/estado y bitácora-evento van SEPARADOS. El tipo
    // 'evento' se omite aquí porque add_evento.php ya envía la fila rica a
    // la hoja 'Bitacora' (no se duplica).
    if ($tipo !== 'evento' && sheets_enabled()) {
        $sheet = in_array($tipo, ['cambio', 'estado'], true) ? 'Cambios_Estado' : 'Auditoria';
        $fecha = ''; $jornada = '';
        if ($q = mysqli_prepare($conn, "SELECT t.fecha, j.nombre FROM turnos t JOIN jornadas j ON j.id=t.jornada_id WHERE t.id=? LIMIT 1")) {
            mysqli_stmt_bind_param($q, 'i', $turnoId);
            mysqli_stmt_execute($q);
            mysqli_stmt_bind_result($q, $f, $jn);
            if (mysqli_stmt_fetch($q)) { $fecha = $f; $jornada = $jn; }
            mysqli_stmt_close($q);
        }
        $row = [
            date('Y-m-d H:i:s'), $fecha, $jornada, date('H:i'),
            $uname ?? '', $urol ?? '', label_accion($tipo),
            $colabCodigo ?? '', $colabNombre ?? '', $detalle ?? '',
        ];
        sheets_push($sheet, [$row], 'append', ['header' => [
            'Registrado', 'Fecha turno', 'Jornada', 'Hora', 'Usuario', 'Rol',
            'Acción', 'Código', 'Colaborador', 'Detalle',
        ]]);
    }
}

/** Contexto de un registro de personal a partir de turno_personal.id.
 *  Devuelve [turno_id, turno_estado, colaborador_codigo, colaborador_nombre,
 *  funcion, ubicacion, estado] o null. */
function contexto_tp($conn, $tpId) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT tp.turno_id, t.estado AS turno_estado, c.codigo, c.nombre,
                tp.funcion, tp.ubicacion, tp.nave_id, tp.horario_colab, tp.estado, tp.radio
           FROM turno_personal tp
           JOIN turnos t        ON t.id = tp.turno_id
           JOIN colaboradores c ON c.id = tp.colaborador_id
          WHERE tp.id = ? LIMIT 1"
    );
    $tpId = (int)$tpId;
    mysqli_stmt_bind_param($stmt, 'i', $tpId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/** Lista las acciones de un turno (para reporte del turno). */
function listar_acciones($conn, $turnoId) {
    $out = [];
    $stmt = mysqli_prepare(
        $conn,
        "SELECT TIME(created_at) AS hora, usuario_nombre, usuario_rol, tipo,
                colaborador_codigo, colaborador_nombre, detalle, created_at
           FROM turno_acciones WHERE turno_id = ? ORDER BY created_at ASC, id ASC"
    );
    $turnoId = (int)$turnoId;
    mysqli_stmt_bind_param($stmt, 'i', $turnoId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $out[] = [
            'hora'        => substr($r['hora'], 0, 5),
            'usuario'     => $r['usuario_nombre'],
            'rol'         => $r['usuario_rol'],
            'tipo'        => $r['tipo'],
            'codigo'      => $r['colaborador_codigo'],
            'colaborador' => $r['colaborador_nombre'],
            'detalle'     => $r['detalle'],
        ];
    }
    mysqli_stmt_close($stmt);
    return $out;
}
