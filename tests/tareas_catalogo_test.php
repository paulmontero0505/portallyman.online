<?php

date_default_timezone_set('America/Lima');
require_once(__DIR__ . '/../includes/tareas_catalogo.php');

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

$AHORA = strtotime('2026-08-10 09:00:00');

function fila($over = []) {
    return array_merge([
        'id'              => 1,
        'estado'          => 'pendiente',
        'fecha_limite'    => '2026-08-12 23:59:00',
        'fecha_limite_2'  => null,
        'enviado_at'      => null,
        'plazo_al_enviar' => null,
        'asignado_id'     => 7,
        'asignado_soporte_de' => null,
    ], $over);
}

echo "\n── tk_plazo_vigente ──────────────────────────────────────\n";
eq(tk_plazo_vigente(fila()), '2026-08-12 23:59:00',
   'sin 2a fecha usa la fecha 1');
eq(tk_plazo_vigente(fila(['fecha_limite_2' => '2026-08-20 23:59:00'])), '2026-08-20 23:59:00',
   'con 2a fecha usa la 2a');
eq(tk_plazo_vigente(fila(['fecha_limite_2' => ''])), '2026-08-12 23:59:00',
   'una 2a fecha vacia no cuenta como prorroga');

echo "\n── tk_es_abierta / tk_es_terminal ────────────────────────\n";
ok(tk_es_abierta('pendiente'),  'pendiente es abierta');
ok(tk_es_abierta('observada'),  'observada es abierta');
ok(!tk_es_abierta('entregada'), 'entregada NO es abierta (esta en revision)');
ok(!tk_es_abierta('aprobada'),  'aprobada no es abierta');
ok(tk_es_terminal('aprobada'),  'aprobada es terminal');
ok(tk_es_terminal('rechazada'), 'rechazada es terminal');
ok(!tk_es_terminal('observada'),'observada no es terminal');

echo "\n── tk_esta_atrasada ──────────────────────────────────────\n";
ok(!tk_esta_atrasada(fila(), $AHORA),
   'pendiente con plazo futuro no esta atrasada');
ok(tk_esta_atrasada(fila(['fecha_limite' => '2026-08-05 23:59:00']), $AHORA),
   'pendiente con plazo vencido esta atrasada');
ok(tk_esta_atrasada(fila(['estado' => 'observada', 'fecha_limite' => '2026-08-05 23:59:00']), $AHORA),
   'observada con plazo vencido esta atrasada');
ok(!tk_esta_atrasada(fila(['estado' => 'entregada', 'fecha_limite' => '2026-08-05 23:59:00']), $AHORA),
   'entregada NO acumula atraso: ya esta en manos del admin');
ok(!tk_esta_atrasada(fila(['estado' => 'aprobada', 'fecha_limite' => '2026-08-05 23:59:00']), $AHORA),
   'aprobada no acumula atraso');
ok(!tk_esta_atrasada(fila(['fecha_limite' => '2026-08-05 23:59:00',
                           'fecha_limite_2' => '2026-08-20 23:59:00']), $AHORA),
   'la prorroga saca del atraso en el acto, sin tocar filas');

echo "\n── tk_dias_atraso ────────────────────────────────────────\n";
eq(tk_dias_atraso(fila(), $AHORA), 0,
   'sin atraso son 0 dias');
eq(tk_dias_atraso(fila(['fecha_limite' => '2026-08-07 23:59:00']), $AHORA), 3,
   'vencia el 7, hoy es 10 => 3 dias (diferencia de calendario)');
eq(tk_dias_atraso(fila(['fecha_limite' => '2026-08-10 08:00:00']), $AHORA), 0,
   'vencio hace una hora el mismo dia => 0 dias, pero atrasada');
ok(tk_esta_atrasada(fila(['fecha_limite' => '2026-08-10 08:00:00']), $AHORA),
   '... y sigue estando atrasada aunque sean 0 dias');

echo "\n── tk_entregada_tarde ────────────────────────────────────\n";
ok(!tk_entregada_tarde(fila()),
   'sin entrega no hay entrega tardia');
