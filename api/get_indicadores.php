<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Indicadores — catálogo + valores computados (JSON)
   Query params: periodo=YYYY-MM (requerido), team=TEAM A..D (opcional)
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/indicadores_catalogo.php');
require_once('../includes/indicadores_engine.php');
api_require_indicadores();

header('Content-Type: application/json');

$periodo = $_GET['periodo'] ?? '';
if (ind_periodo_fechas($periodo) === null) {
    echo json_encode(['success' => false, 'error' => 'Periodo inválido, use formato YYYY-MM.']);
    exit;
}

$teams = ind_teams();

$r = mysqli_query($conn, "SELECT * FROM indicadores_catalogo WHERE activo=1 ORDER BY codigo");

if (!$r) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

$catalogo = [];
while ($row = mysqli_fetch_assoc($r)) $catalogo[] = $row;

// Capturas manuales del periodo, indexadas por indicador+team.
$capturas = [];
$stmt = mysqli_prepare($conn, "SELECT * FROM indicadores_captura WHERE periodo=?");
mysqli_stmt_bind_param($stmt, 's', $periodo);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) $capturas[$row['indicador_codigo'] . '|' . $row['team']] = $row;
mysqli_stmt_close($stmt);

$out = [];
foreach ($catalogo as $ind) {
    $codigo = $ind['codigo'];
    $porTeam = [];
    $valoresPorTeam = [];
    $totalN = null; $totalD = null; $huboDato = false;

    foreach ($teams as $team) {
        $auto = $ind['fuente_automatica'] ? ind_calcular_automatico($conn, $codigo, $periodo, $team) : null;
        $captura = $capturas[$codigo . '|' . $team] ?? null;

        $numerador = $auto['numerador'] ?? ($captura !== null && $captura['numerador'] !== null ? (float)$captura['numerador'] : null);
        $denominador = $auto['denominador'] ?? ($captura !== null && $captura['denominador'] !== null ? (float)$captura['denominador'] : null);

        $valor = ind_valor_team($ind['tipo_calculo'], $numerador, $denominador);
        if ($valor !== null) $huboDato = true;
        $valoresPorTeam[] = $valor;

        if ($numerador !== null) $totalN = ($totalN ?? 0) + $numerador;
        if ($denominador !== null) $totalD = ($totalD ?? 0) + $denominador;

        $porTeam[$team] = [
            'numerador' => $numerador,
            'denominador' => $denominador,
            'valor' => $valor,
            'automatico' => $auto !== null,
            'fuente' => $auto['fuente'] ?? null,
            'n_registros' => $auto['n_registros'] ?? null,
        ];
    }

    $resultadoGeneral = $huboDato ? ind_resultado_general($ind['tipo_calculo'], $valoresPorTeam, $totalN, $totalD) : null;
    $pctVsMeta = ind_pct_vs_meta($resultadoGeneral, (float)$ind['meta'], $ind['operador']);

    // Que campos admite capturar a mano. Es la MISMA regla que aplica
    // api/save_indicador_captura.php al validar; se publica aqui para que la
    // interfaz no tenga que deducirla y no se desincronicen. G1.1 es hoy el
    // unico indicador parcial: su numerador sale del sistema y su denominador
    // ("charlas programadas") no vive en ningun modulo, se escribe a mano.
    $esParcial = ($codigo === 'G1.1');
    $capturaNumerador = ($ind['fuente_automatica'] === null);
    $capturaDenominador = ($ind['fuente_automatica'] === null || $esParcial);

    $out[] = [
        'codigo' => $codigo,
        'gestion_codigo' => $ind['gestion_codigo'],
        'gestion_nombre' => $ind['gestion_nombre'],
        'kpi' => $ind['kpi'],
        'objetivo' => $ind['objetivo'],
        'formula' => $ind['formula'],
        'entregable' => $ind['entregable'],
        'numerador_label' => $ind['numerador_label'],
        'denominador_label' => $ind['denominador_label'],
        'tipo_calculo' => $ind['tipo_calculo'],
        'meta' => (float)$ind['meta'],
        'operador' => $ind['operador'],
        'unidad' => $ind['unidad'],
        'frecuencia' => $ind['frecuencia'],
        'automatico' => $ind['fuente_automatica'] !== null,
        'captura_numerador' => $capturaNumerador,
        'captura_denominador' => $capturaDenominador,
        'teams' => $porTeam,
        'resultado_general' => $resultadoGeneral,
        'pct_vs_meta' => $pctVsMeta,
        'estado' => ind_estado($pctVsMeta),
    ];
}

echo json_encode(['success' => true, 'periodo' => $periodo, 'data' => $out]);
