<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Catálogos del módulo Evaluación de Desempeño (FIJOS en código)
   ───────────────────────────────────────────────────────────────────────
   Única fuente de verdad para:
     · La escala de calificación (1-5)
     · Los criterios de evaluación, agrupados por categoría
     · Los turnos válidos
   La consumen: el formulario (pages/evaluacion_desempeno.php) y la
   validación en servidor (api/save_evaluacion_desempeno.php).
   ═══════════════════════════════════════════════════════════════════════ */

/** Escala de calificación. Clave = puntaje guardado, valor = etiqueta. */
function ed_escala() {
    return [
        1 => 'Deficiente',
        2 => 'Por Mejorar',
        3 => 'Aceptable',
        4 => 'Satisfactorio',
        5 => 'Sobresaliente',
    ];
}

/**
 * Criterios de evaluación agrupados por categoría, en el orden del formato
 * original. Clave de categoría = letra mostrada en el resumen de puntuación.
 */
function ed_criterios() {
    return [
        'A' => [
            'label' => 'Cumplimiento de Procedimientos Operativos',
            'items' => [
                'Cumple con los procedimientos establecidos en los instructivos operativos del área.',
                'Registra y valida la información de carga de manera precisa y oportuna en el sistema.',
                'Responde consultas y solicitudes de acuerdo con los procedimientos internos vigentes.',
            ],
        ],
        'B' => [
            'label' => 'Seguridad, Salud en el Trabajo y Uso de EPP',
            'items' => [
                'Hace uso correcto y permanente de los Equipos de Protección Personal (EPP) requeridos para la operación.',
                'Se mantiene dentro de las zonas seguras asignadas durante el desarrollo de la operación portuaria.',
                'Identifica y reporta oportunamente condiciones o situaciones de riesgo observadas en su área de trabajo.',
            ],
        ],
        'C' => [
            'label' => 'Relaciones Interpersonales y Conducta Profesional',
            'items' => [
                'Mantiene un trato cordial, respetuoso y colaborativo con sus compañeros y supervisores.',
                'Comunica de forma clara y oportuna las novedades de la operación a su equipo y jefatura inmediata.',
                'Demuestra disposición para apoyar a otros miembros del equipo cuando la operación lo requiere.',
            ],
        ],
    ];
}

/** Turnos válidos (manual). Clave = valor en BD, valor = etiqueta. */
function ed_turnos() {
    return ['dia' => 'Día', 'noche' => 'Noche'];
}
