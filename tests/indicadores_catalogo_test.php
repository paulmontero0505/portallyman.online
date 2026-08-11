<?php
date_default_timezone_set('America/Lima');

require_once(__DIR__ . '/../includes/indicadores_catalogo.php');

$TOTAL = 0; $FALLOS = 0;

function ok($cond, $msg) {
    global $TOTAL, $FALLOS;
    $TOTAL++;
    if ($cond) echo "  ok    $msg\n";
    else { $FALLOS++; echo "  FALLA $msg\n"; }
}
function eq($actual, $esperado, $msg) {
    ok($actual === $esperado, $msg . '  (esperado: ' . var_export($esperado, true)
        . ', obtenido: ' . var_export($actual, true) . ')');
}

echo "\n── ind_valor_team ──────────────────────────────────────────\n";
eq(ind_valor_team('Ratio', 4, 5), 0.8, 'Ratio = numerador/denominador');
eq(ind_valor_team('Ratio', 4, 0), null, 'Ratio con denominador 0 es null (evita division por cero)');
eq(ind_valor_team('Ratio', null, 5), null, 'Ratio sin numerador es null');
eq(ind_valor_team('Suma', 12, null), 12, 'Suma ignora el denominador');
eq(ind_valor_team('Suma', null, null), null, 'Suma sin numerador es null');
eq(ind_valor_team('Promedio', 3.5, null), 3.5, 'Promedio por team es el numerador tal cual');
eq(ind_valor_team('Binario', 1, null), 1, 'Binario con numerador > 0 es 1');
eq(ind_valor_team('Binario', 0, null), 0, 'Binario con numerador 0 es 0');
eq(ind_valor_team('Binario', null, null), null, 'Binario sin dato es null');

echo "\n── ind_resultado_general ───────────────────────────────────\n";
eq(ind_resultado_general('Ratio', [], 16, 17), 16 / 17, 'Ratio general = TotalN/TotalD');
eq(ind_resultado_general('Ratio', [], 0, 0), null, 'Ratio general sin datos es null');
eq(ind_resultado_general('Suma', [], 44, null), 44, 'Suma general = TotalN');
eq(ind_resultado_general('Promedio', [0.8, 1.0, 1.0], null, null), (0.8 + 1.0 + 1.0) / 3, 'Promedio general = AVERAGE de los valores por team');
eq(ind_resultado_general('Promedio', [], null, null), null, 'Promedio sin ningun team con dato es null');
eq(ind_resultado_general('Binario', [], 3, null), 1, 'Binario general = 1 si TotalN > 0');
eq(ind_resultado_general('Binario', [], 0, null), 0, 'Binario general = 0 si TotalN = 0');
eq(ind_resultado_general('Binario', [], null, null), null, 'Binario general sin dato es null');

echo "\n── ind_pct_vs_meta · operador >= (mas es mejor) ────────────\n";
eq(ind_pct_vs_meta(0.9411764705882353, 1), 0.9411764705882353, '%vsMeta = resultado/meta');
eq(ind_pct_vs_meta(null, 1), null, 'sin resultado es null');
eq(ind_pct_vs_meta(5, 0), null, 'meta 0 es null (evita division por cero)');
eq(ind_pct_vs_meta(6, 7, '>='), 6 / 7, 'operador >= explicito se comporta igual que el default');

