<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Catálogo del módulo de Capacitaciones
   ───────────────────────────────────────────────────────────────────────
   Fuente única de verdad de estados, marcas de asistencia y reglas de
   adjuntos. Se serializa a JS desde pages/capacitaciones.php, igual que
   hace Sugerencias con sg_canales(), para que servidor y navegador no
   puedan discrepar sobre qué es un estado válido.

   Los tipos de archivo permitidos NO se redefinen aquí: se reutiliza
   sg_tipos_permitidos() de drive_config.php. Duplicar esa lista sería
   garantizar que las dos copias diverjan.
   ═══════════════════════════════════════════════════════════════════════ */

require_once(__DIR__ . '/drive_config.php');   // sg_tipos_permitidos(), sg_accept_attr()

/* ── Estados del ciclo ──────────────────────────────────────────────────
   programada ──(coordinador: «Enviar a validación»)──▶ por_validar
   por_validar ──(administrador)──▶ realizada | no_realizada             */
function cap_estados() {
    return [
        'programada'   => ['label' => 'Programada',    'color' => '#475569', 'bg' => 'rgba(100,116,139,.12)'],
        'por_validar'  => ['label' => 'Por validar',   'color' => '#d97706', 'bg' => 'rgba(217,119,6,.10)'],
        'realizada'    => ['label' => 'Realizada',     'color' => '#047857', 'bg' => 'rgba(4,120,87,.10)'],
        'no_realizada' => ['label' => 'No realizada',  'color' => '#dc2626', 'bg' => 'rgba(220,38,38,.10)'],
    ];
}

/** Etiqueta legible de un estado. */
function cap_estado_label($clave) {
    $e = cap_estados();
    return isset($e[$clave]) ? $e[$clave]['label'] : $clave;
}

/* ── Marcas de asistencia ───────────────────────────────────────────────
   OJO: «sin marcar» NO está aquí a propósito. No es un valor guardable,
   es la ausencia de fila en capacitaciones_asistentes. Ver sql/028.      */
function cap_asistencia() {
    return [
        'asistio'  => ['label' => 'Asistió',  'abrev' => 'A', 'color' => '#047857', 'bg' => 'rgba(4,120,87,.10)'],
        'tardanza' => ['label' => 'Tardanza', 'abrev' => 'T', 'color' => '#d97706', 'bg' => 'rgba(217,119,6,.10)'],
        'falta'    => ['label' => 'Faltó',    'abrev' => 'F', 'color' => '#dc2626', 'bg' => 'rgba(220,38,38,.10)'],
    ];
}

/** Subcarpeta de Google Drive donde se guarda el material. */
function cap_carpeta_drive() {
    return 'Capacitaciones';
}

/** Límite por archivo. Alineado con el resto de adjuntos del sistema. */
function cap_max_bytes() {
    return defined('SG_MAX_BYTES') ? SG_MAX_BYTES : (4 * 1024 * 1024);
}

/** Tope de archivos por capacitación. Más generoso que en Sugerencias:
 *  aquí conviven el material de apoyo y las fotos de la sesión. */
function cap_max_adjuntos() {
    return 12;
}

/* ── Reglas del ciclo ──────────────────────────────────────────────── */

/** True si en este estado el coordinador todavía puede modificar el contenido. */
function cap_es_editable($estado) {
    return $estado === 'programada';
}

/** True si el estado es terminal (validado, ya no se mueve). */
function cap_es_terminal($estado) {
    return $estado === 'realizada' || $estado === 'no_realizada';
}

/**
 * ¿El usuario de la sesión puede escribir sobre esta capacitación?
 *
 * Administrador y Supervisor: cualquiera.
 * Coordinador: solo las suyas.
 * Cualquier rol: solo mientras el estado sea editable.
 *
 * Vive aquí y no en cada endpoint para que la regla exista una sola vez.
 *
 * @param array $row Fila de `capacitaciones` con al menos estado y coordinador_id.
 * @return array{ok:bool, error?:string}
 */
function cap_puede_editar($row) {
    if (!$row) return ['ok' => false, 'error' => 'La capacitación no existe.'];

    if (!cap_es_editable($row['estado'])) {
        return ['ok' => false, 'error' => cap_es_terminal($row['estado'])
            ? 'La capacitación ya fue validada y no admite cambios.'
            : 'La capacitación está en validación. Solo el administrador puede actuar sobre ella.'];
    }

    $rol = $_SESSION['user_rol'] ?? '';
    if ($rol === 'Administrador' || $rol === 'Supervisor') return ['ok' => true];

    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($rol === 'Coordinador' && $uid > 0 && (int)$row['coordinador_id'] === $uid) {
        return ['ok' => true];
    }
    return ['ok' => false, 'error' => 'Solo el coordinador que la creó puede modificarla.'];
}

/**
 * ¿Se puede eliminar? Igual que editar, más el Administrador, que puede
 * borrar en cualquier estado (limpieza de registros erróneos ya validados).
 */
function cap_puede_eliminar($row) {
    if (!$row) return ['ok' => false, 'error' => 'La capacitación no existe.'];
    if (($_SESSION['user_rol'] ?? '') === 'Administrador') return ['ok' => true];
    return cap_puede_editar($row);
}

/**
 * Guardas de la transición programada → por_validar.
 * Devuelve el primer motivo por el que NO se puede enviar, o null si se puede.
 *
 * Sin estas guardas, «Enviar a validación» sería un botón que traslada
 * trabajo sin haberlo hecho: el administrador recibiría capacitaciones sin
 * temas o con la asistencia a medio marcar.
 *
 * @param int $temas        Nº de temas cargados.
 * @param int $marcados     Nº de colaboradores con marca.
 * @param int $plantilla    Nº de colaboradores activos.
 */
function cap_motivo_no_enviable($temas, $marcados, $plantilla) {
    if ((int)$temas < 1) {
        return 'Agrega al menos un tema antes de enviar a validación.';
    }
    $faltan = (int)$plantilla - (int)$marcados;
    if ($faltan > 0) {
        return 'Faltan ' . $faltan . ' colaborador' . ($faltan === 1 ? '' : 'es')
             . ' por marcar en la pestaña de Asistencia.';
    }
    return null;
}
