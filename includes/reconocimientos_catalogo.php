<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Catálogos del módulo Reconocimiento Tally (FIJOS en código)
   ───────────────────────────────────────────────────────────────────────
   Única fuente de verdad para:
     · El mapeo Competencia → Concepto (qué se reconoce)
     · La escala de Nivel de impacto
   Las zonas y turnos se reutilizan del catálogo de Incidencias
   (inc_zonas / inc_turnos). La consumen el formulario
   (pages/reconocimientos.php) y la validación en servidor
   (api/save_reconocimiento.php). Si cambian las opciones, se editan AQUÍ y
   todo el módulo queda consistente.
   ═══════════════════════════════════════════════════════════════════════ */

require_once(__DIR__ . '/incidencias_catalogo.php'); // inc_zonas(), inc_turnos()

/**
 * Competencia → Concepto (descripción de lo que se reconoce).
 * Se elige la competencia y el concepto se autocompleta.
 */
function rec_competencia_concepto() {
    return [
        'Autonomía'                        => 'Puede realizar tareas de forma independiente y da soporte a compañeros',
        'Organización y gestión del tiempo'=> 'Realiza el trabajo en tiempo y forma adecuada',
        'Adaptabilidad'                    => 'Responde a traslados de posiciones de forma positiva',
        'Productividad'                    => 'Mantiene un nivel de productividad por encima del promedio en las operaciones',
        'Comunicación y Colaboración'      => 'Realiza un buen relevo y traslada la información necesaria para la continuidad de operaciones',
        'Iniciativa y compromiso'          => 'Propone ideas para mejorar formas de trabajo y tiene disposición para culminar sus tareas',
        'Disciplina profesional'           => 'Cumple con normas laborales y asiste oportunamente a capacitaciones y demás charlas',
        'Seguridad en el trabajo'          => 'Cumple con normas de seguridad, reporta condiciones inseguras y no genera acciones inseguras',
    ];
}

/** Concepto para una competencia dada, o null si la competencia no existe. */
function rec_concepto_de($competencia) {
    $map = rec_competencia_concepto();
    return $map[$competencia] ?? null;
}

/**
 * Texto elaborado que aparece en el cuerpo del diploma, por competencia.
 * Es una redacción formal del concepto. Se puede editar libremente aquí.
 */
function rec_competencia_diploma() {
    return [
        'Autonomía'                         => 'Este certificado se otorga en mérito a su demostrada capacidad para ejecutar sus responsabilidades con total autonomía, gestionando sus tareas de manera proactiva y brindando un soporte excepcional y oportuno a sus compañeros de equipo.',
        'Organización y gestión del tiempo' => 'Este certificado se otorga en reconocimiento a su notable organización y manejo del tiempo, cumpliendo cada una de sus tareas en el momento oportuno y con la calidad que las operaciones exigen.',
        'Adaptabilidad'                     => 'Este certificado se otorga en mérito a su excelente adaptabilidad, respondiendo con actitud positiva y disposición ante los cambios de posición y las necesidades cambiantes de la operación.',
        'Productividad'                     => 'Este certificado se otorga en reconocimiento a su sobresaliente productividad, manteniendo de forma constante un rendimiento por encima del promedio en las operaciones.',
        'Comunicación y Colaboración'       => 'Este certificado se otorga en mérito a su ejemplar comunicación y colaboración, asegurando relevos claros y el traslado oportuno de la información necesaria para la continuidad de las operaciones.',
        'Iniciativa y compromiso'           => 'Este certificado se otorga en reconocimiento a su iniciativa y compromiso, proponiendo mejoras a las formas de trabajo y demostrando plena disposición para culminar cada una de sus tareas.',
        'Disciplina profesional'            => 'Este certificado se otorga en mérito a su sólida disciplina profesional, cumpliendo las normas laborales y asistiendo puntualmente a las capacitaciones y charlas del equipo.',
        'Seguridad en el trabajo'           => 'Este certificado se otorga en reconocimiento a su compromiso con la seguridad, cumpliendo las normas, reportando condiciones inseguras y evitando toda acción que ponga en riesgo a las personas.',
    ];
}

/** Texto de diploma para una competencia, con respaldo al concepto simple. */
function rec_diploma_de($competencia) {
    $map = rec_competencia_diploma();
    if (isset($map[$competencia])) return $map[$competencia];
    $concepto = rec_concepto_de($competencia);
    return $concepto ? ('Este certificado se otorga en reconocimiento a: ' . $concepto . '.') : '';
}

/**
 * Estado de aprobación del reconocimiento. Clave = valor en BD (enum),
 * 'label' = etiqueta visible, 'color' = color del badge en la UI.
 */
function rec_estados() {
    return [
        'pendiente' => ['label' => 'Pendiente', 'color' => '#F79009'],
        'aprobado'  => ['label' => 'Aprobado',  'color' => '#12B76A'],
        'rechazado' => ['label' => 'Rechazado', 'color' => '#F04438'],
    ];
}
