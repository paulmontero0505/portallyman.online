<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Plantilla + marcas de asistencia de una capacitación
   ───────────────────────────────────────────────────────────────────────
   GET ?id=N   (id = 0 o ausente → solo la plantilla, sin marcas)

   Devuelve TODOS los colaboradores activos —no una selección— porque en
   este módulo la asistencia se marca, no se elige a quién invitar.
   Cada fila trae su marca actual ('asistio'|'tardanza'|'falta') o null si
   está sin marcar. `null` es un estado real y visible en la UI, no un
   hueco: es lo que impide dar por asistido a quien nadie miró.

   Las capacitaciones ya validadas devuelven las filas CONGELADAS que se
   guardaron en su día, no la plantilla de hoy: quien ya no está activo
   debe seguir apareciendo en el registro histórico.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/capacitaciones_catalogo.php');
api_require_report();

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ── Marcas ya guardadas ─────────────────────────────────────────────────
$marcas     = [];  // colaborador_id => estado
$congeladas = [];  // filas guardadas, en lista: colaborador_id puede ser NULL
                   // (ON DELETE SET NULL) y varias filas compartirían la clave 0
$estado     = 'programada';

if ($id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT estado FROM capacitaciones WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $cap = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$cap) {
        echo json_encode(['success' => false, 'error' => 'La capacitación no existe.']); exit;
    }
    $estado = $cap['estado'];

    $stmt = mysqli_prepare($conn,
        "SELECT colaborador_id, colaborador_nombre, colaborador_dni,
                colaborador_cargo, colaborador_cuadrilla, estado
           FROM capacitaciones_asistentes
          WHERE capacitacion_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $r = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($r)) {
        $cid = $row['colaborador_id'] === null ? 0 : (int)$row['colaborador_id'];
        if ($cid > 0) $marcas[$cid] = $row['estado'];
        $row['_cid']  = $cid;
        $congeladas[] = $row;
    }
    mysqli_stmt_close($stmt);
}

// ── Plantilla activa ────────────────────────────────────────────────────
$out  = [];
$vistos = [];
$rc = mysqli_query($conn,
    "SELECT c.id, c.codigo, c.nombre, c.dni, c.funcion_principal, c.cuadrilla,
            c.coordinador_id, u.nombre AS coordinador_nombre
       FROM colaboradores c
       LEFT JOIN usuarios u ON u.id = c.coordinador_id
      WHERE c.activo = 1
   ORDER BY c.cuadrilla ASC, c.nombre ASC");
if (!$rc) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]); exit;
}
while ($row = mysqli_fetch_assoc($rc)) {
    $cid = (int)$row['id'];
    $vistos[$cid] = true;
    $out[] = [
        'id'                 => $cid,
        'codigo'             => $row['codigo'],
        'nombre'             => $row['nombre'],
        'dni'                => $row['dni'],
        'cargo'              => $row['funcion_principal'],
        'cuadrilla'          => $row['cuadrilla'],
        'coordinador_id'     => $row['coordinador_id'] === null ? null : (int)$row['coordinador_id'],
        'coordinador_nombre' => $row['coordinador_nombre'],
        'marca'              => $marcas[$cid] ?? null,
        'historico'          => false,
    ];
}

// ── Marcados que ya no están activos ────────────────────────────────────
// Se añaden al final para que un registro pasado no pierda gente al dar de
// baja a un colaborador. Van con historico=true para que la UI los muestre
// atenuados y no los cuente como plantilla pendiente.
foreach ($congeladas as $row) {
    $cid = (int)$row['_cid'];
    if ($cid > 0 && isset($vistos[$cid])) continue;
    $out[] = [
        'id'                 => $cid,
        'codigo'             => null,
        'nombre'             => $row['colaborador_nombre'],
        'dni'                => $row['colaborador_dni'],
        'cargo'              => $row['colaborador_cargo'],
        'cuadrilla'          => $row['colaborador_cuadrilla'],
        'coordinador_id'     => null,
        'coordinador_nombre' => null,
        'marca'              => $row['estado'],
        'historico'          => true,
    ];
}

echo json_encode([
    'success'   => true,
    'estado'    => $estado,
    'plantilla' => count($vistos),   // denominador: solo los activos
    'data'      => $out,
]);
