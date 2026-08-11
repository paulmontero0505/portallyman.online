<?php

date_default_timezone_set('America/Lima');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function require_login() {
    if (empty($_SESSION['user_id'])) {
        $base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
        if (basename($base) === 'pages') $base = dirname($base);
        $base = rtrim($base, '/\\');
        header('Location: ' . $base . '/login.php');
        exit;
    }
}

function is_admin() {
    return ($_SESSION['user_rol'] ?? null) === 'Administrador';
}

function can_operate() {
    return in_array($_SESSION['user_rol'] ?? '', ['Administrador', 'Supervisor', 'Coordinador', 'Operador'], true);
}

function can_validate() {
    return in_array($_SESSION['user_rol'] ?? '', ['Administrador', 'Supervisor'], true);
}

function can_report() {
    return in_array($_SESSION['user_rol'] ?? '', ['Administrador', 'Supervisor', 'Coordinador'], true);
}

function require_report() {
    require_login();
    if (!can_report()) {
        http_response_code(403);
        die('403 · No tienes permisos para acceder a esta sección.');
    }
}

function can_operaciones() {
    return in_array($_SESSION['user_rol'] ?? '', ['Administrador', 'Supervisor', 'Coordinador'], true);
}

function require_operaciones() {
    require_login();
    if (!can_operaciones()) {
        http_response_code(403);
        die('403 · No tienes permisos para acceder a Operaciones.');
    }
}

function api_require_operaciones() {
    api_require_login();
    if (!can_operaciones()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No tienes permisos para Operaciones.']);
        exit;
    }
}

function api_require_report() {
    api_require_login();
    if (!can_report()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No tienes permisos para registrar incidencias.']);
        exit;
    }
}

function api_require_validate() {
    api_require_login();
    if (!can_validate()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Solo Supervisor o Administrador.']);
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('403 · No tienes permisos para acceder a esta sección.');
    }
}

function api_require_login() {
    if (empty($_SESSION['user_id'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No autenticado.']);
        exit;
    }
}
function api_require_admin() {
    api_require_login();
    if (!is_admin()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Solo Administrador.']);
        exit;
    }
}

function is_soporte() {
    return ($_SESSION['user_rol'] ?? null) === 'Soporte';
}

function can_tareas() {
    return in_array($_SESSION['user_rol'] ?? '',
        ['Administrador', 'Supervisor', 'Coordinador', 'Soporte'], true);
}

function require_tareas() {
    require_login();
    if (!can_tareas()) {
        http_response_code(403);
        die('403 · No tienes permisos para acceder a Tareas.');
    }
}

function api_require_tareas() {
    api_require_login();
    if (!can_tareas()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No tienes permisos para Tareas.']);
        exit;
    }
}

function can_indicadores() {
    return in_array($_SESSION['user_rol'] ?? '', ['Administrador', 'Supervisor', 'Coordinador'], true);
}

function require_indicadores() {
    require_login();
    if (!can_indicadores()) {
        http_response_code(403);
        die('403 · No tienes permisos para acceder a Indicadores.');
    }
}

function api_require_indicadores() {
    api_require_login();
    if (!can_indicadores()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'No tienes permisos para Indicadores.']);
        exit;
    }
}

function can_delete_turno($conn, $turno_id) {
    $rol = $_SESSION['user_rol'] ?? '';
    if ($rol === 'Administrador' || $rol === 'Supervisor') return true;
    if ($rol !== 'Coordinador') return false;
    $stmt = mysqli_prepare($conn, "SELECT fecha FROM turnos WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $turno_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $row && $row['fecha'] === date('Y-m-d');
}

function can_modify_turno($conn, $turno_id) {
    return can_delete_turno($conn, $turno_id);
}

function api_require_modify_turno($conn, $turno_id) {
    api_require_login();
    if (!can_modify_turno($conn, (int)$turno_id)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Sin permiso. Los Coordinadores solo pueden modificar el turno de hoy.']);
        exit;
    }
}

function api_require_delete_turno($conn, $turno_id) {
    api_require_login();
    if (!can_delete_turno($conn, (int)$turno_id)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Sin permiso para eliminar en este turno. Los Coordinadores solo pueden eliminar del turno de hoy.']);
        exit;
    }
}
