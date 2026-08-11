<?php

require_once(__DIR__ . '/../includes/auth.php');

$TOTAL = 0; $FALLOS = 0;

function ok($cond, $msg) {
    global $TOTAL, $FALLOS;
    $TOTAL++;
    if ($cond) echo "  ok    $msg\n";
    else { $FALLOS++; echo "  FALLA $msg\n"; }
}

echo "\n── can_indicadores ──────────────────────────────────────────\n";
$_SESSION = ['user_rol' => 'Administrador'];
ok(can_indicadores(), 'Administrador puede');
$_SESSION = ['user_rol' => 'Supervisor'];
ok(can_indicadores(), 'Supervisor puede');
$_SESSION = ['user_rol' => 'Coordinador'];
ok(can_indicadores(), 'Coordinador puede');
$_SESSION = ['user_rol' => 'Soporte'];
ok(!can_indicadores(), 'Soporte no puede');
$_SESSION = ['user_rol' => 'Operador'];
ok(!can_indicadores(), 'Operador no puede');

echo "\n══════════════════════════════════════════════════════════\n";
echo ($FALLOS === 0 ? "TODO OK" : "HAY FALLOS") . ": $TOTAL aserciones, $FALLOS fallidas\n\n";
exit($FALLOS === 0 ? 0 : 1);
