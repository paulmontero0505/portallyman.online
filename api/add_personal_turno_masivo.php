<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Añadir múltiples colaboradores al turno
   Body JSON: { turnoId, ubicacion, colaboradores: [{ colaboradorId, funcion }] }
   Regla: no se puede agregar a alguien que ya trabajó el turno adyacente
          (Día→Noche del mismo día; Noche→Día del día siguiente).
   ═══════════════════════════════════════════════════════════════════════ */
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/acciones.php';
api_require_login();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido']); exit;
}

$turnoId   = isset($data['turnoId'])     ? (int)$data['turnoId']   : 0;
$ubicacion = trim($data['ubicacion']     ?? '');
$horario   = trim($data['horario']       ?? '');
$colabs    = $data['colaboradores']      ?? [];

if ($turnoId <= 0)                        { echo json_encode(['success' => false, 'error' => 'Turno inválido.']);      exit; }
if (!is_array($colabs) || !count($colabs)){ echo json_encode(['success' => false, 'error' => 'Sin colaboradores.']); exit; }

// El alta masiva comparte ubicación, así que también comparte nave: se valida
// una sola vez y se aplica a todos.
require_once('../includes/operaciones_naves.php');   // opn_validar_nave()
$naveId  = isset($data['naveId']) && (int)$data['naveId'] > 0 ? (int)$data['naveId'] : null;
$chkNave = opn_validar_nave($naveId);
if (!$chkNave['ok']) { echo json_encode(['success' => false, 'error' => $chkNave['error']]); exit; }

// Verificar turno abierto y obtener fecha + jornada para la regla de conflicto
$stmtT = mysqli_prepare($conn,
    "SELECT t.estado, t.fecha, j.codigo AS jornada
       FROM turnos t JOIN jornadas j ON j.id = t.jornada_id
      WHERE t.id = ?");
mysqli_stmt_bind_param($stmtT, 'i', $turnoId);
mysqli_stmt_execute($stmtT);
$turnoRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtT));
mysqli_stmt_close($stmtT);

if (!$turnoRow)                          { echo json_encode(['success' => false, 'error' => 'Turno no encontrado.']); exit; }
if ($turnoRow['estado'] === 'cerrado')   { echo json_encode(['success' => false, 'error' => 'El turno está cerrado.']); exit; }
api_require_modify_turno($conn, $turnoId);

// Obtener turnos adyacentes y construir set de conflictos
$fecha   = $turnoRow['fecha'];
$jornada = $turnoRow['jornada'];
$conflictColabs = [];

if ($jornada === 'D') {
    $stmtAdj = mysqli_prepare($conn,
        "SELECT t.id FROM turnos t JOIN jornadas j ON j.id = t.jornada_id
          WHERE j.codigo = 'N' AND t.fecha IN (DATE_SUB(?, INTERVAL 1 DAY), ?)");
    mysqli_stmt_bind_param($stmtAdj, 'ss', $fecha, $fecha);
} else {
    $stmtAdj = mysqli_prepare($conn,
        "SELECT t.id FROM turnos t JOIN jornadas j ON j.id = t.jornada_id
          WHERE j.codigo = 'D' AND t.fecha IN (?, DATE_ADD(?, INTERVAL 1 DAY))");
    mysqli_stmt_bind_param($stmtAdj, 'ss', $fecha, $fecha);
}
mysqli_stmt_execute($stmtAdj);
$resAdj = mysqli_stmt_get_result($stmtAdj);
$adjIds = [];
while ($r = mysqli_fetch_assoc($resAdj)) $adjIds[] = (int)$r['id'];
mysqli_stmt_close($stmtAdj);

if ($adjIds) {
    $inIds    = implode(',', $adjIds);
    $stmtConf = mysqli_query($conn,
        "SELECT DISTINCT colaborador_id FROM turno_personal WHERE turno_id IN ($inIds)");
    while ($r = mysqli_fetch_assoc($stmtConf)) $conflictColabs[(int)$r['colaborador_id']] = true;
}

$ubicacionVal = $ubicacion === '' ? null : $ubicacion;
$horarioVal   = $horario   === '' ? null : $horario;
$agregados    = [];
$errores      = [];

$stmtColab = mysqli_prepare($conn,
    "SELECT codigo, nombre, activo FROM colaboradores WHERE id = ?");
$stmtIns   = mysqli_prepare($conn,
    "INSERT INTO turno_personal (turno_id, colaborador_id, funcion, ubicacion, nave_id, horario_colab, estado)
          VALUES (?, ?, ?, ?, ?, ?, 'activo')");

foreach ($colabs as $item) {
    $colabId = isset($item['colaboradorId']) ? (int)$item['colaboradorId'] : 0;
    $funcion = trim($item['funcion'] ?? '');

    if ($colabId <= 0 || $funcion === '') {
        $errores[] = ['colaboradorId' => $colabId, 'error' => 'Datos incompletos.']; continue;
    }
    if (isset($conflictColabs[$colabId])) {
        $errores[] = ['colaboradorId' => $colabId, 'error' => 'Ya trabajó el turno adyacente.']; continue;
    }

    mysqli_stmt_bind_param($stmtColab, 'i', $colabId);
    mysqli_stmt_execute($stmtColab);
    $colab = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtColab));

    if (!$colab)                        { $errores[] = ['colaboradorId' => $colabId, 'error' => 'No existe.'];   continue; }
    if ((int)$colab['activo'] !== 1)    { $errores[] = ['colaboradorId' => $colabId, 'error' => 'Inactivo.'];   continue; }

    mysqli_stmt_bind_param($stmtIns, 'iissis', $turnoId, $colabId, $funcion, $ubicacionVal, $naveId, $horarioVal);
    $ok  = mysqli_stmt_execute($stmtIns);
    $err = mysqli_stmt_error($stmtIns);

    if (!$ok) {
        $msg = (strpos((string)$err, 'Duplicate entry') !== false)
            ? 'Ya está en este turno.' : $err;
        $errores[] = ['colaboradorId' => $colabId, 'nombre' => $colab['nombre'], 'error' => $msg]; continue;
    }

    $tpId = (int)mysqli_insert_id($conn);
    registrar_accion(
        $conn, $turnoId, 'alta',
        'Agregado al turno · ' . $funcion . ($ubicacionVal ? ' @ ' . $ubicacionVal : '')
            . ($chkNave['nombre'] ? ' · nave ' . $chkNave['nombre'] : '')
            . ($horarioVal ? ' · ' . $horarioVal : ''),
        $colab['codigo'], $colab['nombre']
    );

    $agregados[] = [
        'tpId'          => $tpId,
        'id'            => $colab['codigo'],
        'colaboradorId' => $colabId,
        'nombre'        => $colab['nombre'],
        'funcion'       => $funcion,
        'ubicacion'     => $ubicacionVal,
        'naveId'        => $naveId,
        'naveNombre'    => $chkNave['nombre'],
        'horario_colab' => $horarioVal,
        'estado'        => 'activo',
        'radio'         => 0,
        'bitacora'      => [],
    ];
}

mysqli_stmt_close($stmtColab);
mysqli_stmt_close($stmtIns);

echo json_encode([
    'success'   => count($agregados) > 0,
    'agregados' => $agregados,
    'errores'   => $errores,
]);
