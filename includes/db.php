<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA · Conexión a base de datos (cPanel / producción)
   ───────────────────────────────────────────────────────────────────────
   Define la conexión a la base principal del sistema ($conn → portally_system)
   y expone las credenciales de la base de Operaciones (portally_operaciones)
   como constantes + helper conn_operaciones(), para que todo el sistema use
   las MISMAS credenciales (Sheets, proxy de operaciones, turno, etc.).

   Las credenciales se pueden sobreescribir por variables de entorno
   (DB_USER, DB_PASS, OPER_DB_*); si no, usa los valores de cPanel de abajo.
   ═══════════════════════════════════════════════════════════════════════ */

// ── Zona horaria (Perú / Lima, UTC-5) ────────────────────────────────────
// Todo el sistema calcula turnos, horas de refrigerio, ingresos, etc. con la
// hora de PHP; se fija explícitamente para no depender del huso del servidor
// (cPanel suele estar en UTC).
date_default_timezone_set('America/Lima');

// ── Base principal del sistema ──────────────────────────────────────────
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_USER = getenv('DB_USER') ?: 'portally_sa';
$DB_PASS = getenv('DB_PASS') ?: 'Sistemas2100*';
$DB_NAME = getenv('DB_NAME') ?: 'portally_system';
$charset = 'utf8mb4';

// ── Credenciales de la base de Operaciones (módulo Node / Tallyman) ──────
// Mismas credenciales por defecto; cambia solo OPER_DB_NAME si difiere.
if (!defined('OPER_DB_HOST')) define('OPER_DB_HOST', getenv('OPER_DB_HOST') ?: $DB_HOST);
if (!defined('OPER_DB_USER')) define('OPER_DB_USER', getenv('OPER_DB_USER') ?: $DB_USER);
if (!defined('OPER_DB_PASS')) define('OPER_DB_PASS', getenv('OPER_DB_PASS') ?: $DB_PASS);
if (!defined('OPER_DB_NAME')) define('OPER_DB_NAME', getenv('OPER_DB_NAME') ?: 'portally_operaciones');

// ── Conexión principal ($conn lo usa todo el sistema) ───────────────────
$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if (!$conn) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Error al conectar con la base de datos: ' . mysqli_connect_error()]);
    exit;
}
mysqli_set_charset($conn, $charset);
// Alinea la hora de MySQL (NOW()/CURRENT_TIMESTAMP) a Perú (UTC-5), igual que PHP.
@mysqli_query($conn, "SET time_zone = '-05:00'");

/** Conexión a la base de Operaciones (portally_operaciones). Devuelve el
 *  recurso mysqli o null si no se pudo conectar (no rompe el flujo). */
function conn_operaciones() {
    $c = @mysqli_connect(OPER_DB_HOST, OPER_DB_USER, OPER_DB_PASS, OPER_DB_NAME);
    if ($c) { mysqli_set_charset($c, 'utf8mb4'); return $c; }
    return null;
}
