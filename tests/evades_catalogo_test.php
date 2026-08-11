<?php

require_once(__DIR__ . '/../includes/evades_catalogo.php');

$TOTAL = 0; $FALLOS = 0;

function ok($cond, $msg) {
    global $TOTAL, $FALLOS;
    $TOTAL++;
    if ($cond) { echo "  ok    $msg\n"; }
    else       { $FALLOS++; echo "  FALLA $msg\n"; }
}
function eq($actual, $esperado, $msg) {
    ok($actual === $esperado, $msg . "  (esperado: " . var_export($esperado, true)
        . ", obtenido: " . var_export($actual, true) . ")");
}

echo "\n── evades_competencias ───────────────────────────────────\n";
$comp = evades_competencias();
eq(count($comp), 10, 'son 10 competencias');
$conductuales = array_filter($comp, fn($c) => $c['tipo'] === 'conductual');
$operativas   = array_filter($comp, fn($c) => $c['tipo'] === 'operativa');
eq(count($conductuales), 5, '5 conductuales (Sección A)');
eq(count($operativas), 5, '5 operativas (Sección B)');
ok(count($comp) * 10 === 100, 'máximo teórico: 10 competencias x 10 pts = 100');

echo "\n── evades_tiene_automatizacion ───────────────────────────\n";
ok(evades_tiene_automatizacion('autonomia'), 'autonomia tiene incremento automatico');
ok(!evades_tiene_automatizacion('productividad'), 'productividad es 100% manual');
ok(evades_tiene_automatizacion('eficiencia'), 'eficiencia usa EPT e incidencias tecnicas');
ok(evades_tiene_automatizacion('dominio_solido'), 'dominio_solido tiene descuento automatico');
ok(evades_tiene_automatizacion('seguridad_trabajo'), 'seguridad_trabajo tiene ambos lados');
ok(!evades_tiene_automatizacion('no_existe'), 'clave inexistente no tiene automatizacion');

echo "\n── evades_incrementos_validos / evades_descuentos_validos ─\n";
$incValidos = evades_incrementos_validos();
eq($incValidos, [0, 2, 4], 'la guia limita el incremento por competencia a +2 o +4');
ok(!in_array(6, $incValidos, true), 'varias fuentes positivas no se acumulan por encima de +4');
eq(evades_descuentos_validos(), [0, 2, 4, 6, 8, 10], 'descuentos validos = pares del Anexo 2');

echo "\n── evades_reglas_evidencia / relaciones cruzadas ──────────\n";
ok(function_exists('evades_reglas_evidencia'), 'existe el catalogo integral de evidencia');
ok(function_exists('evades_umbrales_ept'), 'existen umbrales EPT explicitos');
ok(function_exists('evades_claves_afectadas_por_punto'), 'existe el mapeo canonico por punto a mejorar');
if (function_exists('evades_reglas_evidencia')) {
    $reglas = evades_reglas_evidencia();
    eq(count($reglas), 10, 'las diez competencias declaran sus fuentes');
    eq($reglas['eficiencia']['cruzadas'], ['dominio_solido'], 'Eficiencia cruza con Dominio solido');
    eq($reglas['dominio_solido']['puntos_incidencia'], [
        'Errores de pedeteo', 'Error de registro en balanzas', 'Registro de USR',
        'Registro de CDR', 'Trabajo en PS',
    ], 'Dominio usa los cinco procedimientos tecnicos');
}
if (function_exists('evades_umbrales_ept')) {
    eq(evades_umbrales_ept(), ['minimo' => 3, 'nivel_2' => 4.0, 'nivel_4' => 4.5], 'umbrales EPT configurables');
}
if (function_exists('evades_claves_afectadas_por_punto')) {
    eq(evades_claves_afectadas_por_punto('Errores de pedeteo'), ['dominio_solido', 'eficiencia'], 'error tecnico afecta primaria y cruzada');
    eq(evades_claves_afectadas_por_punto('Continuidad operativa'), ['comunicacion_colaboracion'], 'continuidad solo afecta Comunicacion');
}

echo "\n── evades_matriz_fi ───────────────────────────────────────\n";
$matriz = evades_matriz_fi();
eq(count($matriz), 5, '5 filas de frecuencia');
foreach ($matriz as $fila) eq(count($fila), 5, 'cada fila tiene las 5 columnas de impacto');
eq($matriz[3]['moderado'], 6, 'ejemplo de la guia: F3 x Impacto Medio = 6 (Anexo 2)');
eq($matriz[5]['critico'], 10, 'F5 x Critico = tope de 10');
eq($matriz[1]['minimo'], 2, 'F1 x Minimo = 2');

