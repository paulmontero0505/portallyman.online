<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Guarda las marcas de asistencia de una capacitación
   ───────────────────────────────────────────────────────────────────────
   Payload JSON:
   { id: int, marcas: [ { colaborador_id, estado:'asistio'|'tardanza'|'falta' }, … ] }

   Reemplazo completo: se borran las filas previas y se insertan las que
   vengan marcadas. Quien NO viene en `marcas` queda SIN FILA, que es como
   este módulo representa «sin marcar» (ver sql/028_capacitaciones.sql).

   Guardar un 'sin_marcar' explícito habría sido equivalente, pero abre la
   puerta a que un día el valor por defecto acabe siendo 'asistio' y el
   módulo empiece a reportar asistencias que nadie miró.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/capacitaciones_catalogo.php');
api_require_report();

header('Content-Type: application/json');

$data   = json_decode(file_get_contents('php://input'), true);
$id     = isset($data['id']) ? (int)$data['id'] : 0;
$marcas = $data['marcas'] ?? [];

if ($id <= 0)          { echo json_encode(['success'=>false,'error'=>'ID inválido.']); exit; }
if (!is_array($marcas)) { echo json_encode(['success'=>false,'error'=>'Payload inválido.']); exit; }

// ── Autorización ────────────────────────────────────────────────────────
$stmt = mysqli_prepare($conn, "SELECT id, estado, coordinador_id FROM capacitaciones WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$cap = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$permiso = cap_puede_editar($cap);
if (!$permiso['ok']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $permiso['error']]); exit;
}

// ── Normaliza el payload y resuelve las copias congeladas ───────────────
$validos = array_keys(cap_asistencia());
$pedidas = [];
foreach ($marcas as $m) {
    $cid = (int)($m['colaborador_id'] ?? 0);
    $est = trim($m['estado'] ?? '');
    if ($cid <= 0) continue;
    if (!in_array($est, $validos, true)) continue;   // ignora estados inventados
    $pedidas[$cid] = $est;                            // el último gana
}

$catalogo = [];
if ($pedidas) {
    $in = implode(',', array_map('intval', array_keys($pedidas)));
    $rc = mysqli_query($conn,
        "SELECT id, nombre, dni, funcion_principal, cuadrilla FROM colaboradores WHERE id IN ($in)");
    while ($row = mysqli_fetch_assoc($rc)) $catalogo[(int)$row['id']] = $row;
}

// ── Transacción: borrado + inserción ────────────────────────────────────
mysqli_begin_transaction($conn);
try {
    $del = mysqli_prepare($conn, "DELETE FROM capacitaciones_asistentes WHERE capacitacion_id=?");
    mysqli_stmt_bind_param($del, 'i', $id);
    mysqli_stmt_execute($del);
    if (mysqli_stmt_errno($del)) throw new Exception(mysqli_stmt_error($del));
    mysqli_stmt_close($del);

    $ins = mysqli_prepare($conn,
        "INSERT INTO capacitaciones_asistentes
            (capacitacion_id, colaborador_id, colaborador_nombre, colaborador_dni,
             colaborador_cargo, colaborador_cuadrilla, estado)
         VALUES (?, ?, ?, ?, ?, ?, ?)");

    $guardadas = 0;
    foreach ($pedidas as $cid => $est) {
        $col = $catalogo[$cid] ?? null;
        if (!$col) continue;                          // ignora ids inexistentes
        $nombre    = $col['nombre'];
        $dni       = $col['dni'];
        $cargo     = $col['funcion_principal'];
        $cuadrilla = $col['cuadrilla'];
        mysqli_stmt_bind_param($ins, 'iisssss',
            $id, $cid, $nombre, $dni, $cargo, $cuadrilla, $est);
        mysqli_stmt_execute($ins);
        if (mysqli_stmt_errno($ins)) throw new Exception(mysqli_stmt_error($ins));
        $guardadas++;
    }
    mysqli_stmt_close($ins);

    mysqli_commit($conn);
} catch (Exception $ex) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'No se pudo guardar la asistencia: ' . $ex->getMessage()]); exit;
}

// Denominador vivo, para que la UI refresque el contador sin otra llamada.
$plantilla = 0;
$rp = mysqli_query($conn, "SELECT COUNT(*) AS n FROM colaboradores WHERE activo = 1");
if ($rp && ($r = mysqli_fetch_assoc($rp))) $plantilla = (int)$r['n'];

echo json_encode([
    'success'   => true,
    'id'        => $id,
    'marcados'  => $guardadas,
    'plantilla' => $plantilla,
]);