ok(!tk_entregada_tarde(fila(['enviado_at' => '2026-08-12 10:00:00',
                             'plazo_al_enviar' => '2026-08-12 23:59:00'])),
   'entrego antes del plazo sellado');
ok(tk_entregada_tarde(fila(['enviado_at' => '2026-08-14 10:00:00',
                            'plazo_al_enviar' => '2026-08-12 23:59:00'])),
   'entrego despues del plazo sellado');
ok(tk_entregada_tarde(fila(['estado' => 'aprobada',
                            'enviado_at' => '2026-08-14 10:00:00',
                            'plazo_al_enviar' => '2026-08-12 23:59:00',
                            'fecha_limite_2' => '2026-08-30 23:59:00'])),
   'una prorroga concedida DESPUES no borra la marca de entrega tardia');

echo "\n── tk_semaforo ───────────────────────────────────────────\n";
eq(tk_semaforo(fila(['fecha_limite' => '2026-08-05 23:59:00']), $AHORA), 'vencida',
   'plazo pasado => vencida');
eq(tk_semaforo(fila(['fecha_limite' => '2026-08-10 23:59:00']), $AHORA), 'hoy',
   'vence hoy => hoy');
eq(tk_semaforo(fila(['fecha_limite' => '2026-08-11 20:00:00']), $AHORA), 'proxima',
   'vence dentro de 48h => proxima');
eq(tk_semaforo(fila(['fecha_limite' => '2026-08-30 23:59:00']), $AHORA), 'a_tiempo',
   'vence lejos => a_tiempo');
eq(tk_semaforo(fila(['estado' => 'aprobada', 'fecha_limite' => '2026-08-05 23:59:00']), $AHORA), 'a_tiempo',
   'en una tarea cerrada el semaforo no aplica');

echo "\n── tk_filtro_visibilidad ─────────────────────────────────\n";
$_SESSION = ['user_rol' => 'Administrador', 'user_id' => 1];
eq(tk_filtro_visibilidad(), '1=1', 'el administrador ve todo');
$_SESSION = ['user_rol' => 'Supervisor', 'user_id' => 2];
eq(tk_filtro_visibilidad(), '1=1', 'el supervisor ve todo');
$_SESSION = ['user_rol' => 'Soporte', 'user_id' => 9];
eq(tk_filtro_visibilidad(), 't.asignado_id = 9', 'el soporte solo ve las suyas');
$_SESSION = ['user_rol' => 'Coordinador', 'user_id' => 7];
eq(tk_filtro_visibilidad(),
   '(t.asignado_id = 7 OR t.asignado_id IN (SELECT id FROM usuarios WHERE soporte_de_id = 7))',
   'el coordinador ve las suyas y las de su soporte');
$_SESSION = ['user_rol' => 'Operador', 'user_id' => 4];
eq(tk_filtro_visibilidad(), '0=1',
   'un rol no contemplado no ve NADA (fallar cerrado, no abierto)');
$_SESSION = ['user_rol' => 'Coordinador', 'user_id' => 0];
eq(tk_filtro_visibilidad(), '0=1', 'sin user_id no ve nada');

echo "\n── tk_puede_ver ──────────────────────────────────────────\n";
$_SESSION = ['user_rol' => 'Administrador', 'user_id' => 1];
ok(tk_puede_ver(fila()), 'el admin ve cualquier tarea');
$_SESSION = ['user_rol' => 'Coordinador', 'user_id' => 7];
ok(tk_puede_ver(fila(['asignado_id' => 7])), 'el coordinador ve la suya');
ok(!tk_puede_ver(fila(['asignado_id' => 8])), 'el coordinador NO ve la de otro coordinador');
ok(tk_puede_ver(fila(['asignado_id' => 9, 'asignado_soporte_de' => 7])),
   'el coordinador ve la de su soporte');
$_SESSION = ['user_rol' => 'Soporte', 'user_id' => 9];
ok(tk_puede_ver(fila(['asignado_id' => 9])), 'el soporte ve la suya');
ok(!tk_puede_ver(fila(['asignado_id' => 7])), 'el soporte NO ve la de su coordinador');

