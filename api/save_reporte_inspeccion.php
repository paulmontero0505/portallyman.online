<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Alta / edición de un reporte de inspección (JSON)
   ───────────────────────────────────────────────────────────────────────
   · El área involucrada es SIEMPRE "Operaciones" (fija, no se confía en
     lo que mande el cliente).
   · El inspector que reporta sale SIEMPRE de la sesión.
   · El nombre y cargo del tally se copian (congelan) desde la tabla
     colaboradores al momento de guardar.
   · Los criterios (checklist) se validan contra el catálogo del servidor
     y se persisten como JSON.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/reporte_inspeccion_catalogo.php');
api_require_report();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']);
    exit;
}

$id             = isset($data['id']) ? (int)$data['id'] : 0;
$tallyId        = isset($data['tally_id']) ? (int)$data['tally_id'] : 0;
$zona           = trim($data['zona_trabajo'] ?? '');
$fecha          = trim($data['fecha'] ?? '');
$medidas        = trim($data['medidas_tomar'] ?? '');
$recomendaciones= trim($data['recomendaciones'] ?? '');
$criteriosIn    = is_array($data['criterios'] ?? null) ? $data['criterios'] : [];

// ─── Validaciones ───
if ($zona === '') {
    echo json_encode(['success' => false, 'error' => 'Selecciona la zona de trabajo.']); exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    echo json_encode(['success' => false, 'error' => 'Fecha inválida.']); exit;
}

// Los criterios se validan contra el catálogo del servidor: solo se aceptan
// los ítems conocidos, y solo con los estados válidos.
$catalogo = ri_criterios();
$estados  = ri_estados();
$porItem  = [];
foreach ($criteriosIn as $c) {
    $item = trim($c['item'] ?? '');
    if ($item !== '') $porItem[$item] = $c;
}
$criterios = [];
foreach ($catalogo as $item) {
    $c = $porItem[$item] ?? [];
    $estado = trim($c['estado'] ?? '');
    if ($estado !== '' && !array_key_exists($estado, $estados)) {
        echo json_encode(['success' => false, 'error' => 'Estado inválido para "' . $item . '".']); exit;
    }
    $criterios[] = [
        'item'          => $item,
        'estado'        => $estado, // puede quedar vacío si no se evaluó
        'observaciones' => trim($c['observaciones'] ?? ''),
    ];
}
$criteriosJson = json_encode($criterios, JSON_UNESCAPED_UNICODE);

// ─── Tally: copia congelada de nombre + cargo (opcional) ───
$tallyNombre = '';
$tallyCargo  = null;
if ($tallyId > 0) {
    $stmt = mysqli_prepare($conn, "SELECT nombre, funcion_principal FROM colaboradores WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $tallyId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $col = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$col) {
        echo json_encode(['success' => false, 'error' => 'Selecciona un tally válido.']); exit;
    }
    $tallyNombre = $col['nombre'];
    $tallyCargo  = $col['funcion_principal'];
} else {
    echo json_encode(['success' => false, 'error' => 'Selecciona el personal tally involucrado.']); exit;
}

// Área involucrada: fija, no editable desde el cliente.
$areaInvolucrada = ri_area_involucrada();

// Inspector desde la sesión.
$inspector   = $_SESSION['user_name'] ?? 'Desconocido';
$inspectorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if ($id > 0) {
    // ─── UPDATE ───
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE reporte_inspeccion SET
            tally_id=?, tally_nombre=?, tally_cargo=?,
            zona_trabajo=?, area_involucrada=?, fecha=?,
            inspector=?, inspector_id=?,
            criterios=?, medidas_tomar=?, recomendaciones=?
          WHERE id=?"
    );
    mysqli_stmt_bind_param(
        $stmt, 'issssssisssi',
        $tallyId, $tallyNombre, $tallyCargo,
        $zona, $areaInvolucrada, $fecha,
        $inspector, $inspectorId,
        $criteriosJson, $medidas, $recomendaciones, $id
    );
    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo actualizar.']); exit;
    }
    echo json_encode(['success' => true, 'id' => $id]);

} else {
    // ─── INSERT ───
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO reporte_inspeccion
            (tally_id, tally_nombre, tally_cargo,
             zona_trabajo, area_involucrada, fecha,
             inspector, inspector_id,
             criterios, medidas_tomar, recomendaciones)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
        $stmt, 'issssssisss',
        $tallyId, $tallyNombre, $tallyCargo,
        $zona, $areaInvolucrada, $fecha,
        $inspector, $inspectorId,
        $criteriosJson, $medidas, $recomendaciones
    );
    $ok    = mysqli_stmt_execute($stmt);
    $err   = mysqli_stmt_error($stmt);
    $newId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo guardar.']); exit;
    }
    echo json_encode(['success' => true, 'id' => $newId]);
}
