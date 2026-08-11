<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Alta / edición de una evaluación de desempeño (JSON)
   ───────────────────────────────────────────────────────────────────────
   · El evaluador que reporta sale SIEMPRE de la sesión.
   · El nombre y cargo del colaborador evaluado se copian (congelan) desde
     la tabla colaboradores al momento de guardar.
   · Los criterios (categoría + item + puntaje 1-5 + observaciones) se
     validan contra el catálogo del servidor y se persisten como JSON.
   · El puntaje total se calcula en el servidor (no se confía en el cliente).
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/evaluacion_desempeno_catalogo.php');
api_require_report();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']);
    exit;
}

$id              = isset($data['id']) ? (int)$data['id'] : 0;
$colaboradorId   = isset($data['colaborador_id']) ? (int)$data['colaborador_id'] : 0;
$actividad       = trim($data['actividad'] ?? '');
$turno           = trim($data['turno'] ?? '');
$fecha           = trim($data['fecha'] ?? '');
$criteriosIn     = is_array($data['criterios'] ?? null) ? $data['criterios'] : [];
$comentariosCatIn= is_array($data['comentarios_categoria'] ?? null) ? $data['comentarios_categoria'] : [];
$comentariosGen  = trim($data['comentarios_generales'] ?? '');

// ─── Validaciones ───
if (!array_key_exists($turno, ed_turnos())) {
    echo json_encode(['success' => false, 'error' => 'Turno inválido.']); exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    echo json_encode(['success' => false, 'error' => 'Fecha inválida.']); exit;
}

// Los criterios se validan contra el catálogo del servidor: solo se aceptan
// las categorías/ítems conocidos, con puntaje entre 1 y 5 (o sin evaluar).
$catalogo = ed_criterios();
$porItem  = [];
foreach ($criteriosIn as $c) {
    $key = trim($c['categoria'] ?? '') . '|' . trim($c['item'] ?? '');
    $porItem[$key] = $c;
}
$criterios     = [];
$puntajeTotal  = 0;
foreach ($catalogo as $catKey => $cat) {
    foreach ($cat['items'] as $item) {
        $c = $porItem[$catKey . '|' . $item] ?? [];
        $puntaje = isset($c['puntaje']) && $c['puntaje'] !== '' ? (int)$c['puntaje'] : null;
        if ($puntaje !== null && ($puntaje < 1 || $puntaje > 5)) {
            echo json_encode(['success' => false, 'error' => 'Puntaje inválido para "' . $item . '".']); exit;
        }
        if ($puntaje !== null) $puntajeTotal += $puntaje;
        $criterios[] = [
            'categoria'     => $catKey,
            'item'          => $item,
            'puntaje'       => $puntaje,
            'observaciones' => trim($c['observaciones'] ?? ''),
        ];
    }
}
$criteriosJson = json_encode($criterios, JSON_UNESCAPED_UNICODE);

$comentariosCat = [];
foreach (array_keys($catalogo) as $catKey) {
    $comentariosCat[$catKey] = trim($comentariosCatIn[$catKey] ?? '');
}
$comentariosCatJson = json_encode($comentariosCat, JSON_UNESCAPED_UNICODE);

// ─── Colaborador evaluado: copia congelada de nombre + cargo ───
if ($colaboradorId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Selecciona el colaborador evaluado.']); exit;
}
$stmt = mysqli_prepare($conn, "SELECT nombre, funcion_principal FROM colaboradores WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $colaboradorId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$col = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);
if (!$col) {
    echo json_encode(['success' => false, 'error' => 'Selecciona un colaborador válido.']); exit;
}
$colNombre = $col['nombre'];
$colCargo  = $col['funcion_principal'];

// Evaluador desde la sesión.
$evaluador   = $_SESSION['user_name'] ?? 'Desconocido';
$evaluadorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if ($id > 0) {
    // ─── UPDATE ───
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE evaluacion_desempeno SET
            colaborador_id=?, colaborador_nombre=?, colaborador_cargo=?,
            actividad=?, turno=?, fecha=?,
            evaluador=?, evaluador_id=?,
            criterios=?, comentarios_categoria=?, puntaje_total=?, comentarios_generales=?
          WHERE id=?"
    );
    mysqli_stmt_bind_param(
        $stmt, 'issssssissisi',
        $colaboradorId, $colNombre, $colCargo,
        $actividad, $turno, $fecha,
        $evaluador, $evaluadorId,
        $criteriosJson, $comentariosCatJson, $puntajeTotal, $comentariosGen, $id
    );
    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo actualizar.']); exit;
    }
    echo json_encode(['success' => true, 'id' => $id, 'puntaje_total' => $puntajeTotal]);

} else {
    // ─── INSERT ───
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO evaluacion_desempeno
            (colaborador_id, colaborador_nombre, colaborador_cargo,
             actividad, turno, fecha,
             evaluador, evaluador_id,
             criterios, comentarios_categoria, puntaje_total, comentarios_generales)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
        $stmt, 'issssssissis',
        $colaboradorId, $colNombre, $colCargo,
        $actividad, $turno, $fecha,
        $evaluador, $evaluadorId,
        $criteriosJson, $comentariosCatJson, $puntajeTotal, $comentariosGen
    );
    $ok    = mysqli_stmt_execute($stmt);
    $err   = mysqli_stmt_error($stmt);
    $newId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo guardar.']); exit;
    }
    echo json_encode(['success' => true, 'id' => $newId, 'puntaje_total' => $puntajeTotal]);
}
