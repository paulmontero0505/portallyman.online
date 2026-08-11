<?php
require_once('../includes/db.php');
session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'No se recibieron datos']);
    exit;
}

$email = trim($data['email'] ?? '');
$pass  = $data['password'] ?? '';

if ($email === '' || $pass === '') {
    echo json_encode(['success' => false, 'error' => 'Email y contraseña requeridos']);
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, email, password, nombre, rol, estado FROM usuarios WHERE email = ? LIMIT 1"
);
mysqli_stmt_bind_param($stmt, 's', $email);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
    exit;
}

if ($row['estado'] === 'Inactivo') {
    echo json_encode(['success' => false, 'error' => 'Tu cuenta está inactiva. Contacta al administrador.']);
    exit;
}

if (!password_verify($pass, $row['password'])) {
    echo json_encode(['success' => false, 'error' => 'Contraseña incorrecta']);
    exit;
}

// Re-hash si el algoritmo cambió (futureproof)
if (password_needs_rehash($row['password'], PASSWORD_BCRYPT)) {
    $newHash = password_hash($pass, PASSWORD_BCRYPT);
    $upd = mysqli_prepare($conn, "UPDATE usuarios SET password=? WHERE id=?");
    mysqli_stmt_bind_param($upd, 'si', $newHash, $row['id']);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);
}

// Actualizar último acceso
$upd = mysqli_prepare($conn, "UPDATE usuarios SET ultimo_acceso=NOW() WHERE id=?");
mysqli_stmt_bind_param($upd, 'i', $row['id']);
mysqli_stmt_execute($upd);
mysqli_stmt_close($upd);

$_SESSION['user_id']   = (int)$row['id'];
$_SESSION['user_email']= $row['email'];
$_SESSION['user_name'] = $row['nombre'];
$_SESSION['user_rol']  = $row['rol'];

echo json_encode([
    'success' => true,
    'user' => [
        'email'  => $row['email'],
        'nombre' => $row['nombre'],
        'rol'    => $row['rol'],
    ]
]);
