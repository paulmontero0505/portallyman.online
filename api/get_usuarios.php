<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();

header('Content-Type: application/json');

$r = mysqli_query(
    $conn,
    "SELECT u.id, u.email, u.nombre, u.rol, u.estado, u.ultimo_acceso, u.created_at,
            u.soporte_de_id, c.nombre AS soporte_de_nombre
       FROM usuarios u
       LEFT JOIN usuarios c ON c.id = u.soporte_de_id
      ORDER BY u.nombre ASC"
);

$out = [];
while ($u = mysqli_fetch_assoc($r)) {
    $u['soporte_de_id'] = $u['soporte_de_id'] !== null ? (int)$u['soporte_de_id'] : null;
    $out[] = $u;
}

$coords = [];
$rc = mysqli_query($conn,
    "SELECT DISTINCT u.id, u.nombre, u.estado
       FROM usuarios u
      WHERE (u.rol='Coordinador' AND u.estado='Activo')
         OR u.id IN (SELECT soporte_de_id FROM usuarios WHERE soporte_de_id IS NOT NULL)
      ORDER BY u.nombre ASC");
if ($rc) while ($c = mysqli_fetch_assoc($rc)) {
    $coords[] = [
        'id'     => (int)$c['id'],
        'nombre' => $c['nombre'],
        'activo' => $c['estado'] === 'Activo' ? 1 : 0,
    ];
}

echo json_encode([
    'success'       => true,
    'data'          => $out,
    'roles'         => ['Administrador', 'Supervisor', 'Coordinador', 'Soporte', 'Operador'],
    'coordinadores' => $coords,
]);
