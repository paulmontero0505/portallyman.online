<?php
/* Devuelve el turno vigente (fecha + jornada) para el front del tallyman.
   Reutiliza la lógica central de includes/turno.php.

   Parámetros opcionales:
     ?action=jornadas          → lista todas las jornadas activas
     ?fecha=YYYY-MM-DD&jornada=D  → turno para una fecha y jornada específicas
   Sin parámetros → turno vigente actual. */
require_once(__DIR__ . '/auth.php');
require_once(__DIR__ . '/db.php');
require_once(__DIR__ . '/turno.php');

header('Content-Type: application/json; charset=utf-8');
api_require_operaciones(); // 401/403 según sesión y rol

// ── Catálogo de jornadas (para el selector de filtro) ──
if (($_GET['action'] ?? '') === 'jornadas') {
    echo json_encode(['success' => true, 'data' => listar_jornadas($conn)]);
    exit;
}

// ── Turno para fecha + jornada específicas ──
$fechaParam   = trim($_GET['fecha']   ?? '');
$jornadaParam = trim($_GET['jornada'] ?? '');
if ($fechaParam !== '' && $jornadaParam !== '') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaParam)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Fecha inválida (esperado YYYY-MM-DD).']);
        exit;
    }
    $j = jornada_por_codigo($conn, $jornadaParam);
    if (!$j) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Jornada no encontrada.']);
        exit;
    }
    $t = obtener_turno($conn, (int)$j['id'], $fechaParam);
    if (!$t) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'No se pudo obtener el turno para esa fecha.']);
        exit;
    }
    echo json_encode(['success' => true, 'data' => [
        'fecha'  => $t['fecha'],
        'turno'  => $t['codigo'],
        'nombre' => $t['nombre'],
        'label'  => $t['label'],
        'estado' => $t['estado'],
    ]]);
    exit;
}

// ── Turno para una jornada elegida (sin fecha): el backend resuelve la fecha ──
// según la hora actual (turno noche abierto en la mañana → día anterior). Lo usan
// Registro tallyman y Relevo de turno para abrir en el turno del coordinador.
if ($jornadaParam !== '') {
    $t = obtener_turno_por_codigo($conn, $jornadaParam);
    if (!$t) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Jornada no encontrada.']);
        exit;
    }
    echo json_encode(['success' => true, 'data' => [
        'fecha'  => $t['fecha'],
        'turno'  => $t['codigo'],
        'nombre' => $t['nombre'],
        'label'  => $t['label'],
        'estado' => $t['estado'],
    ]]);
    exit;
}

// ── Turno vigente (comportamiento por defecto) ──
$t = obtener_turno_actual($conn);
if (!$t) {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'No hay jornada vigente configurada.']);
    exit;
}
echo json_encode([
    'success' => true,
    'data' => [
        'fecha'   => $t['fecha'],
        'turno'   => $t['codigo'],
        'nombre'  => $t['nombre'],
        'label'   => $t['label'],
        'estado'  => $t['estado'],
    ],
]);
