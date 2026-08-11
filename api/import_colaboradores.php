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
    "INSERT INTO colaboradores (codigo, nombre, funcion_principal, cuadrilla, activo)
          VALUES (?, ?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE
       nombre = VALUES(nombre),
       funcion_principal = VALUES(funcion_principal),
       cuadrilla = VALUES(cuadrilla),
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

        // Re-validación defensiva (cliente ya validó)
        if ($codigo === '' || !preg_match('/^[A-Za-z0-9]+$/', $codigo) || mb_strlen($codigo) > 20) {
            throw new RuntimeException("Fila " . ($i+1) . ": código inválido (solo letras y números, máx. 20).");
        }
        if (mb_strlen($nombre) < 3)  throw new RuntimeException("Fila " . ($i+1) . ": nombre inválido.");
        if ($funcion === '')         throw new RuntimeException("Fila " . ($i+1) . ": función vacía.");
        if ($cuadrilla === '')       throw new RuntimeException("Fila " . ($i+1) . ": cuadrilla vacía.");

        mysqli_stmt_bind_param($stmt, 'ssss', $codigo, $nombre, $funcion, $cuadrilla);
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
