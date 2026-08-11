<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/sheets.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido']);
    exit;
}

$id           = isset($data['id']) ? (int)$data['id'] : 0;
$codigo       = trim($data['codigo'] ?? '');
$dni          = trim($data['dni'] ?? '');
$nombre       = trim($data['nombre'] ?? '');
$funcion      = trim($data['funcion_principal'] ?? '');
$tipo_funcion = trim($data['tipo_funcion'] ?? '');
$cuadrilla    = trim($data['cuadrilla'] ?? '');
$activo       = !empty($data['activo']) ? 1 : 0;
$coordinador  = isset($data['coordinador_id']) ? (int)$data['coordinador_id'] : 0;

// Validaciones
if ($codigo === '' || !preg_match('/^[A-Za-z0-9]+$/', $codigo) || mb_strlen($codigo) > 20) {
    echo json_encode(['success' => false, 'error' => 'Código requerido (solo letras y números, máx. 20).']); exit;
}
if ($dni !== '' && !preg_match('/^\d{8}$/', $dni)) {
    echo json_encode(['success' => false, 'error' => 'DNI inválido (debe tener 8 dígitos).']); exit;
}
$dniVal = $dni === '' ? null : $dni;
if ($nombre === '' || mb_strlen($nombre) < 3) {
    echo json_encode(['success' => false, 'error' => 'Nombre requerido (mínimo 3 caracteres).']); exit;
}
if ($funcion === '')   { echo json_encode(['success' => false, 'error' => 'Función requerida.']); exit; }
if ($cuadrilla === '') { echo json_encode(['success' => false, 'error' => 'Cuadrilla requerida.']); exit; }

// Coordinador a cargo: opcional (0/ausente → NULL). Si viene un id se valida
// que corresponda a un usuario con rol Coordinador; se aceptan inactivos para
// no bloquear la edición de un colaborador cuyo coordinador fue dado de baja.
$coordinadorVal = null;
if ($coordinador > 0) {
    $stmtC = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE id=? AND rol='Coordinador' LIMIT 1");
    mysqli_stmt_bind_param($stmtC, 'i', $coordinador);
    mysqli_stmt_execute($stmtC);
    $rowC = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtC));
    mysqli_stmt_close($stmtC);
    if (!$rowC) {
        echo json_encode(['success' => false, 'error' => 'El coordinador seleccionado no existe o ya no tiene rol Coordinador.']); exit;
    }
    $coordinadorVal = $coordinador;
}

if ($id > 0) {
    // UPDATE
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE colaboradores
            SET codigo=?, dni=?, nombre=?, funcion_principal=?, tipo_funcion=?, cuadrilla=?,
                coordinador_id=?, activo=?
          WHERE id=?"
    );
    mysqli_stmt_bind_param($stmt, 'ssssssiii', $codigo, $dniVal, $nombre, $funcion, $tipo_funcion, $cuadrilla, $coordinadorVal, $activo, $id);

    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        $msg = (strpos((string)$err, 'Duplicate entry') !== false && strpos((string)$err, 'uq_dni') !== false)
            ? 'Ese DNI ya está registrado en otro colaborador.'
            : ((strpos((string)$err, 'Duplicate entry') !== false && strpos((string)$err, 'uq_codigo') !== false)
                ? 'Ese código ya está registrado.'
                : $err);
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }

    sheets_sync_colaboradores($conn);
    echo json_encode(['success' => true, 'id' => (int)$id, 'codigo' => $codigo]);

} else {
    // INSERT con codigo provisto por el usuario.
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO colaboradores (codigo, dni, nombre, funcion_principal, tipo_funcion, cuadrilla, coordinador_id, activo)
              VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'ssssssii', $codigo, $dniVal, $nombre, $funcion, $tipo_funcion, $cuadrilla, $coordinadorVal, $activo);
    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);

    if (!$ok) {
        mysqli_stmt_close($stmt);
        $msg = (strpos((string)$err, 'Duplicate entry') !== false && strpos((string)$err, 'uq_dni') !== false)
            ? 'Ese DNI ya está registrado en otro colaborador.'
            : ((strpos((string)$err, 'Duplicate entry') !== false && strpos((string)$err, 'uq_codigo') !== false)
                ? 'Ese código ya está registrado.'
                : $err);
        echo json_encode(['success' => false, 'error' => $msg]);
        exit;
    }

    $newId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    sheets_sync_colaboradores($conn);
    echo json_encode(['success' => true, 'id' => $newId, 'codigo' => $codigo]);
}