echo "\n── tk_puede_entregar ─────────────────────────────────────\n";
$_SESSION = ['user_rol' => 'Coordinador', 'user_id' => 7];
ok(tk_puede_entregar(fila(['asignado_id' => 7]))['ok'],
   'el asignado puede entregar su tarea pendiente');
ok(tk_puede_entregar(fila(['asignado_id' => 7, 'estado' => 'observada']))['ok'],
   'el asignado puede reenviar una observada');
ok(!tk_puede_entregar(fila(['asignado_id' => 7, 'estado' => 'entregada']))['ok'],
   'no se entrega dos veces sin que el admin la devuelva');
ok(!tk_puede_entregar(fila(['asignado_id' => 7, 'estado' => 'aprobada']))['ok'],
   'no se entrega una tarea ya aprobada');
ok(!tk_puede_entregar(fila(['asignado_id' => 9, 'asignado_soporte_de' => 7]))['ok'],
   'el coordinador NO entrega por su soporte: la ve en solo lectura');
$_SESSION = ['user_rol' => 'Administrador', 'user_id' => 1];
ok(tk_puede_entregar(fila(['asignado_id' => 7]))['ok'],
   'el admin puede entregar en nombre del asignado');
$_SESSION = ['user_rol' => 'Supervisor', 'user_id' => 2];
ok(!tk_puede_entregar(fila(['asignado_id' => 7]))['ok'],
   'el supervisor mira pero no entrega');

echo "\n── tk_puede_revisar / editar / prorrogar ─────────────────\n";
$_SESSION = ['user_rol' => 'Administrador', 'user_id' => 1];
ok(tk_puede_revisar(fila(['estado' => 'entregada']))['ok'], 'el admin revisa una entregada');
ok(!tk_puede_revisar(fila(['estado' => 'pendiente']))['ok'], 'no se revisa lo que no se entrego');
ok(!tk_puede_revisar(fila(['estado' => 'aprobada']))['ok'],  'no se revisa dos veces');
ok(tk_puede_editar(fila())['ok'], 'el admin edita una pendiente');
ok(!tk_puede_editar(fila(['estado' => 'entregada']))['ok'],
   'no se edita el enunciado de lo que se esta juzgando');
ok(!tk_puede_editar(fila(['estado' => 'aprobada']))['ok'], 'no se edita una terminal');
ok(tk_puede_prorrogar(fila())['ok'], 'el admin prorroga una pendiente');
ok(tk_puede_prorrogar(fila(['estado' => 'observada']))['ok'], 'el admin prorroga una observada');
ok(!tk_puede_prorrogar(fila(['estado' => 'entregada']))['ok'],
   'prorrogar algo ya entregado no significa nada');
$_SESSION = ['user_rol' => 'Coordinador', 'user_id' => 7];
ok(!tk_puede_revisar(fila(['estado' => 'entregada', 'asignado_id' => 7]))['ok'],
   'un coordinador NO se califica a si mismo');
ok(!tk_puede_editar(fila(['asignado_id' => 7]))['ok'], 'un coordinador no edita el enunciado');
ok(!tk_puede_prorrogar(fila(['asignado_id' => 7]))['ok'], 'un coordinador no se da prorrogas');

echo "\n── tk_nota_label ─────────────────────────────────────────\n";
eq(tk_nota_label(1), 'Deficiente',    'la escala es la de Evaluacion de Desempeno');
eq(tk_nota_label(5), 'Sobresaliente', 'nota 5');
eq(tk_nota_label(null), '—',          'sin nota');
eq(tk_nota_label(9), '—',             'una nota fuera de escala no inventa etiqueta');

echo "\n══════════════════════════════════════════════════════════\n";
echo ($FALLOS === 0 ? "TODO OK" : "HAY FALLOS") . ": $TOTAL aserciones, $FALLOS fallidas\n\n";
exit($FALLOS === 0 ? 0 : 1);
