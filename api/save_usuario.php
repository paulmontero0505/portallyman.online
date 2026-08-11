<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido']);
    exit;
}

$id       = isset($data['id']) ? (int)$data['id'] : 0;
$email    = trim($data['email']  ?? '');
$nombre   = trim($data['nombre'] ?? '');
$rol      = $data['rol']         ?? 'Operador';
$estado   = $data['estado']      ?? 'Activo';
$password = $data['password']    ?? '';
$soporteDe = isset($data['soporte_de_id']) && $data['soporte_de_id'] !== ''
           ? (int)$data['soporte_de_id'] : null;

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Correo inválido.']);
    exit;
}
if ($nombre === '') {
    echo json_encode(['success' => false, 'error' => 'El nombre es obligatorio.']);
    exit;
}
if (!in_array($rol, ['Administrador', 'Supervisor', 'Coordinador', 'Soporte', 'Operador'], true)) $rol = 'Coordinador';
if (!in_array($estado, ['Activo', 'Inactivo'], true))                                             $estado = 'Activo';

if ($rol !== 'Soporte') {
    $soporteDe = null;
} else {
    if (!$soporteDe) {
        echo json_encode(['success' => false,
            'error' => 'Un Tally Soporte necesita un Coordinador a cargo.']);
        exit;
    }
    if ($id > 0 && $soporteDe === $id) {
        echo json_encode(['success' => false,
            'error' => 'Un usuario no puede ser soporte de sí mismo.']);
        exit;
    }
    $st = mysqli_prepare($conn, "SELECT rol FROM usuarios WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($st, 'i', $soporteDe);
    mysqli_stmt_execute($st);
    $jefe = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    if (!$jefe || $jefe['rol'] !== 'Coordinador') {
        echo json_encode(['success' => false,
            'error' => 'El coordinador a cargo debe ser un usuario con rol Coordinador.']);
        exit;
    }
}

if ($id > 0) {
    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE usuarios SET email=?, nombre=?, rol=?, estado=?, soporte_de_id=?, password=? WHERE id=?"
        );
        mysqli_stmt_bind_param($stmt, 'ssssisi', $email, $nombre, $rol, $estado, $soporteDe, $hash, $id);
    } else {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE usuarios SET email=?, nombre=?, rol=?, estado=?, soporte_de_id=? WHERE id=?"
        );
        mysqli_stmt_bind_param($stmt, 'ssssii', $email, $nombre, $rol, $estado, $soporteDe, $id);
    }
    $ok = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

} else {
    if ($password === '' || strlen($password) < 6) {
        echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres.']);
        exit;
    }
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO usuarios (email, password, nombre, rol, estado, soporte_de_id) VALUES (?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'sssssi', $email, $hash, $nombre, $rol, $estado, $soporteDe);
    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
}

if ($ok) {
    echo json_encode(['success' => true, 'id' => $id > 0 ? $id : mysqli_insert_id($conn)]);
} else {
    $msg = (strpos((string)$err, 'Duplicate entry') !== false)
        ? 'Ese correo ya está registrado.'
        : $err;
    echo json_encode(['success' => false, 'error' => $msg]);
}
