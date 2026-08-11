<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Catálogos del módulo EVADES (FIJOS en código)
   ───────────────────────────────────────────────────────────────────────
   Única fuente de verdad para:
     · Las 10 competencias EVADES (Sección A conductual / B operativa) y
       su cruce con los catálogos de Incidencias y Reconocimiento Tally.
     · La Matriz Frecuencia × Impacto (Anexo 2 de la guía EVADES 2026).
     · Los trimestres válidos y la clasificación por puntaje total.

   IMPORTANTE: 'rec_competencia' e 'inc_competencia' deben coincidir
   LITERALMENTE con los valores usados en reconocimientos.competencia e
   incidencias.competencia (ver includes/reconocimientos_catalogo.php e
   includes/incidencias_catalogo.php). Si esos catálogos cambian de
   texto, hay que actualizar aquí también o el cruce automático se rompe
   en silencio (deja de encontrar filas, no lanza error).
   ═══════════════════════════════════════════════════════════════════════ */

/**
 * Las 10 competencias EVADES, en el orden de la guía (Sección A, luego B).
 * rec_competencia: string en reconocimientos.competencia que alimenta el
 *   incremento automático, o null si esa competencia no tiene catálogo de
 *   reconocimiento (incremento 100% manual).
 * inc_competencia: string en incidencias.competencia que alimenta el
 *   descuento automático, o null si no tiene catálogo de incidencia
 *   (descuento 100% manual).
 */
function evades_competencias() {
    return [
        'autonomia' => [
            'label' => 'Autonomía',
            'tipo' => 'conductual',
            'rec_competencia' => 'Autonomía',
            'inc_competencia' => null,
        ],
        'organizacion_tiempo' => [
            'label' => 'Organización y Gestión del Tiempo',
            'tipo' => 'conductual',
            'rec_competencia' => 'Organización y gestión del tiempo',
            'inc_competencia' => null,
        ],
        'adaptabilidad' => [
            'label' => 'Adaptabilidad',
            'tipo' => 'conductual',
            'rec_competencia' => 'Adaptabilidad',
            'inc_competencia' => null,
        ],
        'productividad' => [
            'label' => 'Productividad',
            'tipo' => 'conductual',
            'rec_competencia' => null,
            'inc_competencia' => null,
        ],
        'eficiencia' => [
            'label' => 'Eficiencia',
            'tipo' => 'conductual',
            'rec_competencia' => null,
            'inc_competencia' => null,
        ],
        'dominio_solido' => [
            'label' => 'Dominio Sólido en Tareas Asignadas',
            'tipo' => 'operativa',
            'rec_competencia' => null,
            'inc_competencia' => 'Dominio sólido en las tareas asignadas',
        ],
        'comunicacion_colaboracion' => [
            'label' => 'Comunicación y Colaboración',
            'tipo' => 'operativa',
            'rec_competencia' => 'Comunicación y Colaboración',
            'inc_competencia' => 'Comunicación y Colaboración',
        ],
        'iniciativa_compromiso' => [
            'label' => 'Iniciativa y Compromiso',
            'tipo' => 'operativa',
            'rec_competencia' => 'Iniciativa y compromiso',
            'inc_competencia' => 'Iniciativa y compromiso',
        ],
        'disciplina_profesional' => [
            'label' => 'Disciplina Profesional',
            'tipo' => 'operativa',
            'rec_competencia' => 'Disciplina profesional',
            'inc_competencia' => 'Disciplina profesional',
        ],
        'seguridad_trabajo' => [
            'label' => 'Seguridad en el Trabajo',
            'tipo' => 'operativa',
            'rec_competencia' => 'Seguridad en el trabajo',
            'inc_competencia' => 'Seguridad en el trabajo',
        ],
    ];
}

/** true si la competencia tiene algún lado (incremento o descuento) automatizado. */
function evades_tiene_automatizacion($competenciaKey) {
    $reglas = evades_reglas_evidencia();
    if (!isset($reglas[$competenciaKey])) return false;
    $fuentesObjetivas = array_diff($reglas[$competenciaKey]['fuentes_positivas'] ?? [], ['apreciacion']);
    return !empty($fuentesObjetivas) || !empty($reglas[$competenciaKey]['puntos_incidencia'] ?? []);
}

/** Umbrales objetivos para convertir evidencia EPT (escala 1-5) en +2/+4. */
function evades_umbrales_ept() {
    return ['minimo' => 3, 'nivel_2' => 4.0, 'nivel_4' => 4.5];
}

/**
 * Fuentes y relaciones aprobadas por la matriz/guía EVADES.
 * Los criterios EPT son claves semánticas resueltas por evades_evidence.php.
 */
