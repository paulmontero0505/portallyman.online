<?php

function sg_canales() {
    return [
        'observacion' => ['label' => 'Observaciones',        'anonimo' => true,  'carpeta' => 'Observaciones'],
        'consulta'    => ['label' => 'Consultas',            'anonimo' => false, 'carpeta' => 'Consultas'],
        'solicitud'   => ['label' => 'Solicitudes',          'anonimo' => false, 'carpeta' => 'Solicitudes'],
        'propuesta'   => ['label' => 'Propuestas de mejora', 'anonimo' => false, 'carpeta' => 'Propuestas de mejora'],
        'encuesta'    => ['label' => 'Encuestas',            'anonimo' => false, 'carpeta' => 'Encuestas'],
    ];
}

function sg_encuesta_titulo() {
    return 'Encuesta de Refuerzo de Capacitación — Asistentes de Estiba';
}

function sg_encuesta_intro() {
    return 'Marca cada área o tema en el que sientes que necesitas más capacitación. '
         . 'Puedes marcar más de una opción por sección. Usa "Otros" para un tema que no esté en la lista.';
}

function sg_encuesta_secciones() {
    return [
        'muelles' => [
            'label' => 'Muelles (Descarga y Embarque)',
            'items' => [
                'Lectura de planos',
                'Identificación de Piñas',
                'Identificación de Precintos',
                'Daños de contenedores',
                'Pedeteo en contenedores',
                'Pedeteo en carga general',
                'Pedeteo en carga granel',
                'Rotulado y Segregación en descarga de vehículos rodantes',
                'Llenado de USR',
            ],
        ],
        'patio' => [
            'label' => 'Patio (Despachos)',
            'items' => [
                'Despacho de Cigüeñas',
                'Despacho por sus propios medios',
                'Despacho de Carga Fraccionada',
                'Despacho de Granel',
            ],
        ],
        'minerales' => [
            'label' => 'Minerales',
            'items' => [
                'Registro de Balanza 2',
                'Cierre de contenedores',
                'Proceso de pedeteo de minerales',
            ],
        ],
        'gate' => [
            'label' => 'Gate',
            'items' => [
                'Scanner',
                'Pre Gate',
                'Balanza',
            ],
        ],
        'depot' => [
            'label' => 'Depot',
            'items' => [
                'Retiros',
                'Devoluciones',
                'Llenado de EIR',
            ],
        ],
    ];
}

function sg_encuesta_validar($respuestas) {
    if (!is_array($respuestas) || !$respuestas) {
        return ['ok' => false, 'error' => 'Marca al menos un tema antes de enviar.'];
    }
    $secciones = sg_encuesta_secciones();
    $marcas = 0;
    foreach ($respuestas as $clave => $sec) {
        if (!isset($secciones[$clave])) {
            return ['ok' => false, 'error' => 'Sección de encuesta inválida.'];
        }
        if (!is_array($sec)) {
            return ['ok' => false, 'error' => 'Formato de respuesta inválido.'];
        }
        $items = $sec['items'] ?? [];
        $otros = trim((string)($sec['otros'] ?? ''));
        if (!is_array($items)) {
            return ['ok' => false, 'error' => 'Formato de respuesta inválido.'];
        }
        foreach ($items as $it) {
            if (!in_array($it, $secciones[$clave]['items'], true)) {
                return ['ok' => false, 'error' => 'Tema de encuesta inválido.'];
            }
        }
        if (mb_strlen($otros) > 300) {
            return ['ok' => false, 'error' => 'El texto de "Otros" es demasiado largo.'];
        }
        $marcas += count($items) + ($otros !== '' ? 1 : 0);
    }
    if ($marcas === 0) {
        return ['ok' => false, 'error' => 'Marca al menos un tema antes de enviar.'];
    }
    return ['ok' => true];
}

function sg_carpeta_drive($canal) {
    $c = sg_canales();
    return $c[$canal]['carpeta'] ?? 'Otros';
}

function sg_canal_puntuable() {
    return 'propuesta';
}

function sg_puntaje_min() { return 1; }
function sg_puntaje_max() { return 10; }

function sg_impactos() {
    return [
        'minimo' => ['label' => 'Mínimo', 'color' => '#64748b'],
        'medio'  => ['label' => 'Medio',  'color' => '#F79009'],
        'alto'   => ['label' => 'Alto',   'color' => '#00875A'],
    ];
}

function sg_viabilidad_bandas() {
    return [
        ['max' => 4,  'key' => 'no_viable', 'label' => 'No viable',        'color' => '#DC2626'],
        ['max' => 7,  'key' => 'ajustes',   'label' => 'Viable c/ajustes', 'color' => '#F79009'],
        ['max' => 10, 'key' => 'viable',    'label' => 'Viable',           'color' => '#12B76A'],
    ];
}

function sg_banda_viabilidad($viabilidad) {
    if ($viabilidad === null || $viabilidad === '') return null;
    foreach (sg_viabilidad_bandas() as $b) {
        if ((int)$viabilidad <= $b['max']) return $b;
    }
    return null;
}

function sg_decisiones() {
    return [
        'quick_win'  => ['label' => 'Quick win',   'color' => '#00875A', 'icono' => '★',
                         'glosa' => 'Alto impacto y viable: hacerlo ya.'],
        'hacer'      => ['label' => 'Hacer',       'color' => '#12B76A', 'icono' => '✓',
                         'glosa' => 'Viable y aporta lo suficiente.'],
        'apuesta'    => ['label' => 'Apuesta',     'color' => '#7c3aed', 'icono' => '◆',
                         'glosa' => 'Alto impacto, pero exige ajustes antes de aprobarse.'],
        'evaluar'    => ['label' => 'Evaluar',     'color' => '#F79009', 'icono' => '◷',
                         'glosa' => 'Impacto medio y viabilidad dudosa: revisar el caso.'],
        'opcional'   => ['label' => 'Opcional',    'color' => '#64748b', 'icono' => '○',
                         'glosa' => 'Se puede hacer, pero aporta poco.'],
        'descartar'  => ['label' => 'Descartar',   'color' => '#DC2626', 'icono' => '✕',
                         'glosa' => 'No es viable hoy, por mucho que aporte.'],
        'sin_impacto'=> ['label' => 'Pendiente de impacto', 'color' => '#B45309', 'icono' => '◐',
                         'glosa' => 'Falta declarar cuánto aporta.'],
        'sin_calif'  => ['label' => 'Sin calificar', 'color' => '#9ca3af', 'icono' => '—',
                         'glosa' => 'Aún no evaluada.'],
    ];
}

function sg_decision($viabilidad, $impacto) {
    $banda = sg_banda_viabilidad($viabilidad);
    if ($banda === null)                 return 'sin_calif';
    if ($impacto === null || $impacto === '') return 'sin_impacto';
    if ($banda['key'] === 'no_viable')   return 'descartar';

    $esViable = ($banda['key'] === 'viable');
    switch ($impacto) {
        case 'alto':   return $esViable ? 'quick_win' : 'apuesta';
        case 'medio':  return $esViable ? 'hacer'     : 'evaluar';
        case 'minimo': return 'opcional';
    }
    return 'sin_impacto';
}
