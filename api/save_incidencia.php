<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Alta / edición de una incidencia (JSON)
   ───────────────────────────────────────────────────────────────────────
   · La competencia se DERIVA del punto en el servidor (no se confía en el
     valor que mande el cliente) usando includes/incidencias_catalogo.php.
   · El coordinador que reporta sale SIEMPRE de la sesión.
   · El nombre y cargo del colaborador se copian (congelan) desde la tabla
     colaboradores al momento de guardar.
   · foto_path / declaracion_path: rutas ya subidas por
     upload_incidencia_file.php. En edición se borra el archivo anterior si
     fue reemplazado o quitado.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/incidencias_catalogo.php');
require_once('../includes/sheets.php');
api_require_report();

/** Espejo de la incidencia (alta o edición) a la hoja 'Incidencias' (upsert por ID). */
function _inc_push_sheets($rowId, $colNombre, $colCargo, $punto, $competencia, $impacto,
                          $coordinador, $turno, $fecha, $zona, $detalle, $fotoPath, $declaracionPath) {
    if (!sheets_enabled()) return;
    $row = [
        (int)$rowId, date('Y-m-d H:i:s'), $colNombre, $colCargo, $punto, $competencia,
        (inc_impactos()[$impacto]['label'] ?? $impacto), $coordinador,
        (inc_turnos()[$turno] ?? $turno), $fecha, $zona, $detalle,
        $fotoPath ?? '', $declaracionPath ?? '',
    ];
    sheets_push('Incidencias', [$row], 'upsert', ['keyCols' => [1], 'header' => [
        'ID', 'Registrado', 'Colaborador', 'Cargo', 'Punto a mejorar', 'Competencia',
        'Impacto', 'Coordinador', 'Turno', 'Fecha', 'Zona de trabajo', 'Detalle', 'Foto', 'Declaración',
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
$punto          = trim($data['punto_mejorar'] ?? '');
$impacto        = trim($data['impacto'] ?? '');
$turno          = trim($data['turno'] ?? '');
$fecha          = trim($data['fecha'] ?? '');
$zona           = trim($data['zona_trabajo'] ?? '');
$detalle        = trim($data['detalle'] ?? '');
$fotoPath       = trim($data['foto_path'] ?? '');
$declaracionPath= trim($data['declaracion_path'] ?? '');

// ─── Validaciones contra el catálogo (fuente de verdad en servidor) ───
$competencia = inc_competencia_de($punto);
if ($competencia === null) {
    echo json_encode(['success' => false, 'error' => 'Punto a mejorar inválido.']); exit;
}
if (!array_key_exists($impacto, inc_impactos())) {
    echo json_encode(['success' => false, 'error' => 'Impacto inválido.']); exit;
}
if (!array_key_exists($turno, inc_turnos())) {
    echo json_encode(['success' => false, 'error' => 'Turno inválido.']); exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    echo json_encode(['success' => false, 'error' => 'Fecha inválida.']); exit;
}
// Se aceptan también las zonas desactivadas: si una ubicación se retira, las
// incidencias antiguas deben poder seguir editándose.
if ($zona === '') {
    echo json_encode(['success' => false, 'error' => 'Selecciona la zona de trabajo.']); exit;
}
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

// Adjuntos: cadena vacía → NULL en BD.
$fotoPath        = $fotoPath !== '' ? $fotoPath : null;
$declaracionPath = $declaracionPath !== '' ? $declaracionPath : null;

// Coordinador desde la sesión.
$coordinador    = $_SESSION['user_name'] ?? 'Desconocido';
$coordinadorId  = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

/** Borra un adjunto del disco si la ruta es válida y vive en uploads/incidencias. */
function _inc_borrar_archivo($rel) {
    if (!$rel) return;
    if (strpos($rel, 'uploads/incidencias/') !== 0) return; // sólo dentro de la carpeta del módulo
    if (strpos($rel, '..') !== false) return;
    $abs = __DIR__ . '/../' . $rel;
    if (is_file($abs)) @unlink($abs);
}

/** Sube un adjunto local a Drive (subcarpeta $subfolder) y devuelve su URL o null. */
function _inc_drive_url($rel, $subfolder) {
    if (!$rel) return null;
    if (strpos($rel, 'uploads/incidencias/') !== 0 || strpos($rel, '..') !== false) return null;
    if (!function_exists('drive_upload')) return null;
    return drive_upload(__DIR__ . '/../' . $rel, $subfolder, basename($rel));
}

// Subcarpeta destino en Drive: "NOMBRE DEL COLABORADOR - FECHA DEL TURNO".
$driveSub = $colNombre . ' - ' . $fecha;

if ($id > 0) {
    // ─── UPDATE ───
    // Recuperar rutas anteriores para limpiar archivos huérfanos.
    $stmt = mysqli_prepare($conn, "SELECT foto_path, declaracion_path, foto_drive_url, declaracion_drive_url, declaracion_uploaded_at FROM incidencias WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res  = mysqli_stmt_get_result($stmt);
    $prev = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    if (!$prev) {
        echo json_encode(['success' => false, 'error' => 'La incidencia no existe.']); exit;
    }

    // Drive: si el archivo no cambió y ya tenía URL, se reusa; si cambió, se sube.
    $fotoDriveUrl = ($fotoPath && $fotoPath === $prev['foto_path'] && !empty($prev['foto_drive_url']))
        ? $prev['foto_drive_url'] : _inc_drive_url($fotoPath, $driveSub);
    $declaracionDriveUrl = ($declaracionPath && $declaracionPath === $prev['declaracion_path'] && !empty($prev['declaracion_drive_url']))
        ? $prev['declaracion_drive_url'] : _inc_drive_url($declaracionPath, $driveSub);

    // Fecha de subida de la declaración:
    //   · sin declaración      → NULL
    //   · declaración ya tenía fecha → se conserva (mide la PRIMERA subida)
    //   · declaración nueva (antes no había) → se sella ahora
    if (!$declaracionPath) {
        $declaracionUploadedAt = null;
    } elseif (!empty($prev['declaracion_uploaded_at'])) {
        $declaracionUploadedAt = $prev['declaracion_uploaded_at'];
    } else {
        $declaracionUploadedAt = date('Y-m-d H:i:s');
    }

    $stmt = mysqli_prepare(
        $conn,
        "UPDATE incidencias SET
            colaborador_id=?, colaborador_nombre=?, colaborador_cargo=?,
            punto_mejorar=?, competencia=?, impacto=?,
            coordinador=?, coordinador_id=?,
            turno=?, fecha=?, zona_trabajo=?, detalle=?,
            foto_path=?, foto_drive_url=?, declaracion_path=?, declaracion_drive_url=?,
            declaracion_uploaded_at=?
          WHERE id=?"
    );
    // Tipos: i s s s s s s i s s s s s s s s s i  (18 parámetros)
    mysqli_stmt_bind_param(
        $stmt, 'issssssisssssssssi',
        $colaboradorId, $colNombre, $colCargo,
        $punto, $competencia, $impacto,
        $coordinador, $coordinadorId,
        $turno, $fecha, $zona, $detalle,
        $fotoPath, $fotoDriveUrl, $declaracionPath, $declaracionDriveUrl,
        $declaracionUploadedAt, $id
    );
    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo actualizar.']); exit;
    }

    // Limpiar archivos reemplazados o eliminados.
    if ($prev['foto_path'] && $prev['foto_path'] !== $fotoPath) _inc_borrar_archivo($prev['foto_path']);
    if ($prev['declaracion_path'] && $prev['declaracion_path'] !== $declaracionPath) _inc_borrar_archivo($prev['declaracion_path']);

    _inc_push_sheets($id, $colNombre, $colCargo, $punto, $competencia, $impacto, $coordinador, $turno, $fecha, $zona, $detalle, $fotoDriveUrl ?: $fotoPath, $declaracionDriveUrl ?: $declaracionPath);
    echo json_encode(['success' => true, 'id' => $id, 'foto_drive_url' => $fotoDriveUrl, 'declaracion_drive_url' => $declaracionDriveUrl]);

} else {
    // ─── INSERT ───
    // Drive: subir las evidencias presentes a la subcarpeta "Colaborador - Fecha".
    $fotoDriveUrl        = _inc_drive_url($fotoPath, $driveSub);
    $declaracionDriveUrl = _inc_drive_url($declaracionPath, $driveSub);

    // Fecha de subida de la declaración: se sella al momento de crear si ya viene una.
    $declaracionUploadedAt = $declaracionPath ? date('Y-m-d H:i:s') : null;

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO incidencias
            (colaborador_id, colaborador_nombre, colaborador_cargo,
             punto_mejorar, competencia, impacto,
             coordinador, coordinador_id,
             turno, fecha, zona_trabajo, detalle,
             foto_path, foto_drive_url, declaracion_path, declaracion_drive_url,
             declaracion_uploaded_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    // Tipos: i s s s s s s i s s s s s s s s s  (17 parámetros)
    mysqli_stmt_bind_param(
        $stmt, 'issssssisssssssss',
        $colaboradorId, $colNombre, $colCargo,
        $punto, $competencia, $impacto,
        $coordinador, $coordinadorId,
        $turno, $fecha, $zona, $detalle,
        $fotoPath, $fotoDriveUrl, $declaracionPath, $declaracionDriveUrl,
        $declaracionUploadedAt
    );
    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    $newId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);

    if (!$ok) {
        echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo guardar.']); exit;
    }

    _inc_push_sheets($newId, $colNombre, $colCargo, $punto, $competencia, $impacto, $coordinador, $turno, $fecha, $zona, $detalle, $fotoDriveUrl ?: $fotoPath, $declaracionDriveUrl ?: $declaracionPath);
    echo json_encode(['success' => true, 'id' => $newId, 'foto_drive_url' => $fotoDriveUrl, 'declaracion_drive_url' => $declaracionDriveUrl]);
}
