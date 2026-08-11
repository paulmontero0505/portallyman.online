<?php
/* ═══════════════════════════════════════════════════════════════════════
   Catálogo de Coordinadores Tallyman
   ───────────────────────────────────────────────────────────────────────
   Los coordinadores NO son un catálogo aparte: son los usuarios del sistema
   con rol='Coordinador'. Este endpoint los expone como lista simple para
   poblar selectores (Colaboradores y, a futuro, otros módulos que necesiten
   el coordinador a cargo).

   Por defecto devuelve sólo los activos. Con ?incluir_inactivos=1 devuelve
   también los inactivos (marcados con activo=0), útil para no perder una
   asignación histórica al editar un colaborador.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_login();

header('Content-Type: application/json');

$incluirInactivos = !empty($_GET['incluir_inactivos']);

$sql = "SELECT id, nombre, estado
          FROM usuarios
         WHERE rol = 'Coordinador'"
     . ($incluirInactivos ? '' : " AND estado = 'Activo'")
     . " ORDER BY nombre ASC";

$r = mysqli_query($conn, $sql);
if (!$r) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

$out = [];
while ($row = mysqli_fetch_assoc($r)) {
    $out[] = [
        'id'     => (int)$row['id'],
        'nombre' => $row['nombre'],
        'activo' => ($row['estado'] === 'Activo') ? 1 : 0,
    ];
}

echo json_encode(['success' => true, 'data' => $out]);
