<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/incidencias_catalogo.php');

api_require_admin();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Solicitud invalida.']);
    exit;
}

$incidenciaId = isset($data['incidencia_id']) ? (int)$data['incidencia_id'] : 0;
$colaboradorId = isset($data['colaborador_id']) ? (int)$data['colaborador_id'] : 0;
$tipo = trim($data['tipo_sancion'] ?? '');
$fechaInicio = trim($data['fecha_inicio'] ?? '');
$fechaFin = trim($data['fecha_fin'] ?? '');
$evidenciaPath = trim($data['evidencia_path'] ?? '');

if (!in_array($tipo, ['amonestacion_escrita', 'suspension'], true)
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio)
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)
    || $fechaFin < $fechaInicio) {
    echo json_encode(['success' => false, 'error' => 'Completa tipo de sancion y periodo valido.']);
    exit;
}

$incidencia = null;
if ($incidenciaId > 0) {
    $stmt = mysqli_prepare($conn, 'SELECT * FROM incidencias WHERE id=? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $incidenciaId);
    mysqli_stmt_execute($stmt);
    $incidencia = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$incidencia) {
        echo json_encode(['success' => false, 'error' => 'Incidencia no encontrada.']);
        exit;
    }
    $colaboradorId = (int)$incidencia['colaborador_id'];
}

if ($colaboradorId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Selecciona un trabajador.']);
    exit;
}

$stmt = mysqli_prepare($conn, 'SELECT id, nombre, funcion_principal FROM colaboradores WHERE id=? AND activo=1 LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $colaboradorId);
mysqli_stmt_execute($stmt);
$colaborador = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$colaborador) {
    echo json_encode(['success' => false, 'error' => 'Selecciona un trabajador activo.']);
    exit;
}

if ($incidencia) {
    $punto = $incidencia['punto_mejorar'];
    $competencia = inc_competencia_de($punto);
    $impacto = $incidencia['impacto'];
    $turno = $incidencia['turno'] ?? '';
    $fechaIncidencia = $incidencia['fecha'];
    $zona = $incidencia['zona_trabajo'] ?? '';
    $detalle = $incidencia['detalle'] ?? '';
    $declaracionPath = $incidencia['declaracion_path'] ?? '';

    if ($competencia === null) {
        echo json_encode(['success' => false, 'error' => 'La incidencia anexada no tiene un punto valido.']);
        exit;
    }
    if (!array_key_exists($impacto, inc_impactos())) {
        echo json_encode(['success' => false, 'error' => 'La incidencia anexada no tiene impacto valido.']);
        exit;
    }
    if (!in_array($turno, array_keys(inc_turnos()), true)) {
        echo json_encode(['success' => false, 'error' => 'La incidencia anexada no tiene turno valido.']);
        exit;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaIncidencia)) {
        echo json_encode(['success' => false, 'error' => 'La incidencia anexada no tiene fecha valida.']);
        exit;
    }
    if (!in_array($zona, inc_zonas(false), true)) {
        echo json_encode(['success' => false, 'error' => 'La incidencia anexada no tiene zona valida.']);
        exit;
    }
} else {
    $punto = 'Sancion disciplinaria manual';
    $competencia = 'Disciplina profesional';
    $impacto = $tipo === 'suspension' ? 'alto' : 'bajo';
    $turno = null;
    $fechaIncidencia = $fechaInicio;
    $zona = null;
    $detalle = trim($data['detalle'] ?? '');
    $declaracionPath = '';
}

$dias = (new DateTime($fechaInicio))->diff(new DateTime($fechaFin))->days + 1;
$adminNombre = $_SESSION['user_name'] ?? 'Administrador';
$adminId = (int)($_SESSION['user_id'] ?? 0);
$incidenciaIdDb = $incidenciaId > 0 ? $incidenciaId : null;
$declaracionDb = $declaracionPath !== '' ? $declaracionPath : null;
$evidenciaDb = $evidenciaPath !== '' ? $evidenciaPath : null;

mysqli_begin_transaction($conn);
$ok = true;

if ($incidenciaId > 0) {
    $stmt = mysqli_prepare($conn, "UPDATE incidencias SET sancion_disciplinaria=?, medida_aplicada_por=?, medida_aplicada_por_id=?, medida_aplicada_at=NOW() WHERE id=?");
    mysqli_stmt_bind_param($stmt, 'ssii', $tipo, $adminNombre, $adminId, $incidenciaId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

if ($ok) {
    $sql = 'INSERT INTO sanciones_disciplinarias
          (incidencia_id, colaborador_id, colaborador_nombre, colaborador_cargo, tipo_sancion,
           impacto, punto_mejorar, competencia, turno, fecha_incidencia, zona_trabajo, detalle,
           aplicado_por, aplicado_por_id, fecha_inicio, fecha_fin, dias_sancion, evidencia_path, declaracion_path)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
          colaborador_id=VALUES(colaborador_id), colaborador_nombre=VALUES(colaborador_nombre),
          colaborador_cargo=VALUES(colaborador_cargo), tipo_sancion=VALUES(tipo_sancion),
          impacto=VALUES(impacto), punto_mejorar=VALUES(punto_mejorar), competencia=VALUES(competencia),
          turno=VALUES(turno), fecha_incidencia=VALUES(fecha_incidencia), zona_trabajo=VALUES(zona_trabajo),
          detalle=VALUES(detalle), fecha_inicio=VALUES(fecha_inicio), fecha_fin=VALUES(fecha_fin),
          dias_sancion=VALUES(dias_sancion), evidencia_path=VALUES(evidencia_path),
          declaracion_path=VALUES(declaracion_path), aplicado_por=VALUES(aplicado_por),
          aplicado_por_id=VALUES(aplicado_por_id)';
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param(
        $stmt,
        'iisssssssssssississ',
        $incidenciaIdDb,
        $colaboradorId,
        $colaborador['nombre'],
        $colaborador['funcion_principal'],
        $tipo,
        $impacto,
        $punto,
        $competencia,
        $turno,
        $fechaIncidencia,
        $zona,
        $detalle,
        $adminNombre,
        $adminId,
        $fechaInicio,
        $fechaFin,
        $dias,
        $evidenciaDb,
        $declaracionDb
    );
    $ok = mysqli_stmt_execute($stmt);
    $error = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
}

if ($ok) {
    mysqli_commit($conn);
    echo json_encode(['success' => true]);
} else {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $error ?? 'No se pudo registrar la sancion.']);
}