function evades_reglas_evidencia() {
    $erroresTecnicos = [
        'Errores de pedeteo',
        'Error de registro en balanzas',
        'Registro de USR',
        'Registro de CDR',
        'Trabajo en PS',
    ];

    return [
        'autonomia' => [
            'puntos_incidencia' => ['Supervisión constante'],
            'cruzadas' => [],
            'criterios_ept' => ['apoyo_equipo'],
            'fuentes_positivas' => ['reconocimiento', 'ept', 'apreciacion'],
        ],
        'organizacion_tiempo' => [
            'puntos_incidencia' => ['Tardanza o incumplimiento de charla', 'Incumplimiento de refrigerio'],
            'cruzadas' => ['disciplina_profesional'],
            'criterios_ept' => [],
            'fuentes_positivas' => ['reconocimiento', 'asistencia', 'apreciacion'],
        ],
        'adaptabilidad' => [
            'puntos_incidencia' => ['Resistencia al cambio'],
            'cruzadas' => ['iniciativa_compromiso'],
            'criterios_ept' => [],
            'fuentes_positivas' => ['reconocimiento', 'propuesta', 'apreciacion'],
        ],
        'productividad' => [
            'puntos_incidencia' => [],
            'cruzadas' => [],
            'criterios_ept' => [],
            'fuentes_positivas' => ['apreciacion'],
        ],
        'eficiencia' => [
            'puntos_incidencia' => $erroresTecnicos,
            'cruzadas' => ['dominio_solido'],
            'criterios_ept' => ['procedimientos', 'registro_preciso'],
            'fuentes_positivas' => ['ept_sin_incidencias', 'apreciacion'],
        ],
        'dominio_solido' => [
            'puntos_incidencia' => $erroresTecnicos,
            'cruzadas' => ['eficiencia'],
            'criterios_ept' => ['procedimientos', 'registro_preciso'],
            'fuentes_positivas' => ['ept_sin_incidencias', 'apreciacion'],
        ],
        'comunicacion_colaboracion' => [
            'puntos_incidencia' => ['Continuidad operativa', 'Relevo o radio deficiente'],
            'cruzadas' => [],
            'criterios_ept' => ['trato_colaborativo', 'comunicacion_novedades', 'apoyo_equipo'],
            'fuentes_positivas' => ['reconocimiento', 'ept', 'apreciacion'],
        ],
        'iniciativa_compromiso' => [
            'puntos_incidencia' => ['Proyección operativa', 'Falta de recursos o información'],
            'cruzadas' => ['adaptabilidad'],
            'criterios_ept' => [],
            'fuentes_positivas' => ['reconocimiento', 'propuesta', 'apreciacion'],
        ],
        'disciplina_profesional' => [
            'puntos_incidencia' => [
                'Responsabilidad en funciones',
                'Asistencia a capacitaciones',
                'Tardanza o incumplimiento de charla',
                'Incumplimiento de refrigerio',
                'Abandono o desacato',
            ],
            'cruzadas' => ['organizacion_tiempo'],
            'criterios_ept' => [],
            'fuentes_positivas' => ['reconocimiento', 'asistencia', 'apreciacion'],
        ],
        'seguridad_trabajo' => [
            'puntos_incidencia' => ['Seguridad y salud en el trabajo'],
            'cruzadas' => [],
            'criterios_ept' => ['uso_epp', 'zona_segura', 'reporte_riesgos'],
            'fuentes_positivas' => ['reconocimiento', 'ept', 'apreciacion'],
        ],
    ];
}

/**
 * Traduce el procedimiento registrado en Incidencias a la competencia EVADES
 * primaria y, después, a sus competencias cruzadas según la guía.
 */
function evades_claves_afectadas_por_punto($punto) {
    require_once(__DIR__ . '/incidencias_catalogo.php');
    $competenciaPrimaria = inc_competencia_de((string)$punto);
    if ($competenciaPrimaria === null) return [];

    $clavePrimaria = null;
    foreach (evades_competencias() as $key => $meta) {
        if (($meta['inc_competencia'] ?? null) === $competenciaPrimaria
            || ($meta['rec_competencia'] ?? null) === $competenciaPrimaria
            || $meta['label'] === $competenciaPrimaria) {
            $clavePrimaria = $key;
            break;
        }
    }
    if ($clavePrimaria === null) return [];

    $reglas = evades_reglas_evidencia();
    return array_values(array_unique(array_merge(
        [$clavePrimaria],
        $reglas[$clavePrimaria]['cruzadas'] ?? []
    )));
}

/**
 * Matriz Frecuencia (1-5) × Impacto → puntos de descuento.
 * Anexo 2 de la guía EVADES 2026. Frecuencia se topa en 5 si hay más
 * incidencias; Impacto usa las mismas 5 claves que incidencias.impacto.
 */
