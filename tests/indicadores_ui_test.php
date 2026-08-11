<?php

$source = file_get_contents(__DIR__ . '/../pages/indicadores.php');
$total = 0;
$failures = 0;

function ui_has($source, $needle, $message) {
    global $total, $failures;
    $total++;
    if (strpos($source, $needle) !== false) echo "  ok    $message\n";
    else { $failures++; echo "  FALLA $message\n"; }
}

echo "\n── estructura del panel Indicadores ────────────────────────\n";
ui_has($source, 'require_indicadores()', 'la pagina exige permiso de indicadores');
ui_has($source, 'id="indTabs"', 'existe la barra de pestanas');
ui_has($source, 'data-tab="inicio"', 'pestana Inicio');
ui_has($source, 'data-tab="dashboard"', 'pestana Dashboard');
ui_has($source, 'data-tab="resumen"', 'pestana Resumen Gestion');
ui_has($source, 'data-tab="datos"', 'pestana Datos Mensuales');
ui_has($source, 'data-tab="catalogo"', 'pestana Catalogo');
ui_has($source, 'data-tab="cronograma"', 'pestana Cronograma');
ui_has($source, 'id="indPanelInicio"', 'panel Inicio');
ui_has($source, 'id="indPanelDashboard"', 'panel Dashboard');
ui_has($source, 'id="indPanelResumen"', 'panel Resumen');
ui_has($source, 'id="indPanelDatos"', 'panel Datos Mensuales');
ui_has($source, 'id="indPanelCatalogo"', 'panel Catalogo');
ui_has($source, 'id="indPanelCronograma"', 'panel Cronograma');
ui_has($source, 'indOnTabShown', 'las pestanas notifican al cambiar para lazy-load');

echo "\n── pestana Datos Mensuales ──────────────────────────────────\n";
ui_has($source, 'async function loadDatosMensuales', 'carga los datos mensuales via fetch');
ui_has($source, 'get_indicadores.php', 'consume el endpoint de valores computados');
ui_has($source, 'save_indicador_captura.php', 'guarda la captura manual');
ui_has($source, 'ind-badge-auto', 'distingue visualmente los indicadores automaticos');

echo "\n── pestana Catalogo ─────────────────────────────────────────\n";
ui_has($source, 'async function loadCatalogo', 'carga el catalogo');
ui_has($source, 'save_indicador_catalogo.php', 'permite editar meta desde el catalogo');

echo "\n── pestana Cronograma ───────────────────────────────────────\n";
ui_has($source, 'async function loadCronograma', 'carga el cronograma');
ui_has($source, 'save_indicador_cronograma.php', 'permite editar el team responsable');

echo "\n── pestana Resumen Gestion ──────────────────────────────────\n";
ui_has($source, 'async function loadResumenGestion', 'carga el resumen por gestion');
ui_has($source, 'indAgruparPorGestion', 'agrupa los indicadores por gestion');

echo "\n── pestana Dashboard ────────────────────────────────────────\n";
ui_has($source, 'async function loadDashboard', 'carga el dashboard');
ui_has($source, 'indVistaTeam', 'permite filtrar la vista por team');

echo "\n── robustez compartida ──────────────────────────────────────\n";
ui_has($source, 'function indEsc', 'escapa el texto que viene de la base antes de inyectarlo');
ui_has($source, "indMesInputHtml('indDatosPeriodoInput')", 'cada pestana usa un id propio para su selector de mes');
ui_has($source, "indMesInputHtml('indResumenPeriodoInput')", 'Resumen no duplica el id del selector de Datos');
ui_has($source, "indMesInputHtml('indDashPeriodoInput')", 'Dashboard no duplica el id del selector de Datos');

echo "\n── correcciones de la revision final ────────────────────────\n";
ui_has($source, 'function indPctVsMeta', 'el cliente replica la formula del servidor en vez de dividir plano');
ui_has($source, 'indPctVsMeta(t.valor, i.meta, i.operador)', 'la vista por team del Dashboard respeta el operador <=');
ui_has($source, '!ind.captura_numerador && !ind.captura_denominador', 'solo es solo-lectura si NADA se captura (G1.1 sigue capturable)');
ui_has($source, 'function indCambiarPeriodo', 'cambiar el mes invalida las otras pestanas');
ui_has($source, 'function indInvalidarVistasDerivadas', 'tras escribir se refrescan Resumen y Dashboard');
ui_has($source, 'function indHuellaInput', 'el repintado tras guardar devuelve el foco y lo tecleado');
ui_has($source, 'IND_MI_TEAM', 'el Coordinador no recibe inputs de teams ajenos');
ui_has($source, 'function indFallo', 'una carga fallida no deja la pestana muerta');

echo "\n" . ($failures === 0 ? 'TODO OK' : 'HAY FALLOS') . ": $total aserciones, $failures fallidas\n\n";
exit($failures === 0 ? 0 : 1);
