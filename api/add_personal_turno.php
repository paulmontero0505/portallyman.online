<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Añadir colaborador al turno
   Body JSON: { turnoId, colaboradorId, funcion, ubicacion, naveId? }
   Entra siempre con estado 'activo'.
   `naveId` es opcional y sólo tiene sentido cuando la ubicación es un muelle.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/acciones.php');
require_once('../includes/operaciones_naves.php');   // opn_validar_nave()
api_require_login();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido']); exit;
}

$turnoId    = isset($data['turnoId'])       ? (int)$data['turnoId']       : 0;
$colabId    = isset($data['colaboradorId']) ? (int)$data['colaboradorId'] : 0;
$funcion    = trim($data['funcion']   ?? '');
$ubicacion  = trim($data['ubicacion'] ?? '');

if ($turnoId <= 0)  { echo json_encode(['success' => false, 'error' => 'Turno inválido.']); exit; }

// Verificar turno y obtener fecha+jornada para la regla de conflicto.
$stmtT = mysqli_prepare($conn,
    "SELECT t.estado, t.fecha, j.codigo AS jornada
       FROM turnos t JOIN jornadas j ON j.id = t.jornada_id WHERE t.id = ?");
mysqli_stmt_bind_param($stmtT, 'i', $turnoId);
mysqli_stmt_execute($stmtT);
$turnoRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtT));
mysqli_stmt_close($stmtT);

if (!$turnoRow) { echo json_encode(['success' => false, 'error' => 'Turno no encontrado.']); exit; }
if ($turnoRow['estado'] === 'cerrado') {
    echo json_encode(['success' => false, 'error' => 'El turno está cerrado. Reábrelo para modificarlo.']); exit;
}
api_require_modify_turno($conn, $turnoId);

// Regla turnos adyacentes: Día↔Noche consecutivos no pueden compartir personal.
$fecha   = $turnoRow['fecha'];
$jornada = $turnoRow['jornada'];
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

if ($adjIds && $colabId > 0) {
    $inIds      = implode(',', $adjIds);
    $stmtConf   = mysqli_prepare($conn,
        "SELECT 1 FROM turno_personal WHERE turno_id IN ($inIds) AND colaborador_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtConf, 'i', $colabId);
    mysqli_stmt_execute($stmtConf);
    $confRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtConf));
    mysqli_stmt_close($stmtConf);
    if ($confRow) {
        echo json_encode(['success' => false,
            'error' => 'Este colaborador ya trabajó el turno adyacente y no puede ser agregado.']); exit;
    }
}
if ($colabId <= 0)  { echo json_encode(['success' => false, 'error' => 'Colaborador inválido.']); exit; }
if ($funcion === '') { echo json_encode(['success' => false, 'error' => 'Función requerida.']); exit; }
$ubicacionVal = $ubicacion === '' ? null : $ubicacion;

// Nave a la que se asigna (opcional). Como `naves` vive en otra base no hay
// clave foránea: esta validación es lo único que impide guardar una
// referencia rota.
$naveId  = isset($data['naveId']) && (int)$data['naveId'] > 0 ? (int)$data['naveId'] : null;
$chkNave = opn_validar_nave($naveId);
if (!$chkNave['ok']) { echo json_encode(['success' => false, 'error' => $chkNave['error']]); exit; }

// Verificar que el colaborador existe y está activo.
$stmt = mysqli_prepare($conn, "SELECT codigo, nombre, activo FROM colaboradores WHERE id=?");
mysqli_stmt_bind_param($stmt, 'i', $colabId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$colab = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$colab)              { echo json_encode(['success' => false, 'error' => 'El colaborador no existe.']); exit; }
if ((int)$colab['activo'] !== 1) { echo json_encode(['success' => false, 'error' => 'El colaborador está inactivo.']); exit; }

// Insertar en el turno.
$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO turno_personal (turno_id, colaborador_id, funcion, ubicacion, nave_id, estado)
          VALUES (?, ?, ?, ?, ?, 'activo')"
);
mysqli_stmt_bind_param($stmt, 'iissi', $turnoId, $colabId, $funcion, $ubicacionVal, $naveId);
$ok  = mysqli_stmt_execute($stmt);
$err = mysqli_stmt_error($stmt);
$tpId = (int)mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

if (!$ok) {
    $msg = (strpos((string)$err, 'Duplicate entry') !== false)
        ? 'Ese colaborador ya está en el turno.'
        : $err;
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

registrar_accion(
    $conn, $turnoId, 'alta',
    'Agregado al turno · ' . $funcion . ($ubicacionVal ? ' @ ' . $ubicacionVal : '')
        . ($chkNave['nombre'] ? ' · nave ' . $chkNave['nombre'] : ''),
    $colab['codigo'], $colab['nombre']
);

echo json_encode([
    'success' => true,
    'persona' => [
        'tpId'          => $tpId,
        'id'            => $colab['codigo'],
        'colaboradorId' => $colabId,
        'nombre'        => $colab['nombre'],
        'funcion'       => $funcion,
        'ubicacion'     => $ubicacionVal,
        'naveId'        => $naveId,
        'naveNombre'    => $chkNave['nombre'],
        'estado'        => 'activo',
        'radio'         => 0,
        'bitacora'      => [],
    ],
]);
