<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
api_require_tareas();

header('Content-Type: application/json');

$sql = "SELECT u.id, u.nombre, u.rol, u.soporte_de_id, c.nombre AS coordinador_nombre
          FROM usuarios u
          LEFT JOIN usuarios c ON c.id = u.soporte_de_id
         WHERE u.rol IN ('Coordinador','Soporte')
           AND u.estado = 'Activo'
         ORDER BY FIELD(u.rol,'Coordinador','Soporte'), u.nombre ASC";

$r = mysqli_query($conn, $sql);
if (!$r) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

$out = [];
while ($u = mysqli_fetch_assoc($r)) {
    $out[] = [
        'id'                 => (int)$u['id'],
        'nombre'             => $u['nombre'],
        'rol'                => $u['rol'],
        'rol_label'          => tk_rol_label($u['rol']),
        'soporte_de_id'      => $u['soporte_de_id'] !== null ? (int)$u['soporte_de_id'] : null,
        'coordinador_nombre' => $u['coordinador_nombre'],
    ];
}

echo json_encode(['success' => true, 'data' => $out]);
