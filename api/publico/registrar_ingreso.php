<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API PÚBLICA (sin login) · Registrar INGRESO al turno
   Body JSON: { dni, ubicacion }
   El colaborador se auto-agrega al turno vigente con su función principal,
   la ubicación elegida y estado 'activo'. Replica las reglas de
   add_personal_turno.php (turno abierto, adyacencia Día↔Noche, duplicado).
   ═══════════════════════════════════════════════════════════════════════ */
require_once(__DIR__ . '/../../includes/db.php');
require_once(__DIR__ . '/../../includes/turno.php');

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) { echo json_encode(['success' => false, 'error' => 'Payload inválido']); exit; }

$dni       = trim($data['dni'] ?? '');
$ubicacion = trim($data['ubicacion'] ?? '');
if (!preg_match('/^\d{8}$/', $dni)) { echo json_encode(['success' => false, 'error' => 'DNI inválido.']); exit; }

// ── Geocerca: el ingreso solo se permite dentro del radio del terminal ──
$GEO_LAT = -11.58901; $GEO_LNG = -77.2748; $GEO_RADIO_M = 300;
$lat = isset($data['lat']) && $data['lat'] !== '' ? (float)$data['lat'] : null;
$lng = isset($data['lng']) && $data['lng'] !== '' ? (float)$data['lng'] : null;
if ($lat === null || $lng === null) {
    echo json_encode(['success' => false, 'error' => 'No se pudo obtener tu ubicación. Activa el GPS y permite el acceso a la ubicación.']); exit;
}
$dist = geo_distancia_m($GEO_LAT, $GEO_LNG, $lat, $lng);
if ($dist > $GEO_RADIO_M) {
    echo json_encode(['success' => false, 'error' => 'Estás fuera del área permitida (' . round($dist) . ' m). Debes estar a menos de ' . $GEO_RADIO_M . ' m del terminal.']); exit;
}

// ── Foto de asistencia (obligatoria) ──
$foto = $data['foto'] ?? '';
if (!is_string($foto) || !preg_match('#^data:image/(jpe?g|png);base64,#', $foto)) {
    echo json_encode(['success' => false, 'error' => 'La foto de asistencia es obligatoria.']); exit;
}
$fotoBin = base64_decode(preg_replace('#^data:image/[^;]+;base64,#', '', $foto), true);
if ($fotoBin === false || strlen($fotoBin) < 500) {
    echo json_encode(['success' => false, 'error' => 'La foto no es válida.']); exit;
}
if (strlen($fotoBin) > 4 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'La foto es demasiado grande (máx. 4 MB).']); exit;
}

// Colaborador activo.
$stmt = mysqli_prepare($conn,
    "SELECT id, codigo, nombre, funcion_principal, activo FROM colaboradores WHERE dni = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $dni);
mysqli_stmt_execute($stmt);
$colab = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$colab)                       { echo json_encode(['success' => false, 'error' => 'Colaborador no encontrado.']); exit; }
if ((int)$colab['activo'] !== 1)   { echo json_encode(['success' => false, 'error' => 'El colaborador está inactivo.']); exit; }

// Turno vigente + validaciones.
$turno = obtener_turno_actual($conn);
if (!$turno)                        { echo json_encode(['success' => false, 'error' => 'No hay un turno vigente.']); exit; }
if ($turno['estado'] === 'cerrado') { echo json_encode(['success' => false, 'error' => 'El turno está cerrado.']); exit; }

$turnoId = (int)$turno['id'];
$colabId = (int)$colab['id'];
$funcion = $colab['funcion_principal'];
$ubicacionVal = $ubicacion === '' ? null : $ubicacion;
$fecha   = $turno['fecha'];
$jornada = $turno['jornada'];

