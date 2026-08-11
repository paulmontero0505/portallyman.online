<?php
/* Decisión administrativa EVADES de una incidencia. Sólo Administrador. */
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_admin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']); exit;
}

$id = (int)($data['id'] ?? 0);
$aplicaDescuento = !empty($data['aplica_descuento']);
$sancion = trim((string)($data['sancion_disciplinaria'] ?? 'sin_sancion'));
$fechaInicio = trim((string)($data['fecha_inicio'] ?? ''));
$fechaFin = trim((string)($data['fecha_fin'] ?? ''));
$evidenciaPath = trim((string)($data['evidencia_path'] ?? ''));

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Incidencia inválida.']); exit;
}
if (!in_array($sancion, ['sin_sancion', 'amonestacion_escrita', 'suspension'], true)) {
    echo json_encode(['success' => false, 'error' => 'Tipo de sanción inválido.']); exit;
}

$stmt = mysqli_prepare($conn, 'SELECT impacto, colaborador_id, colaborador_nombre, colaborador_cargo, punto_mejorar, fecha, zona_trabajo, declaracion_path FROM incidencias WHERE id=? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$incidencia = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$incidencia) {
    echo json_encode(['success' => false, 'error' => 'La incidencia no existe.']); exit;
}

// Escala EVADES para una incidencia: el nivel de impacto define el descuento.
$descuentosPorImpacto = ['minimo' => 2, 'bajo' => 2, 'moderado' => 4, 'alto' => 6, 'critico' => 8];
$puntos = $aplicaDescuento ? ($descuentosPorImpacto[$incidencia['impacto']] ?? 0) : 0;
$frecuenciaGuardada = $aplicaDescuento ? 1 : 0;
$hayMedida = $puntos > 0 || $sancion !== 'sin_sancion';
$adminNombre = $_SESSION['user_name'] ?? 'Administrador';
$adminId = (int)($_SESSION['user_id'] ?? 0);
if ($sancion !== 'sin_sancion') {
    $fechaInicio = $fechaInicio ?: $incidencia['fecha']; $fechaFin = $fechaFin ?: $fechaInicio;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin) || $fechaFin < $fechaInicio) { echo json_encode(['success'=>false,'error'=>'Indica un periodo de sanción válido.']); exit; }
    $diasSancion = (new DateTime($fechaInicio))->diff(new DateTime($fechaFin))->days + 1;
} else { $fechaInicio = null; $fechaFin = null; $diasSancion = 0; $evidenciaPath = null; }

$stmt = mysqli_prepare(
    $conn,
    'UPDATE incidencias
        SET descuento_puntos=?, frecuencia_descuento=NULLIF(?, 0), sancion_disciplinaria=?,
            medida_aplicada_por=?, medida_aplicada_por_id=?, medida_aplicada_at=IF(?=1, NOW(), NULL)
      WHERE id=?'
);
mysqli_stmt_bind_param($stmt, 'iissiii', $puntos, $frecuenciaGuardada, $sancion, $adminNombre, $adminId, $hayMedida, $id);
$ok = mysqli_stmt_execute($stmt);
$error = mysqli_stmt_error($stmt);
mysqli_stmt_close($stmt);
if (!$ok) {
    echo json_encode(['success' => false, 'error' => $error ?: 'No se pudo guardar la decisión.']); exit;
}

// Una sanción siempre deja una evidencia trazable en su propio módulo.
if ($sancion !== 'sin_sancion') {
    $stmt = mysqli_prepare(
        $conn,
        'INSERT INTO sanciones_disciplinarias
          (incidencia_id, colaborador_id, colaborador_nombre, colaborador_cargo, tipo_sancion,
           impacto, punto_mejorar, fecha_incidencia, zona_trabajo, aplicado_por, aplicado_por_id, fecha_inicio, fecha_fin, dias_sancion, evidencia_path, declaracion_path)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE colaborador_id=VALUES(colaborador_id), colaborador_nombre=VALUES(colaborador_nombre),
           colaborador_cargo=VALUES(colaborador_cargo), tipo_sancion=VALUES(tipo_sancion), impacto=VALUES(impacto),
           punto_mejorar=VALUES(punto_mejorar), fecha_incidencia=VALUES(fecha_incidencia), zona_trabajo=VALUES(zona_trabajo),
           aplicado_por=VALUES(aplicado_por), aplicado_por_id=VALUES(aplicado_por_id), fecha_inicio=VALUES(fecha_inicio), fecha_fin=VALUES(fecha_fin), dias_sancion=VALUES(dias_sancion), evidencia_path=COALESCE(NULLIF(VALUES(evidencia_path),\'\'),evidencia_path), declaracion_path=VALUES(declaracion_path), updated_at=CURRENT_TIMESTAMP'
    );
    $colaboradorId = $incidencia['colaborador_id'] !== null ? (int)$incidencia['colaborador_id'] : null;
    mysqli_stmt_bind_param($stmt, 'iissssssssississ', $id, $colaboradorId, $incidencia['colaborador_nombre'], $incidencia['colaborador_cargo'], $sancion,
        $incidencia['impacto'], $incidencia['punto_mejorar'], $incidencia['fecha'], $incidencia['zona_trabajo'], $adminNombre, $adminId, $fechaInicio, $fechaFin, $diasSancion, $evidenciaPath, $incidencia['declaracion_path']);
    $ok = mysqli_stmt_execute($stmt); $error = mysqli_stmt_error($stmt); mysqli_stmt_close($stmt);
} else {
    $stmt = mysqli_prepare($conn, 'DELETE FROM sanciones_disciplinarias WHERE incidencia_id=?');
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt); $error = mysqli_stmt_error($stmt); mysqli_stmt_close($stmt);
}
if (!$ok) {
    echo json_encode(['success' => false, 'error' => $error ?: 'La medida se guardó, pero no se pudo sincronizar la sanción.']); exit;
}

echo json_encode([
    'success' => true,
    'data' => [
        'descuento_puntos' => $puntos,
        'frecuencia_descuento' => $aplicaDescuento ? 1 : null,
        'sancion_disciplinaria' => $sancion,
        'medida_aplicada_por' => $hayMedida ? $adminNombre : null,
        'medida_aplicada_at' => $hayMedida ? date('Y-m-d H:i:s') : null,
    ],
]);
