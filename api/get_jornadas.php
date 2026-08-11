<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Listar jornadas (catálogo completo, admin)
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/turno.php');
api_require_login();

header('Content-Type: application/json');

echo json_encode(['success' => true, 'data' => listar_jornadas($conn, false)]);