echo "\n── evades_periodo_fechas ─────────────────────────────────\n";
eq(evades_periodo_fechas('2026-T1'), ['inicio' => '2026-01-01', 'fin' => '2026-03-31'], 'T1 = ene-mar');
eq(evades_periodo_fechas('2026-T2'), ['inicio' => '2026-04-01', 'fin' => '2026-06-30'], 'T2 = abr-jun');
eq(evades_periodo_fechas('2026-T3'), ['inicio' => '2026-07-01', 'fin' => '2026-09-30'], 'T3 = jul-sep');
eq(evades_periodo_fechas('2026-T4'), ['inicio' => '2026-10-01', 'fin' => '2026-12-31'], 'T4 = oct-dic');
eq(evades_periodo_fechas('2026-T5'), null, 'trimestre invalido devuelve null');
eq(evades_periodo_fechas('26-T1'), null, 'formato de anio invalido devuelve null');

echo "\n── evades_clasificacion ──────────────────────────────────\n";
eq(evades_clasificacion(0), 'Debajo de lo esperado', 'minimo');
eq(evades_clasificacion(54), 'Debajo de lo esperado', 'borde superior de Debajo');
eq(evades_clasificacion(55), 'En lo esperado', 'borde inferior de En lo esperado');
eq(evades_clasificacion(70), 'En lo esperado', 'borde superior de En lo esperado');
eq(evades_clasificacion(71), 'Sobre lo esperado', 'borde inferior de Sobre lo esperado');
eq(evades_clasificacion(80), 'Sobre lo esperado', 'borde superior de Sobre lo esperado');
eq(evades_clasificacion(81), 'Sobresaliente', 'borde inferior de Sobresaliente');
eq(evades_clasificacion(100), 'Sobresaliente', 'maximo');

echo "\n── evades_filtro_visibilidad ─────────────────────────────\n";
$_SESSION = ['user_rol' => 'Administrador', 'user_id' => 1];
eq(evades_filtro_visibilidad(), '1=1', 'el administrador ve todo');
$_SESSION = ['user_rol' => 'Supervisor', 'user_id' => 2];
eq(evades_filtro_visibilidad(), '1=1', 'el supervisor ve todo');
$_SESSION = ['user_rol' => 'Coordinador', 'user_id' => 7];
eq(evades_filtro_visibilidad(), 'col.coordinador_id = 7', 'el coordinador ve solo los suyos');
eq(evades_filtro_visibilidad('c'), 'c.coordinador_id = 7', 'respeta el alias que se le pase');
$_SESSION = ['user_rol' => 'Coordinador', 'user_id' => 0];
eq(evades_filtro_visibilidad(), '0=1', 'sin user_id no ve nada');
$_SESSION = ['user_rol' => 'Operador', 'user_id' => 4];
eq(evades_filtro_visibilidad(), '0=1', 'un rol no contemplado no ve nada');

echo "\n── evades_puestos_validos / estados de bloque ─────────────\n";
eq(evades_normalizar_puesto('Analista de Trouble Desk'), 'ANALISTA DE TROUBLE DESK', 'normaliza Analista');
eq(evades_normalizar_puesto('asistente de estiba'), 'ASISTENTE DE ESTIBA', 'normaliza Asistente');
eq(evades_normalizar_puesto('Coordinador'), null, 'rechaza puesto fuera de EVADES');
eq(evades_puestos_validos(), ['ASISTENTE DE ESTIBA', 'ANALISTA DE TROUBLE DESK'], 'solo admite los dos puestos aprobados');
eq(evades_estados_bloque(), ['generado', 'revisado', 'modificado', 'cerrado'], 'estados en orden de flujo');
ok(evades_transicion_bloque_valida('generado', 'revisado'), 'primera apertura permitida');
ok(evades_transicion_bloque_valida('revisado', 'modificado'), 'primer cambio permitido');
ok(evades_transicion_bloque_valida('modificado', 'cerrado'), 'cierre permitido');
ok(!evades_transicion_bloque_valida('cerrado', 'modificado'), 'cerrado no se reabre');
ok(evades_bloque_editable('generado'), 'generado sigue editable');
ok(evades_bloque_editable('modificado'), 'modificado sigue editable');
ok(!evades_bloque_editable('cerrado'), 'cerrado es inmutable');

echo "\n══════════════════════════════════════════════════════════\n";
echo ($FALLOS === 0 ? "TODO OK" : "HAY FALLOS") . ": $TOTAL aserciones, $FALLOS fallidas\n\n";
exit($FALLOS === 0 ? 0 : 1);
