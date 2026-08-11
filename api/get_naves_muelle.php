<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Naves asignables a una posición de muelle (JSON)
   ───────────────────────────────────────────────────────────────────────
   Alimenta el selector de nave en la asignación de turno (index.php).

   Devuelve las naves que NO están finalizadas, es decir las que pueden
   recibir personal: Programada, En Puerto o En Operaciones. Se ordena
   poniendo delante las que ya están operando, que son las que se van a
   elegir el 99% de las veces.

   `muelle` se devuelve tal cual para que el front pueda sugerir la nave
   cuya posición coincide con la ubicación elegida, sin imponerla: la
   sugerencia ayuda, pero quien decide es la persona que asigna.

   GET → { success:true, data:[{id, nombre, muelle, estado, tipo}] }
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_login();

header('Content-Type: application/json; charset=utf-8');

$oper = conn_operaciones();
if (!$oper) {
    // No es un error fatal: sin naves el selector queda vacío y la asignación
    // sigue funcionando sin nave. Peor sería tumbar la pantalla de turno.
    echo json_encode(['success' => true, 'data' => [], 'aviso' => 'Base de Operaciones no disponible.']);
    exit;
}

$r = mysqli_query(
    $oper,
    "SELECT n.id, n.nombre, n.muelle, n.estado, t.nombre AS tipo
       FROM naves n
       LEFT JOIN tipos_nave t ON t.id = n.tipo_nave_id
      WHERE n.estado <> 'Finalizada'
      ORDER BY FIELD(n.estado, 'En Operaciones', 'En Puerto', 'Programada'), n.nombre ASC"
);

$out = [];
while ($r && ($row = mysqli_fetch_assoc($r))) {
    $out[] = [
        'id'     => (int)$row['id'],
        'nombre' => $row['nombre'],
        'muelle' => $row['muelle'],
        'estado' => $row['estado'],
        'tipo'   => $row['tipo'],
    ];
}
mysqli_close($oper);

echo json_encode(['success' => true, 'data' => $out]);
