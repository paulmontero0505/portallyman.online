<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Alta / edición de un reconocimiento (JSON)
   ───────────────────────────────────────────────────────────────────────
   · El concepto se DERIVA de la competencia en el servidor (no se confía en
     el valor del cliente) usando includes/reconocimientos_catalogo.php.
   · El coordinador que reporta sale SIEMPRE de la sesión.
   · El nombre y cargo del colaborador se copian (congelan) desde la tabla
     colaboradores al momento de guardar.
   · foto_path: ruta ya subida por upload_reconocimiento_file.php. En edición
     se borra el archivo anterior si fue reemplazado o quitado.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/reconocimientos_catalogo.php');
require_once('../includes/sheets.php');
api_require_report();

/** Espejo del reconocimiento (alta o edición) a la hoja 'Reconocimientos'. */
function _rec_push_sheets($rowId, $colNombre, $colCargo, $competencia, $concepto,
                          $coordinador, $turno, $fecha, $zona, $detalle, $fotoPath, $estado) {
    if (!sheets_enabled()) return;
    $estadoLabel = rec_estados()[$estado]['label'] ?? $estado;
    $row = [
        (int)$rowId, date('Y-m-d H:i:s'), $colNombre, $colCargo, $competencia, $concepto,
        $coordinador, (inc_turnos()[$turno] ?? $turno), $fecha, $zona, $detalle, $fotoPath ?? '', $estadoLabel,
    ];
    sheets_push('Reconocimientos', [$row], 'upsert', ['keyCols' => [1], 'header' => [
        'ID', 'Registrado', 'Colaborador', 'Cargo', 'Competencia', 'Concepto',
        'Coordinador', 'Turno', 'Fecha', 'Zona de trabajo', 'Comentarios', 'Foto', 'Estado',
    ]]);
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']);
    exit;
}

$id             = isset($data['id']) ? (int)$data['id'] : 0;
$colaboradorId  = isset($data['colaborador_id']) ? (int)$data['colaborador_id'] : 0;
$competencia    = trim($data['competencia'] ?? '');
$turno          = trim($data['turno'] ?? '');
$fecha          = trim($data['fecha'] ?? '');
$zona           = trim($data['zona_trabajo'] ?? '');
$detalle        = trim($data['detalle'] ?? '');
$fotoPath       = trim($data['foto_path'] ?? '');

// ─── Validaciones contra el catálogo (fuente de verdad en servidor) ───
$concepto = rec_concepto_de($competencia);
if ($concepto === null) {
    echo json_encode(['success' => false, 'error' => 'Competencia inválida.']); exit;
}
if (!array_key_exists($turno, inc_turnos())) {
    echo json_encode(['success' => false, 'error' => 'Turno inválido.']); exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    echo json_encode(['success' => false, 'error' => 'Fecha inválida.']); exit;
}
if ($zona === '') {
    echo json_encode(['success' => false, 'error' => 'Selecciona la zona de trabajo.']); exit;
}
// Se aceptan también las zonas desactivadas para poder editar registros antiguos.
if (!in_array($zona, inc_zonas(false), true)) {
    echo json_encode(['success' => false, 'error' => 'Zona de trabajo inválida: "' . $zona . '".']); exit;
}

// ─── Colaborador: copia congelada de nombre + cargo ───
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

// Adjunto: cadena vacía → NULL en BD.
$fotoPath = $fotoPath !== '' ? $fotoPath : null;

// Coordinador desde la sesión.
$coordinador   = $_SESSION['user_name'] ?? 'Desconocido';
$coordinadorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

/** Borra el adjunto del disco si la ruta es válida y vive en uploads/reconocimientos. */
function _rec_borrar_archivo($rel) {
    if (!$rel) return;
    if (strpos($rel, 'uploads/reconocimientos/') !== 0) return;
    if (strpos($rel, '..') !== false) return;
    $abs = __DIR__ . '/../' . $rel;
    if (is_file($abs)) @unlink($abs);
}

