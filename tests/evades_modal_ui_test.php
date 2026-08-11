<?php

$source = file_get_contents(__DIR__ . '/../pages/evades.php');
$total = 0;
$failures = 0;

function ui_has($source, $needle, $message) {
    global $total, $failures;
    $total++;
    if (strpos($source, $needle) !== false) echo "  ok    $message\n";
    else { $failures++; echo "  FALLA $message\n"; }
}

echo "\n── espacio de trabajo visual EVADES ───────────────────────\n";
ui_has($source, 'id="evCoverageMetrics"', 'el generador muestra métricas de cobertura');
ui_has($source, 'function renderCoveragePreview', 'la cobertura se representa con datos del motor');
ui_has($source, 'id="evWorkspaceTabs"', 'el modal separa competencias y retroalimentación');
ui_has($source, 'class="ev-score-formula', 'cada competencia explica la fórmula base, aumento y descuento');
ui_has($source, 'id="evAppreciationPanel"', 'incluye captura de apreciación estructurada');
ui_has($source, 'async function saveAppreciation', 'la apreciación se guarda desde el espacio de trabajo');
ui_has($source, 'ev-view-report-head', 'la visualización usa una cabecera de informe');
ui_has($source, 'Historial del expediente', 'la vista cerrada expone la trazabilidad del bloque');

echo "\n" . ($failures === 0 ? 'TODO OK' : 'HAY FALLOS') . ": $total aserciones, $failures fallidas\n\n";
exit($failures === 0 ? 0 : 1);
