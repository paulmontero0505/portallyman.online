<?php

require_once(__DIR__ . '/drive_config.php');
require_once(__DIR__ . '/evaluacion_desempeno_catalogo.php');

function tk_estados() {
    return [
        'pendiente' => ['label' => 'Pendiente', 'color' => '#475569', 'bg' => 'rgba(100,116,139,.12)'],
        'entregada' => ['label' => 'En revisión','color' => '#2563eb', 'bg' => 'rgba(37,99,235,.10)'],
        'observada' => ['label' => 'Observada',  'color' => '#d97706', 'bg' => 'rgba(217,119,6,.10)'],
        'aprobada'  => ['label' => 'Aprobada',   'color' => '#047857', 'bg' => 'rgba(4,120,87,.10)'],
        'rechazada' => ['label' => 'Rechazada',  'color' => '#dc2626', 'bg' => 'rgba(220,38,38,.10)'],
    ];
}

function tk_estado_label($clave) {
    $e = tk_estados();
    return isset($e[$clave]) ? $e[$clave]['label'] : $clave;
}

function tk_prioridades() {
    return [
        'baja'  => ['label' => 'Baja',  'color' => '#64748b'],
        'media' => ['label' => 'Media', 'color' => '#d97706'],
        'alta'  => ['label' => 'Alta',  'color' => '#dc2626'],
    ];
}

function tk_semaforos() {
    return [
        'vencida'  => ['label' => 'Vencida',      'color' => '#dc2626', 'bg' => 'rgba(220,38,38,.10)'],
        'hoy'      => ['label' => 'Vence hoy',    'color' => '#d97706', 'bg' => 'rgba(217,119,6,.10)'],
        'proxima'  => ['label' => 'Vence pronto', 'color' => '#b45309', 'bg' => 'rgba(180,83,9,.08)'],
        'a_tiempo' => ['label' => 'A tiempo',     'color' => '#64748b', 'bg' => 'rgba(100,116,139,.08)'],
    ];
}

function tk_acciones() {
    return [
        'creada'            => 'Tarea creada',
        'editada'           => 'Enunciado modificado',
        'enviada'           => 'Entrega enviada',
        'observada'         => 'Devuelta con observaciones',
        'aprobada'          => 'Aprobada',
        'rechazada'         => 'Rechazada',
        'prorroga'          => 'Prórroga concedida',
        'prorroga_retirada' => 'Prórroga retirada',
        'adjunto'           => 'Archivo adjuntado',
        'adjunto_borrado'   => 'Archivo eliminado',
    ];
}

function tk_rol_label($rol) {
    return $rol === 'Soporte' ? 'Tally Soporte' : $rol;
}

function tk_roles_asignables() {
    return ['Coordinador', 'Soporte'];
}

function tk_nota_label($n) {
    if ($n === null || $n === '') return '—';
    $e = ed_escala();
    $n = (int)$n;
    return isset($e[$n]) ? $e[$n] : '—';
}

function tk_plazo_vigente($row) {
    $f2 = $row['fecha_limite_2'] ?? null;
    if ($f2 !== null && $f2 !== '' && $f2 !== '0000-00-00 00:00:00') return $f2;
    return $row['fecha_limite'] ?? null;
}

function tk_es_abierta($estado) {
    return $estado === 'pendiente' || $estado === 'observada';
}

function tk_es_terminal($estado) {
    return $estado === 'aprobada' || $estado === 'rechazada';
}

function tk_esta_atrasada($row, $ahora = null) {
    if (!tk_es_abierta($row['estado'] ?? '')) return false;
    $plazo = tk_plazo_vigente($row);
    if (!$plazo) return false;
    $ahora = ($ahora !== null) ? $ahora : time();
    return strtotime($plazo) < $ahora;
}

function tk_dias_atraso($row, $ahora = null) {
    if (!tk_esta_atrasada($row, $ahora)) return 0;
    $ahora = ($ahora !== null) ? $ahora : time();
    $d1 = new DateTime(date('Y-m-d', strtotime(tk_plazo_vigente($row))));
    $d2 = new DateTime(date('Y-m-d', $ahora));
    return (int)$d1->diff($d2)->days;
}

function tk_entregada_tarde($row) {
    $env = $row['enviado_at'] ?? null;
    $plz = $row['plazo_al_enviar'] ?? null;
    if (!$env || !$plz) return false;
    return strtotime($env) > strtotime($plz);
}

function tk_semaforo($row, $ahora = null) {
    if (!tk_es_abierta($row['estado'] ?? '')) return 'a_tiempo';
    $plazo = tk_plazo_vigente($row);
    if (!$plazo) return 'a_tiempo';
    $ahora = ($ahora !== null) ? $ahora : time();
    $ts = strtotime($plazo);
    if ($ts < $ahora) return 'vencida';
    if (date('Y-m-d', $ts) === date('Y-m-d', $ahora)) return 'hoy';
    if ($ts - $ahora <= 48 * 3600) return 'proxima';
    return 'a_tiempo';
}

