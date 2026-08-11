<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Catálogos del módulo de Incidencias (FIJOS en código)
   ───────────────────────────────────────────────────────────────────────
   Única fuente de verdad para:
     · El mapeo Punto a mejorar → Competencia afectada
     · La escala de Impacto
     · Las Zonas de trabajo
   La consumen: el formulario (pages/incidencias.php) y la validación en
   servidor (api/save_incidencia.php). Si cambian las opciones, se editan
   AQUÍ y todo el módulo queda consistente.
   ═══════════════════════════════════════════════════════════════════════ */

/**
 * Punto a mejorar → Competencia afectada.
 * El orden de las claves respeta el numeral del cuadro original.
 */
function inc_puntos_competencia() {
    return [
        'Errores de pedeteo'              => 'Dominio sólido en las tareas asignadas',
        'Error de registro en balanzas'   => 'Dominio sólido en las tareas asignadas',
        'Registro de USR'                 => 'Dominio sólido en las tareas asignadas',
        'Registro de CDR'                 => 'Dominio sólido en las tareas asignadas',
        'Trabajo en PS'                   => 'Dominio sólido en las tareas asignadas',
        'Continuidad operativa'           => 'Comunicación y Colaboración',
        'Proyección operativa'            => 'Iniciativa y compromiso',
        'Responsabilidad en funciones'    => 'Disciplina profesional',
        'Asistencia a capacitaciones'     => 'Disciplina profesional',
        'Seguridad y salud en el trabajo' => 'Seguridad en el trabajo',
        'Supervisión constante'           => 'Autonomía',
        'Tardanza o incumplimiento de charla' => 'Disciplina profesional',
        'Incumplimiento de refrigerio'    => 'Disciplina profesional',
        'Resistencia al cambio'           => 'Iniciativa y compromiso',
        'Relevo o radio deficiente'       => 'Comunicación y Colaboración',
        'Falta de recursos o información' => 'Iniciativa y compromiso',
        'Abandono o desacato'             => 'Disciplina profesional',
    ];
}

/** Competencia afectada para un punto dado, o null si el punto no existe. */
function inc_competencia_de($punto) {
    $map = inc_puntos_competencia();
    return $map[$punto] ?? null;
}

/**
 * Escala de impacto. Clave = valor guardado en BD (enum), 'label' = etiqueta
 * visible, 'color' = color del badge en la UI.
 */
function inc_impactos() {
    return [
        'minimo'   => ['label' => 'Mínimo',   'color' => '#12B76A'],
        'bajo'     => ['label' => 'Bajo',     'color' => '#84CC16'],
        'moderado' => ['label' => 'Moderado', 'color' => '#F79009'],
        'alto'     => ['label' => 'Alto',     'color' => '#F04438'],
        'critico'  => ['label' => 'Crítico',  'color' => '#B42318'],
    ];
}

/**
 * Zonas de trabajo. Salen de la tabla `ubicaciones`, que es la que el
 * administrador mantiene y la que llena el desplegable del formulario.
 * Antes vivían fijas aquí y quedaron desfasadas: el formulario ofrecía
 * zonas ("Minerales", "Depot", …) que el servidor rechazaba al guardar.
 *
 * @param bool $soloActivas true → las que se pueden elegir hoy (para el
 *        formulario). false → todas, incluidas las desactivadas, para poder
 *        validar la edición de una incidencia antigua cuya zona ya se retiró.
 */
function inc_zonas($soloActivas = true) {
    // 'global' va ANTES del require: si db.php se cargara aquí dentro, su
    // $conn quedaría como variable local de esta función.
    global $conn;
    require_once(__DIR__ . '/db.php');

    $sql = "SELECT nombre FROM ubicaciones"
         . ($soloActivas ? " WHERE activo=1" : "")
         . " ORDER BY orden, nombre";
    $r = mysqli_query($conn, $sql);
    if (!$r) return [];

    $zonas = [];
    while ($row = mysqli_fetch_assoc($r)) $zonas[] = $row['nombre'];
    return $zonas;
}

/** Turnos válidos (manual). Clave = valor en BD, valor = etiqueta. */
function inc_turnos() {
    return ['dia' => 'Día', 'noche' => 'Noche'];
}
