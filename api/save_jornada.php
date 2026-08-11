<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Crear / editar jornada (admin)
   Body JSON: { id, nombre, hora_inicio, hora_fin, activo, codigo, orden }
   horas en formato HH:MM.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) { echo json_encode(['success' => false, 'error' => 'Payload inválido']); exit; }

$id     = isset($data['id']) ? (int)$data['id'] : 0;
$nombre = trim($data['nombre'] ?? '');
$ini    = trim($data['hora_inicio'] ?? '');
$fin    = trim($data['hora_fin'] ?? '');
$codigo = trim($data['codigo'] ?? '');
$activo = !empty($data['activo']) ? 1 : 0;
$orden  = isset($data['orden']) ? (int)$data['orden'] : 0;

$reHora = '/^\d{1,2}:\d{2}$/';
if ($nombre === '' || mb_strlen($nombre) < 2) { echo json_encode(['success' => false, 'error' => 'Nombre requerido (mínimo 2 caracteres).']); exit; }
if (!preg_match($reHora, $ini)) { echo json_encode(['success' => false, 'error' => 'Hora de inicio inválida (HH:MM).']); exit; }
if (!preg_match($reHora, $fin)) { echo json_encode(['success' => false, 'error' => 'Hora de fin inválida (HH:MM).']); exit; }
if ($ini === $fin) { echo json_encode(['success' => false, 'error' => 'La hora de inicio y fin no pueden ser iguales.']); exit; }
if (mb_strlen($codigo) > 8) { echo json_encode(['success' => false, 'error' => 'Código demasiado largo (máx. 8).']); exit; }
$codigoVal = $codigo === '' ? null : $codigo;

if ($id > 0) {
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE jornadas SET codigo=?, nombre=?, hora_inicio=?, hora_fin=?, activo=?, orden=? WHERE id=?"
    );
    mysqli_stmt_bind_param($stmt, 'ssssiii', $codigoVal, $nombre, $ini, $fin, $activo, $orden, $id);
    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) { echo json_encode(['success' => false, 'error' => $err]); exit; }
    echo json_encode(['success' => true, 'id' => $id]);
} else {
    // Si no se indicó orden, va al final.
    if ($orden <= 0) {
        $r = mysqli_query($conn, "SELECT COALESCE(MAX(orden),0)+1 AS n FROM jornadas");
        $orden = (int)(mysqli_fetch_assoc($r)['n'] ?? 1);
    }
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO jornadas (codigo, nombre, hora_inicio, hora_fin, activo, orden) VALUES (?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'ssssii', $codigoVal, $nombre, $ini, $fin, $activo, $orden);
    $ok  = mysqli_stmt_execute($stmt);
    $err = mysqli_stmt_error($stmt);
    $newId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    if (!$ok) { echo json_encode(['success' => false, 'error' => $err]); exit; }
    echo json_encode(['success' => true, 'id' => $newId]);
}
