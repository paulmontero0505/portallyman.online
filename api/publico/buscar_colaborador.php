<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API PÚBLICA (sin login) · Buscar colaborador por DNI
   GET ?dni=12345678
   Devuelve el colaborador activo, el turno vigente y su estado en él
   (para decidir en la página pública si mostrar Ingreso o Refrigerio).
   ═══════════════════════════════════════════════════════════════════════ */
require_once(__DIR__ . '/../../includes/db.php');
require_once(__DIR__ . '/../../includes/turno.php');

header('Content-Type: application/json');

$dni = isset($_GET['dni']) ? trim($_GET['dni']) : '';
if (!preg_match('/^\d{8}$/', $dni)) {
    echo json_encode(['success' => false, 'error' => 'Ingresa un DNI válido (8 dígitos).']); exit;
}

// Colaborador activo por DNI.
$stmt = mysqli_prepare($conn,
    "SELECT id, codigo, dni, nombre, funcion_principal, tipo_funcion, cuadrilla
       FROM colaboradores WHERE dni = ? AND activo = 1 LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $dni);
mysqli_stmt_execute($stmt);
$colab = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$colab) {
    echo json_encode(['success' => false, 'error' => 'No se encontró un colaborador activo con ese DNI.']); exit;
}

// Turno vigente.
$turno = obtener_turno_actual($conn);
if (!$turno) {
    echo json_encode(['success' => false, 'error' => 'No hay un turno vigente configurado.']); exit;
}

// ¿Ya está en el turno vigente? ¿estado? ¿refrigerio abierto?
$estado = null; $tpId = 0; $refrigerioAbierto = false; $refrigerioCompletado = false; $enTurno = false;
$q = mysqli_prepare($conn, "SELECT id, estado FROM turno_personal WHERE turno_id = ? AND colaborador_id = ? LIMIT 1");
$tid = (int)$turno['id']; $cid = (int)$colab['id'];
mysqli_stmt_bind_param($q, 'ii', $tid, $cid);
mysqli_stmt_execute($q);
$tp = mysqli_fetch_assoc(mysqli_stmt_get_result($q));
mysqli_stmt_close($q);
if ($tp) {
    $enTurno = true; $tpId = (int)$tp['id']; $estado = $tp['estado'];
    // Refrigerio en curso (sin hora fin).
    $qe = mysqli_prepare($conn,
        "SELECT id FROM turno_eventos WHERE turno_personal_id = ? AND tipo = 'refrigerio' AND hora_fin IS NULL ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($qe, 'i', $tpId);
    mysqli_stmt_execute($qe);
    $refrigerioAbierto = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($qe));
    mysqli_stmt_close($qe);
    // Refrigerio ya terminado (con hora fin) → ciclo del turno completo.
    $qc = mysqli_prepare($conn,
        "SELECT id FROM turno_eventos WHERE turno_personal_id = ? AND tipo = 'refrigerio' AND hora_fin IS NOT NULL LIMIT 1");
    mysqli_stmt_bind_param($qc, 'i', $tpId);
    mysqli_stmt_execute($qc);
    $refrigerioCompletado = (bool) mysqli_fetch_assoc(mysqli_stmt_get_result($qc));
    mysqli_stmt_close($qc);
}

// Ubicaciones activas (para el selector de ingreso).
$ubicaciones = [];
$r = mysqli_query($conn, "SELECT nombre FROM ubicaciones WHERE activo = 1 ORDER BY orden, nombre");
while ($row = mysqli_fetch_assoc($r)) $ubicaciones[] = $row['nombre'];

echo json_encode([
    'success'     => true,
    'colaborador' => [
        'id'          => (int)$colab['id'],
        'codigo'      => $colab['codigo'],
        'dni'         => $colab['dni'],
        'nombre'      => $colab['nombre'],
        'funcion'     => $colab['funcion_principal'],
        'tipoFuncion' => $colab['tipo_funcion'] ?? '',
        'cuadrilla'   => $colab['cuadrilla'] ?? '',
    ],
    'turno' => [
        'id'      => (int)$turno['id'],
        'label'   => $turno['label'],
        'estado'  => $turno['estado'],
        'fecha'   => $turno['fecha'],
        'jornada' => $turno['jornada'],
    ],
    'enTurno'              => $enTurno,
    'tpId'                 => $tpId,
    'estado'               => $estado,
    'refrigerioAbierto'    => $refrigerioAbierto,
    'refrigerioCompletado' => $refrigerioCompletado,
    'ubicaciones'          => $ubicaciones,
]);