echo "\n── ind_pct_vs_meta · operador <= (menos es mejor) ──────────\n";
// Valores tomados de la hoja "Datos Mensuales" del Excel original, que usa
// =IFERROR(IF(X="","",IF(X=0,2,F/X)),"") en las filas con operador '<='.
eq(ind_pct_vs_meta(0.36, 0.2, '<='), 0.2 / 0.36, 'G1.4 Junio: reincidencia 0.36 vs meta 0.2 da 0.5556 (NO CUMPLE)');
eq(ind_pct_vs_meta(0.1, 0.05, '<='), 0.5, 'G3.3 Junio: 10% EPP incompleto vs meta 5% da 0.5 (NO CUMPLE)');
eq(ind_pct_vs_meta(2.1562, 3, '<='), 3 / 2.1562, 'G2.3 Junio: 2.15 dias vs meta 3 da 1.39 (CUMPLE)');
eq(ind_pct_vs_meta(0, 0.05, '<='), 2.0, 'resultado 0 en un <= es cumplimiento perfecto: tope 2, no division por cero');
eq(ind_pct_vs_meta(0, 0.01, '<='), 2.0, 'el tope 2 no depende del valor de la meta');
eq(ind_pct_vs_meta(null, 0.05, '<='), null, 'sin resultado sigue siendo null');
ok(ind_estado(ind_pct_vs_meta(0.36, 0.2, '<=')) === 'NO CUMPLE', 'reincidencia del 36% con meta 20% NO cumple');
ok(ind_estado(ind_pct_vs_meta(0, 0.05, '<=')) === 'CUMPLE', 'cero incumplimientos de EPP CUMPLE');
ok(ind_estado(ind_pct_vs_meta(2.5134, 3, '<=')) === 'CUMPLE', 'responder en 2.5 dias con meta de 3 CUMPLE');

echo "\n── ind_estado ──────────────────────────────────────────────\n";
eq(ind_estado(null), 'SIN DATO', 'sin %vsMeta es SIN DATO');
eq(ind_estado(1), 'CUMPLE', '%vsMeta = 1 exacto es CUMPLE');
eq(ind_estado(1.5), 'CUMPLE', '%vsMeta > 1 es CUMPLE');
eq(ind_estado(0.8), 'EN RIESGO', '%vsMeta = 0.8 exacto es EN RIESGO');
eq(ind_estado(0.99), 'EN RIESGO', '%vsMeta entre 0.8 y 1 es EN RIESGO');
eq(ind_estado(0.79), 'NO CUMPLE', '%vsMeta < 0.8 es NO CUMPLE');
eq(ind_estado(0), 'NO CUMPLE', '%vsMeta = 0 es NO CUMPLE');

echo "\n── ind_periodo_fechas / ind_trimestre_de_periodo ───────────\n";
eq(ind_periodo_fechas('2026-06'), ['inicio' => '2026-06-01', 'fin' => '2026-06-30'], 'junio: 30 dias');
eq(ind_periodo_fechas('2026-02'), ['inicio' => '2026-02-01', 'fin' => '2026-02-28'], 'febrero no bisiesto: 28 dias');
eq(ind_periodo_fechas('2028-02'), ['inicio' => '2028-02-01', 'fin' => '2028-02-29'], 'febrero bisiesto: 29 dias');
eq(ind_periodo_fechas('2026-13'), null, 'mes invalido devuelve null');
eq(ind_trimestre_de_periodo('2026-01'), '2026-T1', 'enero cae en T1');
eq(ind_trimestre_de_periodo('2026-06'), '2026-T2', 'junio cae en T2');
eq(ind_trimestre_de_periodo('2026-09'), '2026-T3', 'setiembre cae en T3');
eq(ind_trimestre_de_periodo('2026-12'), '2026-T4', 'diciembre cae en T4');

echo "\n── ind_team_normalizado ─────────────────────────────────────\n";
eq(ind_team_normalizado('TEAM A'), 'TEAM A', 'formato limpio se reconoce tal cual');
eq(ind_team_normalizado('G1 TEAM A'), 'TEAM A', 'extrae el team de un prefijo de gestion');
eq(ind_team_normalizado('team b'), 'TEAM B', 'no distingue mayusculas/minusculas');
eq(ind_team_normalizado('DIURNO'), null, 'valor sin patron TEAM [A-D] es null');
eq(ind_team_normalizado('SIN ASIGNAR'), null, 'sin asignar es null');
eq(ind_team_normalizado(null), null, 'null es null');

echo "\n══════════════════════════════════════════════════════════\n";
echo ($FALLOS === 0 ? "TODO OK" : "HAY FALLOS") . ": $TOTAL aserciones, $FALLOS fallidas\n\n";
exit($FALLOS === 0 ? 0 : 1);
