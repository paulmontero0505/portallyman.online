<?php
/* Actualización pública: modifica directamente la ficha del colaborador.
   No crea una sugerencia ni una solicitud administrativa. */
require_once(__DIR__ . '/../../includes/db.php');
require_once(__DIR__ . '/../../includes/catalogos_colaboradores.php');

header('Content-Type: application/json; charset=utf-8');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Solicitud inválida.']); exit;
}

$dni     = trim((string)($data['dni'] ?? ''));
$codigo  = strtoupper(trim((string)($data['codigo'] ?? '')));
$celular = preg_replace('/\D/', '', (string)($data['celular'] ?? ''));
$correo  = strtolower(trim((string)($data['correo_electronico'] ?? '')));
$fechaNacimiento = trim((string)($data['fecha_nacimiento'] ?? ''));
$team = trim((string)($data['cuadrilla'] ?? ''));

if (!preg_match('/^\d{8}$/', $dni)) {
    echo json_encode(['success' => false, 'error' => 'Ingresa un DNI válido.']); exit;
}
if ($codigo === '' || !preg_match('/^[A-Z0-9_-]{1,20}$/', $codigo)) {
    echo json_encode(['success' => false, 'error' => 'El código solo puede contener letras, números, guiones o guion bajo.']); exit;
}
if ($celular !== '' && !preg_match('/^9\d{8}$/', $celular)) {
    echo json_encode(['success' => false, 'error' => 'El celular debe tener 9 dígitos y comenzar con 9.']); exit;
}
if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Ingresa un correo electrónico válido.']); exit;
}
if ($fechaNacimiento !== '') {
    $fecha = DateTime::createFromFormat('Y-m-d', $fechaNacimiento);
    if (!$fecha || $fecha->format('Y-m-d') !== $fechaNacimiento || $fecha > new DateTime('today')) {
        echo json_encode(['success' => false, 'error' => 'Ingresa una fecha de cumpleaños válida.']); exit;
    }
}
if ($team === '') {
    echo json_encode(['success' => false, 'error' => 'Selecciona el Team que te corresponde.']); exit;
}

$stmt = mysqli_prepare($conn, 'SELECT id, codigo, nombre, celular, correo_electronico, fecha_nacimiento, fecha_ingreso, funcion_principal, cuadrilla FROM colaboradores WHERE dni=? AND activo=1 LIMIT 1');
mysqli_stmt_bind_param($stmt, 's', $dni);
mysqli_stmt_execute($stmt);
$colaborador = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$colaborador) {
    echo json_encode(['success' => false, 'error' => 'No se encontró un colaborador activo con ese DNI.']); exit;
}

if (!colaborador_team_es_valido($conn, $team)) {
    echo json_encode(['success' => false, 'error' => 'El Team seleccionado ya no está disponible.']); exit;
}

if ($codigo !== (string)$colaborador['codigo']) {
    $stmt = mysqli_prepare($conn, 'SELECT id FROM colaboradores WHERE codigo=? AND id<>? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'si', $codigo, $colaborador['id']);
    mysqli_stmt_execute($stmt);
    $ocupado = (bool)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if ($ocupado) {
        echo json_encode(['success' => false, 'error' => 'Ese código ya pertenece a otro colaborador.']); exit;
    }
}

$celularDb = $celular === '' ? null : '+51 ' . $celular;
$correoDb = $correo === '' ? null : $correo;
$fechaNacimientoDb = $fechaNacimiento === '' ? null : $fechaNacimiento;
if ($codigo === (string)$colaborador['codigo']
    && $celularDb === ($colaborador['celular'] ?: null)
    && $correoDb === ($colaborador['correo_electronico'] ?: null)
    && $fechaNacimientoDb === ($colaborador['fecha_nacimiento'] ?: null)
    && $team === ($colaborador['cuadrilla'] ?: '')) {
    echo json_encode(['success' => false, 'error' => 'No detectamos cambios en tus datos.']); exit;
}

$stmt = mysqli_prepare($conn, 'UPDATE colaboradores SET codigo=?, celular=?, correo_electronico=?, fecha_nacimiento=?, cuadrilla=? WHERE id=?');
mysqli_stmt_bind_param($stmt, 'sssssi', $codigo, $celularDb, $correoDb, $fechaNacimientoDb, $team, $colaborador['id']);
$okUpdate = mysqli_stmt_execute($stmt);
$errorUpdate = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);
if (!$okUpdate) {
    echo json_encode(['success' => false, 'error' => $errorUpdate ?: 'No se pudieron actualizar los datos.']); exit;
}

echo json_encode([
    'success' => true,
    'colaborador' => [
        'nombre' => $colaborador['nombre'],
        'codigo' => $codigo,
        'celular' => $celularDb,
        'correo_electronico' => $correoDb,
        'fecha_nacimiento' => $fechaNacimientoDb,
        'fecha_ingreso' => $colaborador['fecha_ingreso'],
        'team' => $team,
    ],
]);
