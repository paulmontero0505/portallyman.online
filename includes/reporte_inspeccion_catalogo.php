<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Catálogos del módulo Reporte de Inspección (FIJOS en código)
   ───────────────────────────────────────────────────────────────────────
   Única fuente de verdad para:
     · Los ítems del checklist de inspección (criterios)
     · Los estados válidos por criterio (Conforme / No conforme)
   La consumen: el formulario (pages/reporte_inspeccion.php) y la validación
   en servidor (api/save_reporte_inspeccion.php).
   ═══════════════════════════════════════════════════════════════════════ */

/** Ítems a inspeccionar, en el orden del formato original. */
function ri_criterios() {
    return [
        'Señalización',
        'Iluminación',
        'Orden y Limpieza',
        'Uso de Epps en la zona',
        'Ausencia de condiciones inseguras',
        'Proximidad de espacios de limpieza personal',
        'Proximidad de espacios de hidratación',
        'Condiciones ergonómicas',
        'Otros',
    ];
}

/** Estados válidos de cada criterio. Clave = valor guardado, valor = etiqueta. */
function ri_estados() {
    return [
        'conforme'    => 'Conforme',
        'no_conforme' => 'No conforme',
    ];
}

/** Área involucrada: fija para este módulo (no editable desde el cliente). */
function ri_area_involucrada() {
    return 'Operaciones';
}

/**
 * Acciones correctivas que el coordinador puede marcar al responder un
 * reporte ya registrado. Se pueden marcar VARIAS. Clave = valor guardado,
 * valor = etiqueta. Para añadir o quitar opciones, se edita solo aquí:
 * el formulario y la validación en servidor leen de esta misma lista.
 */
function ri_acciones_correctivas() {
    return [
        'correccion_inmediata' => 'Corrección inmediata en campo',
        'charla'               => 'Charla / sensibilización al personal',
        'epp'                  => 'Entrega o reposición de EPP',
        'senalizacion'         => 'Señalización o delimitación del área',
        'limpieza'             => 'Limpieza y orden del área',
        'detencion'            => 'Detención de la actividad',
        'reubicacion'          => 'Reubicación del personal',
        'coordinacion'         => 'Coordinación con el área responsable',
        'hse'                  => 'Reporte a Seguridad (HSE)',
        'capacitacion'         => 'Capacitación programada',
        'seguimiento'          => 'Seguimiento en el próximo turno',
        'otros'                => 'Otros',
    ];
}

/** Etiqueta legible de una acción correctiva. */
function ri_accion_label($clave) {
    return ri_acciones_correctivas()[$clave] ?? $clave;
}

/** Subcarpeta de Google Drive donde se guardan las evidencias de la acción. */
function ri_carpeta_drive() {
    return 'Reportes de Inspección';
}