function evades_matriz_fi() {
    return [
        1 => ['minimo' => 2, 'bajo' => 2, 'moderado' => 4, 'alto' => 6,  'critico' => 8],
        2 => ['minimo' => 2, 'bajo' => 2, 'moderado' => 4, 'alto' => 8,  'critico' => 8],
        3 => ['minimo' => 4, 'bajo' => 4, 'moderado' => 6, 'alto' => 8,  'critico' => 10],
        4 => ['minimo' => 6, 'bajo' => 6, 'moderado' => 8, 'alto' => 10, 'critico' => 10],
        5 => ['minimo' => 8, 'bajo' => 8, 'moderado' => 10,'alto' => 10, 'critico' => 10],
    ];
}

/**
 * Valores de incremento permitidos (guía EVADES: +2 o +4 por competencia).
 * Se incluye 6 porque Autonomía puede acumular el incremento por
 * reconocimiento (hasta +4) MÁS el bono de evaluación diaria (+2) en el
 * mismo trimestre — ver evades_bono_autonomia() en evades_engine.php. El
 * puntaje final de la competencia igual queda topado en 10 por el clamp
 * de api/save_evades.php, esto solo evita rechazar una sugerencia válida.
 */
function evades_incrementos_validos() {
    return [0, 2, 4];
}

/** Valores de descuento permitidos (guía EVADES, Anexo 2). */
function evades_descuentos_validos() {
    return [0, 2, 4, 6, 8, 10];
}

/** Puestos que comparten la matriz EVADES aprobada. */
function evades_puestos_validos() {
    return ['ASISTENTE DE ESTIBA', 'ANALISTA DE TROUBLE DESK'];
}

/** Devuelve el nombre canónico del puesto o null si EVADES no lo admite. */
function evades_normalizar_puesto($puesto) {
    $normalizado = strtoupper(trim((string)$puesto));
    return in_array($normalizado, evades_puestos_validos(), true) ? $normalizado : null;
}

/** Estados persistidos de un bloque, en el orden normal del flujo. */
function evades_estados_bloque() {
    return ['generado', 'revisado', 'modificado', 'cerrado'];
}

/** Un bloque cerrado queda en modo de consulta para todos los roles. */
function evades_bloque_editable($estado) {
    return in_array((string)$estado, ['generado', 'revisado', 'modificado'], true);
}

/** Valida únicamente transiciones de avance; nunca permite reabrir. */
function evades_transicion_bloque_valida($desde, $hacia) {
    $mapa = [
        'generado' => ['revisado', 'modificado', 'cerrado'],
        'revisado' => ['modificado', 'cerrado'],
        'modificado' => ['cerrado'],
        'cerrado' => [],
    ];
    return in_array((string)$hacia, $mapa[(string)$desde] ?? [], true);
}

/**
 * Rango de fechas [inicio, fin] de un período 'YYYY-T#'. Null si el
 * formato no calza.
 */
function evades_periodo_fechas($periodo) {
    if (!preg_match('/^(\d{4})-T([1-4])$/', $periodo, $m)) return null;
    $anio = (int)$m[1];
    $t = (int)$m[2];
    $meses = [1 => [1, 3], 2 => [4, 6], 3 => [7, 9], 4 => [10, 12]];
    [$mIni, $mFin] = $meses[$t];
    $inicio = sprintf('%04d-%02d-01', $anio, $mIni);
    $fin = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $anio, $mFin)));
    return ['inicio' => $inicio, 'fin' => $fin];
}

/** Los 4 trimestres de un año, con etiqueta y rango, para poblar el selector. */
function evades_periodos_del_anio($anio) {
    $out = [];
    foreach ([1, 2, 3, 4] as $t) {
        $periodo = "$anio-T$t";
        $out[$periodo] = evades_periodo_fechas($periodo);
    }
    return $out;
}

/** Clasificación EVADES según el puntaje total (0-100). */
function evades_clasificacion($puntaje) {
    if ($puntaje <= 54) return 'Debajo de lo esperado';
    if ($puntaje <= 70) return 'En lo esperado';
    if ($puntaje <= 80) return 'Sobre lo esperado';
    return 'Sobresaliente';
}

/**
 * Filtro de visibilidad SQL para listar evaluaciones EVADES según el rol
 * de la sesión. Se usa contra un alias de `colaboradores` (por defecto
 * 'col') porque coordinador_id vive ahí, no en evades_evaluaciones.
 */
function evades_filtro_visibilidad($aliasColaborador = 'col') {
    $rol = $_SESSION['user_rol'] ?? '';
    $uid = (int)($_SESSION['user_id'] ?? 0);
    if (in_array($rol, ['Administrador', 'Supervisor'], true)) return '1=1';
    if ($rol === 'Coordinador' && $uid > 0) return "$aliasColaborador.coordinador_id = $uid";
    return '0=1';
}
