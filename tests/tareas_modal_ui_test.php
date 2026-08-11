<?php

declare(strict_types=1);

session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_name'] = 'UI Test';
$_SESSION['user_rol'] = 'Administrador';
$_SERVER['PHP_SELF'] = '/pages/tareas.php';

$originalDirectory = getcwd();
chdir(__DIR__ . '/../pages');
ob_start();
include 'tareas.php';
$html = (string) ob_get_clean();
chdir($originalDirectory);

$requiredOutput = [
    'class="tk-modal tk-compose"' => 'el diálogo de alta usa su variante visual',
    'class="tk-modal wide tk-detail"' => 'el detalle usa su variante visual',
    'role="dialog"' => 'los modales exponen semántica de diálogo',
    'aria-modal="true"' => 'los modales se anuncian como modales',
    'class="tk-modal-kicker"' => 'los encabezados muestran contexto operativo',
    'class="tk-form-section"' => 'el formulario agrupa campos relacionados',
    'id="tm-priority-options"' => 'la prioridad tiene controles visuales',
    'class="tk-detail-summary"' => 'el detalle muestra un resumen operativo',
    '@media (max-width:720px)' => 'el modal adapta rejillas en pantallas pequeñas',
    '@media (prefers-reduced-motion:reduce)' => 'la animación respeta movimiento reducido',
];

foreach ($requiredOutput as $fragment => $contract) {
    if (!str_contains($html, $fragment)) {
        fwrite(STDERR, "FALLO: {$contract}. Falta en el HTML renderizado: {$fragment}\n");
        exit(1);
    }
}

fwrite(STDOUT, "OK tareas_modal_ui_test\n");
exit(0);
