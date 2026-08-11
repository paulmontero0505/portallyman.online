<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API PÚBLICA (sin login) · Refrigerio (iniciar / terminar)
   Body JSON: { dni, accion: 'iniciar' | 'terminar' }
   · iniciar : estado → refrigerio + evento refrigerio con hora_inicio (sin fin)
   · terminar: cierra el evento abierto (hora_fin) + estado → activo
   Requiere que el colaborador ya tenga ingreso en el turno vigente.
   ═══════════════════════════════════════════════════════════════════════ */
require_once(__DIR__ . '/../../includes/db.php');
require_once(__DIR__ . '/../../includes/turno.php');

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) { echo json_encode(['success' => false, 'error' => 'Payload inválido']); exit; }

$dni    = trim($data['dni'] ?? '');
$accion = trim($data['accion'] ?? '');
// Radio: solo aplica al terminar (Sí/No). null = no enviado.
$radio  = array_key_exists('radio', $data) ? (int)((bool)$data['radio']) : null;
if (!preg_match('/^\d{8}$/', $dni))                 { echo json_encode(['success' => false, 'error' => 'DNI inválido.']); exit; }
if (!in_array($accion, ['iniciar', 'terminar'], true)) { echo json_encode(['success' => false, 'error' => 'Acción inválida.']); exit; }

// Colaborador.
$stmt = mysqli_prepare($conn, "SELECT id, codigo, nombre FROM colaboradores WHERE dni = ? AND activo = 1 LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $dni);
mysqli_stmt_execute($stmt);
$colab = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$colab) { echo json_encode(['success' => false, 'error' => 'Colaborador no encontrado.']); exit; }

// Turno vigente + su registro de personal.
$turno = obtener_turno_actual($conn);
if (!$turno)                        { echo json_encode(['success' => false, 'error' => 'No hay un turno vigente.']); exit; }
if ($turno['estado'] === 'cerrado') { echo json_encode(['success' => false, 'error' => 'El turno está cerrado.']); exit; }

$turnoId = (int)$turno['id'];
$colabId = (int)$colab['id'];

$q = mysqli_prepare($conn, "SELECT id, estado FROM turno_personal WHERE turno_id = ? AND colaborador_id = ? LIMIT 1");
mysqli_stmt_bind_param($q, 'ii', $turnoId, $colabId);
mysqli_stmt_execute($q);
$tp = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
mysqli_stmt_close($q);
if (!$tp) { echo json_encode(['success' => false, 'error' => 'Primero registra tu ingreso al turno.']); exit; }
$tpId = (int)$tp['id'];

// Evento de refrigerio abierto (sin hora_fin), si lo hay.
$qe = mysqli_prepare($conn,
    "SELECT id FROM turno_eventos WHERE turno_personal_id = ? AND tipo = 'refrigerio' AND hora_fin IS NULL ORDER BY id DESC LIMIT 1");
mysqli_stmt_bind_param($qe, 'i', $tpId);
mysqli_stmt_execute($qe);
$abierto = mysqli_fetch_assoc(mysqli_stmt_get_result($qe));
mysqli_stmt_close($qe);

$hora = date('H:i');

if ($accion === 'iniciar') {
    if ($abierto) { echo json_encode(['success' => false, 'error' => 'Ya tienes un refrigerio en curso.']); exit; }
    // Un solo refrigerio por turno: si ya registró uno (aunque esté terminado), no otro.
    $qy = mysqli_prepare($conn, "SELECT id FROM turno_eventos WHERE turno_personal_id = ? AND tipo = 'refrigerio' LIMIT 1");
    mysqli_stmt_bind_param($qy, 'i', $tpId);
    mysqli_stmt_execute($qy);
    $yaTuvo = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($qy));
    mysqli_stmt_close($qy);
    if ($yaTuvo) { echo json_encode(['success' => false, 'error' => 'Ya registraste tu refrigerio en este turno.']); exit; }
    $ins = mysqli_prepare($conn,
        "INSERT INTO turno_eventos (turno_personal_id, tipo, motivo, hora_inicio, hora_fin, observaciones)
              VALUES (?, 'refrigerio', 'Refrigerio', ?, NULL, NULL)");
    mysqli_stmt_bind_param($ins, 'is', $tpId, $hora);
    $ok = mysqli_stmt_execute($ins);
    $err = mysqli_stmt_error($ins);
    mysqli_stmt_close($ins);
    if (!$ok) { echo json_encode(['success' => false, 'error' => $err]); exit; }
    mysqli_query($conn, "UPDATE turno_personal SET estado = 'refrigerio' WHERE id = " . $tpId);
    $det = 'Auto-registro · Refrigerio inicio ' . $hora;
    $msg = 'Refrigerio iniciado a las ' . $hora . '.';
} else {
    if (!$abierto) { echo json_encode(['success' => false, 'error' => 'No tienes un refrigerio en curso.']); exit; }
    $eid = (int)$abierto['id'];
    $upd = mysqli_prepare($conn, "UPDATE turno_eventos SET hora_fin = ? WHERE id = ?");
    mysqli_stmt_bind_param($upd, 'si', $hora, $eid);
    $ok = mysqli_stmt_execute($upd);
    $err = mysqli_stmt_error($upd);
    mysqli_stmt_close($upd);
    if (!$ok) { echo json_encode(['success' => false, 'error' => $err]); exit; }
    // Al terminar: estado activo y, si el colaborador indicó radio, se actualiza.
    if ($radio !== null) {
        mysqli_query($conn, "UPDATE turno_personal SET estado = 'activo', radio = " . $radio . " WHERE id = " . $tpId);
    } else {
        mysqli_query($conn, "UPDATE turno_personal SET estado = 'activo' WHERE id = " . $tpId);
    }
    $det = 'Auto-registro · Refrigerio fin ' . $hora . ($radio !== null ? ' · ' . ($radio ? 'Con radio' : 'Sin radio') : '');
    $msg = 'Refrigerio terminado a las ' . $hora . '.';
}

// Auditoría (sin sesión).
$aud = mysqli_prepare($conn,
    "INSERT INTO turno_acciones (turno_id, usuario_id, usuario_nombre, usuario_rol, tipo, colaborador_codigo, colaborador_nombre, detalle)
          VALUES (?, NULL, 'Auto-registro', 'Colaborador', 'evento', ?, ?, ?)");
mysqli_stmt_bind_param($aud, 'isss', $turnoId, $colab['codigo'], $colab['nombre'], $det);
mysqli_stmt_execute($aud);
mysqli_stmt_close($aud);

echo json_encode(['success' => true, 'mensaje' => $msg, 'estado' => ($accion === 'iniciar' ? 'refrigerio' : 'activo')]);
