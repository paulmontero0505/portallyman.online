<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/sheets.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || !is_array($data['rows'] ?? null)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido']);
    exit;
}

$rows = $data['rows'];
if (count($rows) === 0) {
    echo json_encode(['success' => false, 'error' => 'No hay filas para importar.']);
    exit;
}
if (count($rows) > 1000) {
    echo json_encode(['success' => false, 'error' => 'Máximo 1000 filas por importación.']);
    exit;
}

mysqli_begin_transaction($conn);

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO colaboradores (codigo,dni,celular,nombre,fecha_nacimiento,fecha_ingreso,funcion_principal,tipo_funcion,cuadrilla,coordinador_id,activo)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE
        dni = VALUES(dni),
        celular = VALUES(celular),
        nombre = VALUES(nombre),
        fecha_nacimiento = VALUES(fecha_nacimiento),
        fecha_ingreso = VALUES(fecha_ingreso),
        funcion_principal = VALUES(funcion_principal),
        tipo_funcion = VALUES(tipo_funcion),
        cuadrilla = VALUES(cuadrilla),
        coordinador_id = VALUES(coordinador_id),
        updated_at = CURRENT_TIMESTAMP"
);

$inserted = 0;
$updated  = 0;

try {
    foreach ($rows as $i => $r) {
        $codigo    = trim($r['codigo']    ?? '');
        $nombre    = trim($r['nombre']    ?? '');
        $funcion   = trim($r['funcion']   ?? '');
        $cuadrilla = trim($r['cuadrilla'] ?? '');
        $dni = trim($r['dni'] ?? '');
        $celular = trim($r['celular'] ?? '');
        $tipoFuncion = trim($r['tipo_funcion'] ?? '');
        $coordinadorNombre = trim($r['coordinador'] ?? '');
        $fechaNacimiento = trim($r['fecha_nacimiento'] ?? '');
        $fechaIngreso = trim($r['fecha_ingreso'] ?? '');

        // Re-validación defensiva (cliente ya validó)
        if ($codigo === '' || !preg_match('/^[A-Za-z0-9]+$/', $codigo) || mb_strlen($codigo) > 20) {
            throw new RuntimeException("Fila " . ($i+1) . ": código inválido (solo letras y números, máx. 20).");
        }
        if (mb_strlen($nombre) < 3)  throw new RuntimeException("Fila " . ($i+1) . ": nombre inválido.");
        if ($funcion === '')         throw new RuntimeException("Fila " . ($i+1) . ": función vacía.");
        if ($cuadrilla === '')       throw new RuntimeException("Fila " . ($i+1) . ": cuadrilla vacía.");
        if ($dni !== '' && !preg_match('/^\d{8}$/', $dni)) throw new RuntimeException("Fila " . ($i+1) . ": DNI inválido.");
        if ($celular !== '' && !preg_match('/^\+51 9\d{8}$/', $celular)) throw new RuntimeException("Fila " . ($i+1) . ": celular inválido.");
        foreach (['fecha_nacimiento' => $fechaNacimiento, 'fecha_ingreso' => $fechaIngreso] as $campo => $fecha) {
            if ($fecha !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) throw new RuntimeException("Fila " . ($i+1) . ": $campo debe usar YYYY-MM-DD.");
        }
        $coordinadorId = null;
        if ($coordinadorNombre !== '') {
            $stmtCoord = mysqli_prepare($conn, "SELECT id FROM usuarios WHERE rol='Coordinador' AND nombre=? LIMIT 1");
            mysqli_stmt_bind_param($stmtCoord, 's', $coordinadorNombre);
            mysqli_stmt_execute($stmtCoord);
            $coord = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtCoord));
            mysqli_stmt_close($stmtCoord);
            if (!$coord) throw new RuntimeException("Fila " . ($i+1) . ": coordinador no encontrado.");
            $coordinadorId = (int)$coord['id'];
        }

        $dniVal = $dni !== '' ? $dni : null; $celularVal = $celular !== '' ? $celular : null;
        $nacimientoVal = $fechaNacimiento !== '' ? $fechaNacimiento : null; $ingresoVal = $fechaIngreso !== '' ? $fechaIngreso : null;
        mysqli_stmt_bind_param($stmt, 'sssssssssi', $codigo, $dniVal, $celularVal, $nombre, $nacimientoVal, $ingresoVal, $funcion, $tipoFuncion, $cuadrilla, $coordinadorId);
        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException("Fila " . ($i+1) . ": " . mysqli_stmt_error($stmt));
        }

        // Semántica MySQL: ON DUPLICATE KEY UPDATE → affected_rows = 1 si insert, 2 si update, 0 si update sin cambios.
        $aff = mysqli_stmt_affected_rows($stmt);
        if ($aff === 1)                       $inserted++;
        elseif ($aff === 2 || $aff === 0)     $updated++;
    }

    mysqli_stmt_close($stmt);
    mysqli_commit($conn);
    sheets_sync_colaboradores($conn);
    echo json_encode([
        'success'  => true,
        'inserted' => $inserted,
        'updated'  => $updated,
        'total'    => $inserted + $updated,
    ]);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    if (isset($stmt) && $stmt) @mysqli_stmt_close($stmt);
    $msg = $e->getMessage();
    if (strpos($msg, 'Duplicate entry') !== false && strpos($msg, 'uq_codigo') !== false) {
        $msg = 'Ese código ya está registrado en otra fila.';
    }
    echo json_encode(['success' => false, 'error' => $msg]);
}