function tk_filtro_visibilidad($alias = 't') {
    $rol = $_SESSION['user_rol'] ?? '';
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

    if ($rol === 'Administrador' || $rol === 'Supervisor') return '1=1';
    if ($uid <= 0) return '0=1';
    if ($rol === 'Coordinador') {
        return "($alias.asignado_id = $uid"
             . " OR $alias.asignado_id IN (SELECT id FROM usuarios WHERE soporte_de_id = $uid))";
    }
    if ($rol === 'Soporte') return "$alias.asignado_id = $uid";

    return '0=1';
}

function tk_puede_ver($row) {
    if (!$row) return false;
    $rol = $_SESSION['user_rol'] ?? '';
    if ($rol === 'Administrador' || $rol === 'Supervisor') return true;

    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($uid <= 0) return false;
    if ((int)($row['asignado_id'] ?? 0) === $uid) return true;
    if ($rol === 'Coordinador' && (int)($row['asignado_soporte_de'] ?? 0) === $uid) return true;
    return false;
}

function tk_puede_editar($row) {
    if (!$row) return ['ok' => false, 'error' => 'La tarea no existe.'];
    if (($_SESSION['user_rol'] ?? '') !== 'Administrador') {
        return ['ok' => false, 'error' => 'Solo el Administrador puede modificar una tarea.'];
    }
    if (!tk_es_abierta($row['estado'] ?? '')) {
        return ['ok' => false, 'error' => tk_es_terminal($row['estado'])
            ? 'La tarea ya fue revisada y no admite cambios.'
            : 'La tarea está en revisión. Devuélvela con una observación antes de modificarla.'];
    }
    return ['ok' => true];
}

function tk_puede_entregar($row) {
    if (!$row) return ['ok' => false, 'error' => 'La tarea no existe.'];
    if (!tk_es_abierta($row['estado'] ?? '')) {
        return ['ok' => false, 'error' => ($row['estado'] === 'entregada')
            ? 'La tarea ya fue enviada y está en revisión.'
            : 'La tarea ya fue revisada y no admite más entregas.'];
    }
    $rol = $_SESSION['user_rol'] ?? '';
    if ($rol === 'Administrador') return ['ok' => true, 'en_nombre_de' => true];

    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($uid > 0 && (int)($row['asignado_id'] ?? 0) === $uid) return ['ok' => true];

    return ['ok' => false, 'error' => 'Solo la persona asignada puede entregar esta tarea.'];
}

function tk_puede_revisar($row) {
    if (!$row) return ['ok' => false, 'error' => 'La tarea no existe.'];
    if (($_SESSION['user_rol'] ?? '') !== 'Administrador') {
        return ['ok' => false, 'error' => 'Solo el Administrador puede revisar tareas.'];
    }
    if (($row['estado'] ?? '') !== 'entregada') {
        return ['ok' => false, 'error' => tk_es_terminal($row['estado'] ?? '')
            ? 'La tarea ya fue revisada.'
            : 'Solo se puede revisar una tarea que ya fue entregada.'];
    }
    return ['ok' => true];
}

function tk_puede_prorrogar($row) {
    if (!$row) return ['ok' => false, 'error' => 'La tarea no existe.'];
    if (($_SESSION['user_rol'] ?? '') !== 'Administrador') {
        return ['ok' => false, 'error' => 'Solo el Administrador puede conceder una prórroga.'];
    }
    if (!tk_es_abierta($row['estado'] ?? '')) {
        return ['ok' => false, 'error' => 'Solo se puede prorrogar una tarea pendiente u observada.'];
    }
    return ['ok' => true];
}

function tk_carpeta_drive()  { return 'Tareas'; }
function tk_max_bytes()      { return defined('SG_MAX_BYTES') ? SG_MAX_BYTES : (4 * 1024 * 1024); }
function tk_max_adjuntos()   { return 10; }

function tk_historial($conn, $tareaId, $accion, $detalle = null) {
    $uid    = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $nombre = $_SESSION['user_name'] ?? 'Sistema';
    $rol    = $_SESSION['user_rol']  ?? '';
    $tareaId = (int)$tareaId;

    $st = mysqli_prepare($conn,
        "INSERT INTO tareas_historial
            (tarea_id, accion, usuario_id, usuario_nombre, usuario_rol, detalle)
         VALUES (?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($st, 'isisss', $tareaId, $accion, $uid, $nombre, $rol, $detalle);
    mysqli_stmt_execute($st);
    mysqli_stmt_close($st);
}
