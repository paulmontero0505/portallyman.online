<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/turno.php');   // turno_conflict_set()
api_require_login();

header('Content-Type: application/json');

// Conflicto de turno adyacente. Se puede pedir de dos formas:
//   · ?turnoId=N                       → se resuelven fecha+jornada del turno
//   · ?fecha=YYYY-MM-DD&jornada=D|N    → directo (sin crear turno alguno)
$turnoId = 0;
if     (isset($_GET['turnoId']))  $turnoId = (int)$_GET['turnoId'];
elseif (isset($_POST['turnoId'])) $turnoId = (int)$_POST['turnoId'];
else {
    $body = json_decode(file_get_contents('php://input'), true);
    if (is_array($body) && isset($body['turnoId'])) $turnoId = (int)$body['turnoId'];
}

$fechaConf   = isset($_GET['fecha'])   ? trim($_GET['fecha'])              : '';
$jornadaConf = isset($_GET['jornada']) ? strtoupper(trim($_GET['jornada'])) : '';

// Construye el set de colaboradorIds que ya trabajaron en el turno adyacente
$conflictSet = [];
if ($turnoId > 0) {
    $stmtT = mysqli_prepare($conn,
        "SELECT t.fecha, j.codigo
           FROM turnos t JOIN jornadas j ON j.id = t.jornada_id
          WHERE t.id = ?");
    mysqli_stmt_bind_param($stmtT, 'i', $turnoId);
    mysqli_stmt_execute($stmtT);
    $rowT = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtT));
    mysqli_stmt_close($stmtT);
    if ($rowT) {
        $conflictSet = turno_conflict_set($conn, $rowT['fecha'], $rowT['codigo']);
    }
} elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaConf) && in_array($jornadaConf, ['D', 'N'], true)) {
    $conflictSet = turno_conflict_set($conn, $fechaConf, $jornadaConf);
}

// LEFT JOIN a usuarios: el coordinador a cargo es un usuario con rol
// 'Coordinador'. Es LEFT para que los colaboradores sin asignar se sigan
// devolviendo (coordinador_id NULL).
$r = mysqli_query(
    $conn,
    "SELECT c.id, c.codigo, c.dni, c.celular, c.nombre, c.fecha_nacimiento, c.fecha_ingreso, c.funcion_principal, c.tipo_funcion,
            c.cuadrilla, c.activo, c.coordinador_id, u.nombre AS coordinador_nombre,
            c.created_at, c.updated_at
       FROM colaboradores c
       LEFT JOIN usuarios u ON u.id = c.coordinador_id
       ORDER BY c.cuadrilla ASC, c.nombre ASC"
);

if (!$r) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

$out = [];
while ($row = mysqli_fetch_assoc($r)) {
    $row['activo']         = (int)$row['activo'];
    $row['coordinador_id'] = $row['coordinador_id'] === null ? null : (int)$row['coordinador_id'];
    $row['conflicto']      = isset($conflictSet[(int)$row['id']]);
    $out[] = $row;
}

echo json_encode(['success' => true, 'data' => $out]);