/** Sube el adjunto local a Drive (subcarpeta $subfolder) y devuelve su URL o null. */
function _rec_drive_url($rel, $subfolder) {
    if (!$rel) return null;
    if (strpos($rel, 'uploads/reconocimientos/') !== 0 || strpos($rel, '..') !== false) return null;
    if (!function_exists('drive_upload')) return null;
    return drive_upload(__DIR__ . '/../' . $rel, $subfolder, basename($rel));
}

// Subcarpeta destino en Drive: "NOMBRE DEL COLABORADOR - FECHA DEL TURNO".
$driveSub = $colNombre . ' - ' . $fecha;

if ($id > 0) {
    // ─── UPDATE ───
    $stmt = mysqli_prepare($conn, "SELECT foto_path, foto_drive_url, estado FROM reconocimientos WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res  = mysqli_stmt_get_result($stmt);
    $prev = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$prev) {
        echo json_encode(['success' => false, 'error' => 'El reconocimiento no existe.']); exit;
    }

    // Drive: si el archivo no cambió y ya tenía URL, se reusa; si cambió, se sube.
    $fotoDriveUrl = ($fotoPath && $fotoPath === $prev['foto_path'] && !empty($prev['foto_drive_url']))
        ? $prev['foto_drive_url'] : _rec_drive_url($fotoPath, $driveSub);

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE reconocimientos SET
            colaborador_id=?, colaborador_nombre=?, colaborador_cargo=?,
            competencia=?, concepto=?,
            coordinador=?, coordinador_id=?,
            turno=?, fecha=?, zona_trabajo=?, detalle=?,
            foto_path=?, foto_drive_url=?
          WHERE id=?"
    );
    // Tipos: i s s s s s i s s s s s s i  (14 parámetros)
    mysqli_stmt_bind_param(
        $stmt, 'isssssissssssi',
        $colaboradorId, $colNombre, $colCargo,
        $competencia, $concepto,
        $coordinador, $coordinadorId,
        $turno, $fecha, $zona, $detalle,
        $fotoPath, $fotoDriveUrl, $id
    );
    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo actualizar.']); exit;
    }

    // Limpiar archivo reemplazado o eliminado.
    if ($prev['foto_path'] && $prev['foto_path'] !== $fotoPath) _rec_borrar_archivo($prev['foto_path']);

    _rec_push_sheets($id, $colNombre, $colCargo, $competencia, $concepto, $coordinador, $turno, $fecha, $zona, $detalle, $fotoDriveUrl ?: $fotoPath, $prev['estado']);
    echo json_encode(['success' => true, 'id' => $id, 'foto_drive_url' => $fotoDriveUrl]);

} else {
    // ─── INSERT ───
    $fotoDriveUrl = _rec_drive_url($fotoPath, $driveSub);

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO reconocimientos
            (colaborador_id, colaborador_nombre, colaborador_cargo,
             competencia, concepto,
             coordinador, coordinador_id,
             turno, fecha, zona_trabajo, detalle,
             foto_path, foto_drive_url)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    // Tipos: i s s s s s i s s s s s s  (13 parámetros)
    mysqli_stmt_bind_param(
        $stmt, 'isssssissssss',
        $colaboradorId, $colNombre, $colCargo,
        $competencia, $concepto,
        $coordinador, $coordinadorId,
        $turno, $fecha, $zona, $detalle,
        $fotoPath, $fotoDriveUrl
    );
    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    $newId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo guardar.']); exit;
    }

    _rec_push_sheets($newId, $colNombre, $colCargo, $competencia, $concepto, $coordinador, $turno, $fecha, $zona, $detalle, $fotoDriveUrl ?: $fotoPath, 'pendiente');
    echo json_encode(['success' => true, 'id' => $newId, 'foto_drive_url' => $fotoDriveUrl]);
}