// Regla turnos adyacentes: Día↔Noche consecutivos no comparten personal.
if ($jornada === 'D') {
    $stmtAdj = mysqli_prepare($conn,
        "SELECT t.id FROM turnos t JOIN jornadas j ON j.id = t.jornada_id
          WHERE j.codigo = 'N' AND t.fecha IN (DATE_SUB(?, INTERVAL 1 DAY), ?)");
    mysqli_stmt_bind_param($stmtAdj, 'ss', $fecha, $fecha);
} else {
    $stmtAdj = mysqli_prepare($conn,
        "SELECT t.id FROM turnos t JOIN jornadas j ON j.id = t.jornada_id
          WHERE j.codigo = 'D' AND t.fecha IN (?, DATE_ADD(?, INTERVAL 1 DAY))");
    mysqli_stmt_bind_param($stmtAdj, 'ss', $fecha, $fecha);
}
mysqli_stmt_execute($stmtAdj);
$resAdj = mysqli_stmt_get_result($stmtAdj);
$adjIds = [];
while ($rw = mysqli_fetch_assoc($resAdj)) $adjIds[] = (int)$rw['id'];
mysqli_stmt_close($stmtAdj);

if ($adjIds) {
    $inIds = implode(',', $adjIds);
    $stmtConf = mysqli_prepare($conn,
        "SELECT 1 FROM turno_personal WHERE turno_id IN ($inIds) AND colaborador_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmtConf, 'i', $colabId);
    mysqli_stmt_execute($stmtConf);
    $conf = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtConf));
    mysqli_stmt_close($stmtConf);
    if ($conf) { echo json_encode(['success' => false, 'error' => 'Ya trabajaste el turno adyacente; no puedes registrarte en este.']); exit; }
}

// ¿Ya registró su ingreso en este turno?
$q = mysqli_prepare($conn, "SELECT id FROM turno_personal WHERE turno_id = ? AND colaborador_id = ? LIMIT 1");
mysqli_stmt_bind_param($q, 'ii', $turnoId, $colabId);
mysqli_stmt_execute($q);
if (mysqli_fetch_assoc(mysqli_stmt_get_result($q))) {
    mysqli_stmt_close($q);
    echo json_encode(['success' => false, 'error' => 'Ya registraste tu ingreso en este turno.']); exit;
}
mysqli_stmt_close($q);

// Guardar la foto de asistencia en uploads/asistencia/.
$dir = __DIR__ . '/../../uploads/asistencia';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$fname = $turnoId . '_' . $colabId . '_' . date('YmdHis') . '.jpg';
$fotoPath = null;
if (@file_put_contents($dir . '/' . $fname, $fotoBin) !== false) {
    $fotoPath = 'uploads/asistencia/' . $fname;
}

// Alta en el turno (estado activo, horario = etiqueta del turno, foto + coordenadas).
$horario = $turno['label'];
$ins = mysqli_prepare($conn,
    "INSERT INTO turno_personal (turno_id, colaborador_id, funcion, ubicacion, estado, horario_colab, foto_ingreso, ingreso_lat, ingreso_lng)
          VALUES (?, ?, ?, ?, 'activo', ?, ?, ?, ?)");
mysqli_stmt_bind_param($ins, 'iissssdd', $turnoId, $colabId, $funcion, $ubicacionVal, $horario, $fotoPath, $lat, $lng);
$ok  = mysqli_stmt_execute($ins);
$err = mysqli_stmt_error($ins);
mysqli_stmt_close($ins);
if (!$ok) {
    $msg = (strpos((string)$err, 'Duplicate entry') !== false) ? 'Ya estás en el turno.' : $err;
    echo json_encode(['success' => false, 'error' => $msg]); exit;
}

// Auditoría (sin sesión: usuario = Auto-registro).
$det = 'Auto-registro · Ingreso · ' . $funcion . ($ubicacionVal ? ' @ ' . $ubicacionVal : '');
$aud = mysqli_prepare($conn,
    "INSERT INTO turno_acciones (turno_id, usuario_id, usuario_nombre, usuario_rol, tipo, colaborador_codigo, colaborador_nombre, detalle)
          VALUES (?, NULL, 'Auto-registro', 'Colaborador', 'alta', ?, ?, ?)");
mysqli_stmt_bind_param($aud, 'isss', $turnoId, $colab['codigo'], $colab['nombre'], $det);
mysqli_stmt_execute($aud);
mysqli_stmt_close($aud);

echo json_encode([
    'success'  => true,
    'mensaje'  => 'Ingreso registrado. ¡Bienvenido al turno ' . $turno['label'] . '!',
    'ubicacion' => $ubicacionVal,
]);

/** Distancia en metros entre dos coordenadas (fórmula de Haversine). */
function geo_distancia_m($lat1, $lon1, $lat2, $lon2) {
    $R = 6371000; // radio terrestre en metros
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}
