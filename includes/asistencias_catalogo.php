<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Catálogo del módulo Asistencias Pre-Operativas
   ───────────────────────────────────────────────────────────────────────
   Fuente única de verdad para tipos de reunión. Las zonas y turnos se
   reutilizan del catálogo de Incidencias (inc_zonas / inc_turnos).
   ═══════════════════════════════════════════════════════════════════════ */

require_once(__DIR__ . '/incidencias_catalogo.php'); // inc_zonas(), inc_turnos()

/** Tipos de reunión del formato COSCO. Clave = valor en BD, valor = etiqueta. */
function aso_tipos_reunion() {
    return [
        'induccion_general'    => 'Inducción General',
        'induccion_especifica' => 'Inducción Específica',
        'capacitacion'         => 'Capacitación',
        'entrenamiento'        => 'Entrenamiento',
        'simulacro'            => 'Simulacro de Emergencia',
        'charla_seguridad'     => 'Charla de Seguridad',
        'charla_preoperativa'  => 'Charla Pre Operativa',
        'reunion'              => 'Reunión',
        'lecciones_aprendidas' => 'Lecciones Aprendidas',
        'otros'                => 'Otros',
    ];
}

/** Etiqueta legible de un tipo de reunión. */
function aso_tipo_label($clave) {
    return aso_tipos_reunion()[$clave] ?? $clave;
}

/** Subcarpeta de Google Drive donde se guardan las evidencias. */
function aso_carpeta_drive() {
    return 'Asistencias Pre-Operativas';
}
