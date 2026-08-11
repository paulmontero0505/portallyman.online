<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Proxy seguro hacia la API Node de Operaciones (:4000)
   ───────────────────────────────────────────────────────────────────────
   El navegador NUNCA habla directo con la API Node. Habla same-origin con
   este proxy, que:
     1) exige sesión PHP válida y rol con acceso a Operaciones,
     2) inyecta la identidad (rol/nombre/id) desde $_SESSION en los headers
        x-user-* que la API Node usa (auth simulada Fase 1). Así el rol NO se
        puede falsificar desde el cliente: sale del servidor, no del fetch,
     3) reenvía método + cuerpo a /api/operaciones/<path> y devuelve la
        respuesta tal cual (status + JSON).
     4) Si el Node API no responde y el método es GET, cae en fallback PHP
        que consulta MySQL directo (DB "operaciones") para lectura inmediata.

   Uso desde el front:  api/operaciones_proxy.php?path=naves            (GET)
                        api/operaciones_proxy.php?path=naves&estado=...  (GET + filtro)
                        api/operaciones_proxy.php?path=naves/5/datos     (PUT, cuerpo JSON)
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/auth.php');
require_once('../includes/db.php');   // define OPER_DB_* (credenciales centralizadas)
header('Content-Type: application/json; charset=utf-8');

// ── Credenciales BD Operaciones (vienen de includes/db.php) ──
define('OPER_HOST', OPER_DB_HOST);
define('OPER_USER', OPER_DB_USER);
define('OPER_PASS', OPER_DB_PASS);
define('OPER_DB',   OPER_DB_NAME);

api_require_operaciones(); // 401 si no hay sesión, 403 si el rol no aplica

// Base de la API Node (sobreescribible por variable de entorno del sistema).
$NODE_BASE = getenv('OPERACIONES_API_BASE') ?: 'http://127.0.0.1:4000/api/operaciones';

// ── Ruta destino: solo se permiten los recursos del módulo ──
$path = trim($_GET['path'] ?? '', '/');
if (!preg_match('#^(naves|tipos-nave|tallyman)(/[A-Za-z0-9_\-]+)*$#', $path)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ruta no permitida.']);
    exit;
}

// El histórico transversal (rango) es solo para Administrador.
if ($path === 'tallyman/registros-rango' && ($_SESSION['user_rol'] ?? '') !== 'Administrador') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Solo Administrador puede ver el histórico de registros.']);
    exit;
}

// ── Query params (todo menos 'path') se reenvían a la API ──
$query = $_GET;
unset($query['path']);
$url = $NODE_BASE . '/' . $path;
if (!empty($query)) {
    // RFC3986: el espacio va como %20 (no '+'), para que el estado tipo
    // 'En Puerto' llegue inequívoco a la API.
    $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body   = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
        ? file_get_contents('php://input')
        : null;

// ── Identidad de la sesión inyectada como headers (no falsificable por el cliente) ──
$headers = [
    'Content-Type: application/json',
    'x-user-id: '   . ($_SESSION['user_id']  ?? ''),
    'x-user-role: ' . ($_SESSION['user_rol'] ?? ''),
    // El nombre se URL-codifica para no romper el header con acentos/espacios.
    'x-user-name: ' . rawurlencode($_SESSION['user_name'] ?? ''),
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => $method,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_CONNECTTIMEOUT => 1,  // falla rápido; el fallback PHP entra en ~1 s
]);
if ($body !== null && $body !== '') {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

$resp    = curl_exec($ch);
$apiDown = ($resp === false);
$status  = $apiDown ? 0 : (curl_getinfo($ch, CURLINFO_RESPONSE_CODE) ?: 200);
curl_close($ch);

// ── Si el Node API no responde y es lectura → fallback PHP directo ──────────
if ($apiDown && $method === 'GET') {
    echo fallback_get($path, $query);
    exit;
}

if ($apiDown) {
    echo fallback_write($path, $body, $method);
    exit;
}

http_response_code($status);
echo $resp;

// ── Fallback escritura: POST/PUT/DELETE directo a MySQL ─────────────────────
function fallback_write(string $path, ?string $body, string $method): string
{
    try {
        $pdo = new PDO(
            'mysql:host=' . OPER_HOST . ';port=3306;dbname=' . OPER_DB . ';charset=utf8mb4',
            OPER_USER, OPER_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        http_response_code(503);
        return json_encode(['success' => false, 'error' => 'BD no disponible: ' . $e->getMessage()]);
    }

    $data = $body ? (json_decode($body, true) ?? []) : [];
    $registradoPor = rawurldecode($_SERVER['HTTP_X_USER_NAME'] ?? ($_SESSION['user_name'] ?? 'sistema'));

    // ── POST /naves ──────────────────────────────────────────────────────────
    if ($path === 'naves' && $method === 'POST') {
        $nombre   = trim($data['nombre'] ?? '');
        $tipoId   = isset($data['tipo_nave_id']) ? (int)$data['tipo_nave_id'] : null;
        if (!$nombre || !$tipoId) { http_response_code(400); return json_encode(['success'=>false,'error'=>'nombre y tipo_nave_id son obligatorios.']); }
        $s = $pdo->prepare("INSERT INTO naves (nombre, muelle, tipo_nave_id, actividad_id, eta, etb, etd, estado, datos_adicionales) VALUES (?,?,?,?,?,?,?,?,?)");
        $s->execute([
            $nombre,
            $data['muelle'] ?? null,
            $tipoId,
            isset($data['actividad_id']) && $data['actividad_id'] ? (int)$data['actividad_id'] : null,
            $data['eta'] ?? null, $data['etb'] ?? null, $data['etd'] ?? null,
            $data['estado'] ?? 'Programada',
            isset($data['datos_adicionales']) ? json_encode($data['datos_adicionales']) : null,
        ]);
        $nave = fetch_nave_by_id($pdo, "n.id, n.nombre, n.muelle, n.tipo_nave_id, t.nombre AS tipo_nave, n.actividad_id, ta.nombre AS actividad, n.eta, n.etb, n.etd, n.estado, n.datos_adicionales, n.created_at, n.updated_at", (int)$pdo->lastInsertId());
        http_response_code(201);
        return json_encode(['success'=>true,'data'=>$nave,'_fallback'=>true]);
    }

    // ── POST /tallyman/registros ─────────────────────────────────────────────
    if ($path === 'tallyman/registros' && $method === 'POST') {
        $r = tm_parse_body($data);
        if (isset($r['error'])) { http_response_code(400); return json_encode(['success'=>false,'error'=>$r['error']]); }
        $s = $pdo->prepare(
            "INSERT INTO tallyman_registros
               (fecha_turno, turno, ubicacion_tipo, ubicacion, nave_id, nave_patio, actividad_id,
                estado_pos, status_act, planned, executed, directa, interna, productivity, details,
                cargo_type, bl, producto, unidades, tons, ubic_codigo,
                coord_entrante, coord_saliente, registrado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $s->execute([
            $r['fecha_turno'], $r['turno'], $r['ubicacion_tipo'], $r['ubicacion'],
            $r['nave_id'], $r['nave_patio'], $r['actividad_id'],
            $r['estado_pos'], $r['status_act'], $r['planned'], $r['executed'],
            $r['directa'], $r['interna'], $r['productivity'], $r['details'],
            $r['cargo_type'], $r['bl'], $r['producto'], $r['unidades'],
            $r['tons'], $r['ubic_codigo'], $r['coord_entrante'], $r['coord_saliente'],
            $registradoPor,
        ]);
        if ($r['ubicacion_tipo'] === 'YARD') tm_propagar_planned_yard($pdo, $r);
        $reg = tm_obtener_registro($pdo, (int)$pdo->lastInsertId());
        tm_sync_nave_berth($pdo, $reg, $data);
        tm_sync_despacho_cementero($pdo, $reg, $registradoPor);
        tm_sync_despacho_interna($pdo, $reg, $registradoPor);
        $prev = tm_executed_previo($pdo, $reg);
        http_response_code(201);
        return json_encode(['success'=>true,'data'=>tm_con_resumen($reg, $prev),'_fallback'=>true]);
    }

    // ── PUT /tallyman/registros/{id} ─────────────────────────────────────────
    if (preg_match('#^tallyman/registros/(\d+)$#', $path, $m) && $method === 'PUT') {
        $id = (int)$m[1];
        $r = tm_parse_body($data);
        if (isset($r['error'])) { http_response_code(400); return json_encode(['success'=>false,'error'=>$r['error']]); }
        $s = $pdo->prepare(
            "UPDATE tallyman_registros
                SET ubicacion_tipo=?, ubicacion=?, nave_id=?, nave_patio=?, actividad_id=?,
                    estado_pos=?, status_act=?, planned=?, executed=?,
                    directa=?, interna=?, productivity=?, details=?,
                    cargo_type=?, bl=?, producto=?, unidades=?, tons=?, ubic_codigo=?,
                    coord_entrante=?, coord_saliente=?
              WHERE id=?"
        );
        $s->execute([
            $r['ubicacion_tipo'], $r['ubicacion'], $r['nave_id'], $r['nave_patio'], $r['actividad_id'],
            $r['estado_pos'], $r['status_act'], $r['planned'], $r['executed'],
            $r['directa'], $r['interna'], $r['productivity'], $r['details'],
            $r['cargo_type'], $r['bl'], $r['producto'], $r['unidades'],
            $r['tons'], $r['ubic_codigo'], $r['coord_entrante'], $r['coord_saliente'],
            $id,
        ]);
        if ($r['ubicacion_tipo'] === 'YARD') tm_propagar_planned_yard($pdo, $r);
        $reg = tm_obtener_registro($pdo, $id);
        tm_sync_nave_berth($pdo, $reg, $data);
        tm_sync_despacho_cementero($pdo, $reg, $registradoPor);
        tm_sync_despacho_interna($pdo, $reg, $registradoPor);
        $prev = tm_executed_previo($pdo, $reg);
        return json_encode(['success'=>true,'data'=>tm_con_resumen($reg, $prev),'_fallback'=>true]);
    }

    // ── DELETE /tallyman/registros/{id} ──────────────────────────────────────
    if (preg_match('#^tallyman/registros/(\d+)$#', $path, $m) && $method === 'DELETE') {
        $pdo->prepare("DELETE FROM tallyman_registros WHERE id=?")->execute([(int)$m[1]]);
        return json_encode(['success'=>true,'_fallback'=>true]);
    }

    // ── POST /tallyman/incidencias ───────────────────────────────────────────
    if ($path === 'tallyman/incidencias' && $method === 'POST') {
        $fecha = trim($data['fecha_turno'] ?? '');
        $turno = trim($data['turno'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !$turno) {
            http_response_code(400);
            return json_encode(['success'=>false,'error'=>'fecha_turno y turno son obligatorios.']);
        }
        $hubo   = !empty($data['hubo']) ? 1 : 0;
        $detalle = trim($data['detalle'] ?? '') ?: null;
        $pdo->prepare("DELETE FROM tallyman_incidencias WHERE fecha_turno=? AND turno=?")->execute([$fecha, $turno]);
        $s = $pdo->prepare("INSERT INTO tallyman_incidencias (fecha_turno, turno, hubo, detalle, registrado_por) VALUES (?,?,?,?,?)");
        $s->execute([$fecha, $turno, $hubo, $detalle, $registradoPor]);
        $row = $pdo->prepare("SELECT * FROM tallyman_incidencias WHERE id=?")->execute([(int)$pdo->lastInsertId()]);
        $stmt = $pdo->prepare("SELECT * FROM tallyman_incidencias WHERE id=?");
        $stmt->execute([(int)$pdo->lastInsertId() ?: $pdo->lastInsertId()]);
        // Re-fetch via fecha+turno (más fiable tras DELETE+INSERT)
        $stmt2 = $pdo->prepare("SELECT * FROM tallyman_incidencias WHERE fecha_turno=? AND turno=? LIMIT 1");
        $stmt2->execute([$fecha, $turno]);
        $row = $stmt2->fetch() ?: null;
        if ($row) $row['hubo'] = (bool)$row['hubo'];
        http_response_code(201);
        return json_encode(['success'=>true,'data'=>$row,'_fallback'=>true]);
    }

    // ── PUT /naves/{id} ─────────────────────────────────────────────────────
    if (preg_match('#^naves/(\d+)$#', $path, $m) && $method === 'PUT') {
        $id     = (int)$m[1];
        $nombre = trim($data['nombre'] ?? '');
        $tipoId = isset($data['tipo_nave_id']) ? (int)$data['tipo_nave_id'] : null;
        if (!$nombre || !$tipoId) {
            http_response_code(400);
            return json_encode(['success' => false, 'error' => 'nombre y tipo_nave_id son obligatorios.']);
        }
        $s = $pdo->prepare(
            "UPDATE naves SET nombre=?, muelle=?, tipo_nave_id=?, actividad_id=?,
                              eta=?, etb=?, etd=?, estado=?, updated_at=NOW()
              WHERE id=?"
        );
        $newMuelle = isset($data['muelle']) && $data['muelle'] !== '' ? $data['muelle'] : null;
        $s->execute([
            $nombre,
            $newMuelle,
            $tipoId,
            isset($data['actividad_id']) && $data['actividad_id'] ? (int)$data['actividad_id'] : null,
            $data['eta'] ?? null,
            $data['etb'] ?? null,
            $data['etd'] ?? null,
            $data['estado'] ?? 'Programada',
            $id,
        ]);
        if ($newMuelle) {
            $pdo->prepare("UPDATE tallyman_registros SET ubicacion=? WHERE nave_id=? AND ubicacion_tipo='BERTH'")
                ->execute([$newMuelle, $id]);
        }
        $COLS = "n.id, n.nombre, n.muelle, n.tipo_nave_id, t.nombre AS tipo_nave,
                 n.actividad_id, ta.nombre AS actividad,
                 n.eta, n.etb, n.etd, n.estado, n.datos_adicionales, n.created_at, n.updated_at";
        $nave = fetch_nave_by_id($pdo, $COLS, $id);
        if (!$nave) { http_response_code(404); return json_encode(['success' => false, 'error' => 'Nave no encontrada.']); }
        return json_encode(['success' => true, 'data' => $nave, '_fallback' => true]);
    }

    // ── PUT /naves/{id}/datos ────────────────────────────────────────────────
    if (preg_match('#^naves/(\d+)/datos$#', $path, $m) && $method === 'PUT') {
        $id    = (int)$m[1];
        $datos = $data['datos_adicionales'] ?? null;
        if ($datos === null) {
            http_response_code(400);
            return json_encode(['success' => false, 'error' => 'datos_adicionales es obligatorio.']);
        }
        $s = $pdo->prepare("UPDATE naves SET datos_adicionales=?, updated_at=NOW() WHERE id=?");
        $s->execute([json_encode($datos), $id]);
        $COLS = "n.id, n.nombre, n.muelle, n.tipo_nave_id, t.nombre AS tipo_nave,
                 n.actividad_id, ta.nombre AS actividad,
                 n.eta, n.etb, n.etd, n.estado, n.datos_adicionales, n.created_at, n.updated_at";
        $nave = fetch_nave_by_id($pdo, $COLS, $id);
        if (!$nave) { http_response_code(404); return json_encode(['success' => false, 'error' => 'Nave no encontrada.']); }
        return json_encode(['success' => true, 'data' => $nave, '_fallback' => true]);
    }

    // ── DELETE /naves/{id} ───────────────────────────────────────────────────
    if (preg_match('#^naves/(\d+)$#', $path, $m) && $method === 'DELETE') {
        $id = (int)$m[1];
        $pdo->prepare("DELETE FROM avances_nave WHERE nave_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM naves WHERE id=?")->execute([$id]);
        return json_encode(['success' => true, '_fallback' => true]);
    }

    // ── Ruta de escritura no implementada ────────────────────────────────────
    http_response_code(502);
    return json_encode([
        'success' => false,
        'error'   => 'La API de Operaciones no está disponible (¿corriendo en :4000?). '
                   . 'Esta operación de escritura no está disponible sin el servidor Node.',
    ]);
}

// ── Helpers de parse y obtención para fallback de escritura ─────────────────
function tm_parse_body(array $d): array
{
    $fecha = trim($d['fecha_turno'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) return ['error' => 'fecha_turno inválida.'];
    $turno = trim($d['turno'] ?? '');
    if (!$turno) return ['error' => 'turno es obligatorio.'];
    $tipo = strtoupper(trim($d['ubicacion_tipo'] ?? ''));
    if (!in_array($tipo, ['BERTH','YARD'])) return ['error' => 'ubicacion_tipo inválido.'];
    $ubic = trim($d['ubicacion'] ?? '');
    if (!$ubic) return ['error' => 'ubicacion es obligatoria.'];
    $actId = (int)($d['actividad_id'] ?? 0);
    if ($actId <= 0) return ['error' => 'actividad_id inválido.'];
    $executed = $d['executed'] ?? null;
    if ($executed === null || $executed === '') return ['error' => 'executed es obligatorio.'];

    $n = function($v) { if ($v === null || $v === '') return null; $n = (float)$v; return $n >= 0 ? $n : null; };
    $i = function($v) { if ($v === null || $v === '') return null; $n = (int)$v; return $n > 0 ? $n : null; };
    $s = function($v) { $t = trim((string)($v ?? '')); return $t === '' ? null : $t; };

    // En muelle, al iniciar una nave el Planned es obligatorio.
    $statusAct = trim($d['status_act'] ?? 'Inicio') ?: 'Inicio';
    $planned   = $n($d['planned'] ?? null);
    if ($tipo === 'BERTH' && $statusAct === 'Inicio' && ($planned === null || $planned <= 0)) {
        return ['error' => 'El Planned es obligatorio cuando el status es Inicio.'];
    }

    return [
        'fecha_turno'    => $fecha,
        'turno'          => $turno,
        'ubicacion_tipo' => $tipo,
        'ubicacion'      => $ubic,
        'nave_id'        => $i($d['nave_id'] ?? null),
        'nave_patio'     => $s($d['nave_patio'] ?? null),
        'actividad_id'   => $actId,
        'estado_pos'     => strtoupper(trim($d['estado_pos'] ?? 'ACTIVE')) ?: 'ACTIVE',
        'status_act'     => $statusAct,
        'planned'        => $planned,
        'executed'       => (float)$executed,
        'directa'        => $n($d['directa'] ?? null),
        'interna'        => $n($d['interna'] ?? null),
        'productivity'   => $n($d['productivity'] ?? null),
        'details'        => $s($d['details'] ?? null),
        'cargo_type'     => $s($d['cargo_type'] ?? null),
        'bl'             => $s($d['bl'] ?? null),
        'producto'       => $s($d['producto'] ?? null),
        'unidades'       => $n($d['unidades'] ?? null),
        'tons'           => $n($d['tons'] ?? null),
        'ubic_codigo'    => $s($d['ubic_codigo'] ?? null),
        'coord_entrante' => $s($d['coord_entrante'] ?? null),
        'coord_saliente' => $s($d['coord_saliente'] ?? null),
    ];
}

function tm_obtener_registro(PDO $pdo, int $id): array
{
    $s = $pdo->prepare(
        "SELECT r.*, a.nombre AS actividad, n.nombre AS nave,
                CASE WHEN r.ubicacion_tipo = 'YARD' AND r.nave_id IS NOT NULL AND r.planned IS NULL
                     THEN COALESCE((SELECT SUM(tr2.interna)
                                      FROM tallyman_registros tr2
                                     WHERE tr2.nave_id = r.nave_id
                                       AND tr2.ubicacion_tipo = 'BERTH'
                                       AND tr2.interna > 0), 0)
                     ELSE r.planned
                END AS planned
           FROM tallyman_registros r
           JOIN tallyman_actividades a ON a.id = r.actividad_id
           LEFT JOIN naves n ON n.id = r.nave_id
          WHERE r.id = ?"
    );
    $s->execute([$id]);
    return $s->fetch() ?: [];
}

// Sincroniza la nave de Operaciones con el estado del registro de muelle:
//   · Inicio    → ETB/ATB = fecha operativa, estado = En Operaciones (+ planned).
//   · Culminado → ETD/ATD = fecha operativa, estado = Finalizada.
// La fecha operativa viene de op_fecha (fecha del turno + hora); si falta, ahora.
// Solo aplica a registros BERTH con nave real. Patio (YARD) no afecta naves.
function tm_sync_nave_berth(PDO $pdo, array $reg, array $data): void
{
    if (($reg['ubicacion_tipo'] ?? '') !== 'BERTH') return;
    $naveId = isset($reg['nave_id']) ? (int)$reg['nave_id'] : 0;
    if ($naveId <= 0) return;
    $status = $reg['status_act'] ?? '';

    $opFecha = trim($data['op_fecha'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $opFecha)) {
        $opFecha = date('Y-m-d H:i:s');
    }

    if ($status === 'Inicio') {
        // El atraque se refleja en `eta` (columna que el formulario de Operaciones usa
        // para "ETB/ATB") y en `etb` por consistencia con la vista/Gantt (etb || eta).
        $pdo->prepare("UPDATE naves SET eta=?, etb=?, estado='En Operaciones', updated_at=NOW() WHERE id=?")
            ->execute([$opFecha, $opFecha, $naveId]);
        if (isset($reg['planned']) && $reg['planned'] !== null && (float)$reg['planned'] > 0) {
            $clave = tm_planned_key_por_tipo(tm_tipo_nave($pdo, $naveId));
            tm_merge_datos_nave($pdo, $naveId, [$clave => (float)$reg['planned']]);
        }
    } elseif ($status === 'Culminado') {
        $pdo->prepare("UPDATE naves SET etd=?, estado='Finalizada', updated_at=NOW() WHERE id=?")
            ->execute([$opFecha, $naveId]);
    }
}

// Mapa: tipo de nave (en minúsculas) → actividad de PATIO de despacho que se
// autogenera a partir de la DESCARGA INTERNA del muelle. DEBE coincidir con
// DISPATCH_INTERNA_POR_TIPO de la API Node (operaciones-api/.../tallyman.controller.js).
function tm_dispatch_interna_por_tipo(): array
{
    return [
        'carga general' => 'General Cargo Dispatch (Despacho de Carga General)',
        'granelera'     => 'Corn Dispatch (Despacho de Maíz)',
        // Ro-Ro: el "Tránsito Aduanero" se registra en la descarga interna del muelle
        // y genera automáticamente la actividad de patio "Car Dispatch" (executed = 0).
        'ro-ro'         => 'Car Dispatch (Despacho de Vehículos)',
    ];
}

// Clave de datos_adicionales donde se refleja el planned según el tipo de nave, para
// que Operaciones lo muestre con la unidad correcta. DEBE coincidir con la API Node.
//   · Ro-Ro → "vehiculos"   · Containera → "teus"   · resto → "cantidad_total" (TM)
function tm_planned_key_por_tipo(string $tipo): string
{
    $map = ['ro-ro' => 'vehiculos', 'containera' => 'teus', 'portacontenedores' => 'teus'];
    return $map[$tipo] ?? 'cantidad_total';
}

// Nombre del tipo de nave (en minúsculas) de una nave dada.
function tm_tipo_nave(PDO $pdo, int $naveId): string
{
    $st = $pdo->prepare("SELECT t.nombre FROM naves n JOIN tipos_nave t ON t.id = n.tipo_nave_id WHERE n.id = ?");
    $st->execute([$naveId]);
    return strtolower(trim((string)($st->fetchColumn() ?: '')));
}

// Autogenera la actividad de PATIO (despacho) a partir de la descarga interna de
// un registro de muelle. Réplica de sincronizarDespachoInterna() de la API Node,
// para que la autogeneración funcione también cuando el Node API está caído y la
// escritura la atiende este fallback PHP. Reglas:
//   · Solo BERTH con nave real cuyo tipo esté mapeado y con interna > 0.
//   · Un despacho por nave y turno: si ya existe, se respeta (no se duplica).
//   · planned = NULL → se calcula en vivo como SUM(interna) de los BERTH de la nave.
//   · executed = 0 → el despacho real del patio lo ingresa el operador.
//   · Status: "Inicio" si no hay ciclo previo o el último fue Culminado; si no, "En Proceso".
function tm_sync_despacho_interna(PDO $pdo, array $reg, string $registradoPor): void
{
    if (($reg['ubicacion_tipo'] ?? '') !== 'BERTH') return;
    $naveId = isset($reg['nave_id']) ? (int)$reg['nave_id'] : 0;
    if ($naveId <= 0) return;
    // La actividad "Cement Big Bags Loading/Discharge" es estilo Cementero: su interna es
    // avance de muelle (a piso) y su directa genera el patio; no lleva despacho por interna.
    $actNombre = strtolower(trim((string)($reg['actividad'] ?? '')));
    if (strpos($actNombre, 'cement big bags loading') === 0) return;
    $interna = isset($reg['interna']) && $reg['interna'] !== null ? (float)$reg['interna'] : 0;
    if ($interna <= 0) return; // sin descarga interna → no se genera nada

    // Tipo de nave (desde Operaciones), normalizado a minúsculas.
    $st = $pdo->prepare("SELECT t.nombre FROM naves n JOIN tipos_nave t ON t.id = n.tipo_nave_id WHERE n.id = ?");
    $st->execute([$naveId]);
    $tipo = strtolower(trim((string)($st->fetchColumn() ?: '')));

    $mapa = tm_dispatch_interna_por_tipo();
    if (!isset($mapa[$tipo])) return; // tipo de nave sin despacho de patio autogenerado
    $actNombre = $mapa[$tipo];

    // Id de la actividad de despacho por nombre exacto (activa).
    $sa = $pdo->prepare("SELECT id FROM tallyman_actividades WHERE nombre = ? AND activo = 1 LIMIT 1");
    $sa->execute([$actNombre]);
    $dispatchActId = $sa->fetchColumn();
    if (!$dispatchActId) return; // catálogo sin esa actividad → no se genera nada
    $dispatchActId = (int)$dispatchActId;

    // ¿Ya existe el despacho de patio de este turno (nave+actividad+YARD)? → respetar.
    $sb = $pdo->prepare(
        "SELECT id FROM tallyman_registros
          WHERE fecha_turno = ? AND turno = ? AND ubicacion_tipo = 'YARD'
            AND (nave_id <=> ?) AND actividad_id = ?
          ORDER BY id DESC LIMIT 1"
    );
    $sb->execute([$reg['fecha_turno'], $reg['turno'], $naveId, $dispatchActId]);
    if ($sb->fetchColumn()) return; // ya existe → no se duplica

    // Status según el último registro YARD de esta nave+actividad.
    $su = $pdo->prepare(
        "SELECT status_act FROM tallyman_registros
          WHERE actividad_id = ? AND (nave_id <=> ?)
          ORDER BY id DESC LIMIT 1"
    );
    $su->execute([$dispatchActId, $naveId]);
    $ultimo = $su->fetchColumn();
    $status = (!$ultimo || $ultimo === 'Culminado') ? 'Inicio' : 'En Proceso';

    $ins = $pdo->prepare(
        "INSERT INTO tallyman_registros
           (fecha_turno, turno, ubicacion_tipo, ubicacion, nave_id, nave_patio, actividad_id,
            estado_pos, status_act, planned, executed, details, registrado_por)
         VALUES (?, ?, 'YARD', 'Yard', ?, NULL, ?, 'ACTIVE', ?, NULL, 0, ?, ?)"
    );
    $ins->execute([
        $reg['fecha_turno'], $reg['turno'], $naveId, $dispatchActId, $status,
        'Generado automáticamente desde muelle (descarga interna).', $registradoPor,
    ]);
}

// Genera/actualiza la actividad de PATIO "Big Bags Dispatch" para naves Cementero,
// a partir del DESPACHO INDIRECTO (columna `directa`) del registro de muelle. Réplica
// de sincronizarDespachoCementero() de la API Node (para el fallback sin Node). A
// diferencia del despacho interna:
//   · planned del patio = planned del muelle (cantidad total de la nave).
//   · executed del patio = despacho indirecto (se llena solo, no es 0).
//   · Upsert por nave y turno: si ya existe, se actualiza (no se duplica al editar);
//     si en edición se quitó el despacho, queda en 0 (no se borra).
//   · El planned se propaga a todos los turnos de esa nave+actividad (consistencia).
function tm_sync_despacho_cementero(PDO $pdo, array $reg, string $registradoPor): void
{
    if (($reg['ubicacion_tipo'] ?? '') !== 'BERTH') return;
    $naveId = isset($reg['nave_id']) ? (int)$reg['nave_id'] : 0;
    if ($naveId <= 0) return;

    // Disparadores del despacho a patio:
    //  · Actividad "Cement Big Bags Loading/Discharge" (nuevo criterio por actividad).
    //  · Tipo de nave "Cementero" (legado, se mantiene como estaba).
    $actNombre = strtolower(trim((string)($reg['actividad'] ?? '')));
    $esCementBigBags = strpos($actNombre, 'cement big bags loading') === 0;

    $st = $pdo->prepare("SELECT t.nombre FROM naves n JOIN tipos_nave t ON t.id = n.tipo_nave_id WHERE n.id = ?");
    $st->execute([$naveId]);
    $tipo = strtolower(trim((string)($st->fetchColumn() ?: '')));
    $esTipoCementero = ($tipo === 'cementero');

    if (!$esCementBigBags && !$esTipoCementero) return;

    // Actividad de patio destino: la actividad Cement Big Bags genera "Cement Big Bags
    // Dispatch"; el tipo de nave Cementero (legado) sigue generando "Big Bags Dispatch".
    $dispatchNombre = $esCementBigBags
        ? 'Cement Big Bags Dispatch (Despacho de Cemento en Big Bags)'
        : 'Big Bags Dispatch (Despacho de Big Bags)';
    $sa = $pdo->prepare("SELECT id FROM tallyman_actividades WHERE nombre = ? AND activo = 1 LIMIT 1");
    $sa->execute([$dispatchNombre]);
    $dispatchActId = $sa->fetchColumn();
    if (!$dispatchActId) return; // catálogo sin esa actividad → no se genera nada
    $dispatchActId = (int)$dispatchActId;

    $despacho = isset($reg['directa']) && $reg['directa'] !== null ? (float)$reg['directa'] : 0;
    $planned  = isset($reg['planned']) && $reg['planned'] !== null ? (float)$reg['planned'] : null;

    // ¿Ya existe el despacho de patio de este turno (nave+actividad+YARD)?
    $sb = $pdo->prepare(
        "SELECT id FROM tallyman_registros
          WHERE fecha_turno = ? AND turno = ? AND ubicacion_tipo = 'YARD'
            AND (nave_id <=> ?) AND actividad_id = ?
          ORDER BY id DESC LIMIT 1"
    );
    $sb->execute([$reg['fecha_turno'], $reg['turno'], $naveId, $dispatchActId]);
    $existenteId = $sb->fetchColumn();
    $existenteId = $existenteId ? (int)$existenteId : 0;

    // ── Caso Cement Big Bags (por actividad): contenedor de patio ──
    // executed = 0 (lo llena el tallyman) y planned = lo que resta por despachar =
    // planned de muelle − acumulado de despacho indirecto (directa) de esa nave+actividad.
    if ($esCementBigBags) {
        $sd = $pdo->prepare(
            "SELECT COALESCE(SUM(directa),0) FROM tallyman_registros
              WHERE ubicacion_tipo='BERTH' AND actividad_id = ? AND (nave_id <=> ?)"
        );
        $sd->execute([(int)$reg['actividad_id'], $naveId]);
        $sumaDirecta = (float)$sd->fetchColumn();
        $plannedPatio = $planned !== null ? max($planned - $sumaDirecta, 0) : null;
        if ($existenteId) {
            // Solo actualizar el planned (no pisar el executed que ingresó el operador).
            if ($plannedPatio !== null) {
                $pdo->prepare("UPDATE tallyman_registros SET planned=? WHERE ubicacion_tipo='YARD' AND actividad_id=? AND nave_id=?")
                    ->execute([$plannedPatio, $dispatchActId, $naveId]);
            }
        } else {
            $su = $pdo->prepare(
                "SELECT status_act FROM tallyman_registros
                  WHERE actividad_id = ? AND (nave_id <=> ?)
                  ORDER BY id DESC LIMIT 1"
            );
            $su->execute([$dispatchActId, $naveId]);
            $ultimo = $su->fetchColumn();
            $status = (!$ultimo || $ultimo === 'Culminado') ? 'Inicio' : 'En Proceso';
            $pdo->prepare(
                "INSERT INTO tallyman_registros
                   (fecha_turno, turno, ubicacion_tipo, ubicacion, nave_id, nave_patio, actividad_id,
                    estado_pos, status_act, planned, executed, details, registrado_por)
                 VALUES (?, ?, 'YARD', 'Yard', ?, NULL, ?, 'ACTIVE', ?, ?, ?, ?, ?)"
            )->execute([
                $reg['fecha_turno'], $reg['turno'], $naveId, $dispatchActId, $status,
                $plannedPatio, 0,
                'Generado automáticamente desde muelle (despacho indirecto · Cement Big Bags).',
                $registradoPor,
            ]);
        }
        return;
    }

    // ── Caso Cementero legado (por tipo de nave): executed = despacho directo ──
    if ($despacho > 0) {
        if ($existenteId) {
            $pdo->prepare("UPDATE tallyman_registros SET executed = ?, planned = ? WHERE id = ?")
                ->execute([$despacho, $planned, $existenteId]);
        } else {
            // Status según el último registro YARD de esta nave+actividad.
            $su = $pdo->prepare(
                "SELECT status_act FROM tallyman_registros
                  WHERE actividad_id = ? AND (nave_id <=> ?)
                  ORDER BY id DESC LIMIT 1"
            );
            $su->execute([$dispatchActId, $naveId]);
            $ultimo = $su->fetchColumn();
            $status = (!$ultimo || $ultimo === 'Culminado') ? 'Inicio' : 'En Proceso';
            $pdo->prepare(
                "INSERT INTO tallyman_registros
                   (fecha_turno, turno, ubicacion_tipo, ubicacion, nave_id, nave_patio, actividad_id,
                    estado_pos, status_act, planned, executed, details, registrado_por)
                 VALUES (?, ?, 'YARD', 'Yard', ?, NULL, ?, 'ACTIVE', ?, ?, ?, ?, ?)"
            )->execute([
                $reg['fecha_turno'], $reg['turno'], $naveId, $dispatchActId, $status,
                $planned, $despacho,
                'Generado automáticamente desde muelle (descarga directa).',
                $registradoPor,
            ]);
        }
    } elseif ($existenteId) {
        // En edición se quitó el despacho: dejar el patio autogenerado en 0 (no se borra).
        $pdo->prepare("UPDATE tallyman_registros SET executed = 0, planned = ? WHERE id = ?")
            ->execute([$planned, $existenteId]);
    }

    // Propaga el planned a todos los turnos de esa nave+actividad (consistencia).
    if ($planned !== null) {
        $pdo->prepare("UPDATE tallyman_registros SET planned=? WHERE ubicacion_tipo='YARD' AND actividad_id=? AND nave_id=?")
            ->execute([$planned, $dispatchActId, $naveId]);
    }
}

// Propaga el planned (acumulado de patio) editado a TODOS los registros YARD que
// coincidan en nave (real o de texto) + actividad, para que el acumulado sea
// consistente entre turnos. Solo aplica si hay un planned definido.
function tm_propagar_planned_yard(PDO $pdo, array $r): void
{
    if (($r['ubicacion_tipo'] ?? '') !== 'YARD') return;
    $planned = $r['planned'] ?? null;
    if ($planned === null) return;
    $actId = (int)$r['actividad_id'];
    if (isset($r['nave_id']) && $r['nave_id'] !== null) {
        $pdo->prepare("UPDATE tallyman_registros SET planned=? WHERE ubicacion_tipo='YARD' AND actividad_id=? AND nave_id=?")
            ->execute([$planned, $actId, (int)$r['nave_id']]);
    } elseif (!empty($r['nave_patio'])) {
        $pdo->prepare("UPDATE tallyman_registros SET planned=? WHERE ubicacion_tipo='YARD' AND actividad_id=? AND nave_patio=?")
            ->execute([$planned, $actId, $r['nave_patio']]);
    }
}

// Mezcla $patch dentro de datos_adicionales (preserva las demás claves).
function tm_merge_datos_nave(PDO $pdo, int $naveId, array $patch): void
{
    $stmt = $pdo->prepare("SELECT datos_adicionales FROM naves WHERE id=?");
    $stmt->execute([$naveId]);
    $row = $stmt->fetch();
    $datos = [];
    if ($row && !empty($row['datos_adicionales'])) {
        $dec = json_decode($row['datos_adicionales'], true);
        if (is_array($dec)) $datos = $dec;
    }
    $datos = array_merge($datos, $patch);
    $pdo->prepare("UPDATE naves SET datos_adicionales=?, updated_at=NOW() WHERE id=?")
        ->execute([json_encode($datos), $naveId]);
}

// ── Fallback PHP: lectura directa a MySQL db "operaciones" ──────────────────
// Cubre GET /tipos-nave, GET /naves, GET /naves/{id}, GET /naves/{id}/historial
function fallback_get(string $path, array $params): string
{
    try {
        $pdo = new PDO(
            'mysql:host=' . OPER_HOST . ';port=3306;dbname=' . OPER_DB . ';charset=utf8mb4',
            OPER_USER, OPER_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        http_response_code(503);
        return json_encode(['success' => false, 'error' => 'No se pudo conectar a la base de datos: ' . $e->getMessage()]);
    }

    // GET /tipos-nave
    if ($path === 'tipos-nave') {
        $rows = $pdo->query("SELECT id, nombre FROM tipos_nave WHERE activo = 1 ORDER BY nombre")->fetchAll();
        return json_encode(['success' => true, 'count' => count($rows), 'data' => $rows, '_fallback' => true]);
    }

    $COLS = "n.id, n.nombre, n.muelle, n.tipo_nave_id, t.nombre AS tipo_nave,
             n.actividad_id, ta.nombre AS actividad,
             n.eta, n.etb, n.etd, n.estado, n.datos_adicionales, n.created_at, n.updated_at";

    // GET /naves/{id}/historial
    if (preg_match('#^naves/(\d+)/historial$#', $path, $m)) {
        $id = (int)$m[1];
        $nave = fetch_nave_by_id($pdo, $COLS, $id);
        if (!$nave) { http_response_code(404); return json_encode(['success' => false, 'error' => 'Nave no encontrada.']); }

        $avs = $pdo->prepare(
            "SELECT id, nave_id, fecha_turno, turno, directa_tm, indirecta_tm, despacho_tm,
                    bodegas, descripcion_avance, registrado_por, fecha_registro
               FROM avances_nave WHERE nave_id = ?
              ORDER BY fecha_turno ASC, (turno = 'Noche'), fecha_registro ASC, id ASC"
        );
        $avs->execute([$id]);
        $avances = array_map(fn($r) => decode_json_col($r, 'bodegas'), $avs->fetchAll());

        $directa = $indirecta = $despacho = $reportes = 0;
        $bodegasTm = 0;
        foreach ($avances as $a) {
            $directa   += (float)($a['directa_tm']   ?? 0);
            $indirecta += (float)($a['indirecta_tm'] ?? 0);
            $despacho  += (float)($a['despacho_tm']  ?? 0);
            $reportes++;
            if (is_array($a['bodegas'])) foreach ($a['bodegas'] as $b) $bodegasTm += (float)($b['tm'] ?? 0);
        }
        $datos  = $nave['datos_adicionales'] ?? [];
        $total  = (float)($datos['cantidad_total'] ?? 0);
        $movido = $directa + $indirecta;
        $resumen = [
            'total'      => $total,
            'directa'    => $directa,
            'indirecta'  => $indirecta,
            'despacho'   => $despacho,
            'movido'     => $movido,
            'saldo'      => $total > 0 ? max($total - $movido, 0) : 0,
            'porcentaje' => $total > 0 ? min(round($movido / $total * 1000) / 10, 100) : null,
            'reportes'   => $reportes,
            'bodegas_tm' => $bodegasTm,
        ];
        return json_encode(['success' => true, 'data' => ['nave' => $nave, 'avances' => $avances, 'resumen' => $resumen], '_fallback' => true]);
    }

    // GET /naves/{id}
    if (preg_match('#^naves/(\d+)$#', $path, $m)) {
        $nave = fetch_nave_by_id($pdo, $COLS, (int)$m[1]);
        if (!$nave) { http_response_code(404); return json_encode(['success' => false, 'error' => 'Nave no encontrada.']); }
        return json_encode(['success' => true, 'data' => ['nave' => $nave, 'campos' => []], '_fallback' => true]);
    }

    // GET /naves
    if ($path === 'naves') {
        $estado = $params['estado'] ?? '';
        if ($estado !== '') {
            $stmt = $pdo->prepare("SELECT $COLS
                FROM naves n
                JOIN tipos_nave t ON t.id = n.tipo_nave_id
                LEFT JOIN tallyman_actividades ta ON ta.id = n.actividad_id
                WHERE n.estado = ?
                ORDER BY (n.eta IS NULL), n.eta ASC, n.id DESC");
            $stmt->execute([$estado]);
        } else {
            $stmt = $pdo->query("SELECT $COLS
                FROM naves n
                JOIN tipos_nave t ON t.id = n.tipo_nave_id
                LEFT JOIN tallyman_actividades ta ON ta.id = n.actividad_id
                WHERE n.estado IN ('Programada','En Puerto','En Operaciones')
                ORDER BY (n.eta IS NULL), n.eta ASC, n.id DESC");
        }
        $rows = array_map(fn($r) => decode_json_col($r, 'datos_adicionales'), $stmt->fetchAll());
        return json_encode(['success' => true, 'count' => count($rows), 'data' => $rows, '_fallback' => true]);
    }

    // ── Tallyman ────────────────────────────────────────────────────────────

    // GET /tallyman/actividades
    if ($path === 'tallyman/actividades') {
        $rows = $pdo->query(
            "SELECT id, nombre FROM tallyman_actividades WHERE activo = 1 ORDER BY orden, nombre"
        )->fetchAll();
        return json_encode(['success' => true, 'count' => count($rows), 'data' => $rows, '_fallback' => true]);
    }

    // GET /tallyman/naves-patio
    if ($path === 'tallyman/naves-patio') {
        $naves = $pdo->query(
            "SELECT n.id, n.nombre,
                    COALESCE(SUM(tr.interna), 0) AS planned_patio,
                    ult.actividad_id AS actividad_id_sugerida,
                    ult.planned     AS planned_sugerido
               FROM naves n
               JOIN tallyman_registros tr ON tr.nave_id = n.id AND tr.interna > 0
               LEFT JOIN tallyman_registros ult
                      ON ult.id = (
                           SELECT MAX(id) FROM tallyman_registros
                            WHERE ubicacion_tipo = 'YARD'
                              AND status_act <> 'Culminado'
                              AND nave_id = n.id
                         )
              GROUP BY n.id, n.nombre, ult.actividad_id, ult.planned
              ORDER BY n.nombre"
        )->fetchAll();
        $pNombres = $pdo->query(
            "SELECT DISTINCT tr.nave_patio AS nombre,
                    (SELECT tr2.actividad_id FROM tallyman_registros tr2
                      WHERE tr2.nave_patio = tr.nave_patio AND tr2.status_act <> 'Culminado'
                      ORDER BY tr2.id DESC LIMIT 1) AS actividad_id_sugerida,
                    (SELECT tr2.planned FROM tallyman_registros tr2
                      WHERE tr2.nave_patio = tr.nave_patio AND tr2.status_act <> 'Culminado'
                      ORDER BY tr2.id DESC LIMIT 1) AS planned_sugerido
               FROM tallyman_registros tr
              WHERE tr.nave_patio IS NOT NULL AND tr.nave_patio <> ''
                AND tr.id = (SELECT MAX(m.id) FROM tallyman_registros m
                              WHERE m.nave_patio = tr.nave_patio
                                AND m.actividad_id = tr.actividad_id)
                AND tr.status_act <> 'Culminado'
              ORDER BY tr.nave_patio"
        )->fetchAll();
        return json_encode(['success' => true, 'data' => ['naves' => $naves, 'nombres_patio' => array_values($pNombres)], '_fallback' => true]);
    }

    // GET /tallyman/registros-rango?desde=&hasta=   (histórico, solo Admin)
    if ($path === 'tallyman/registros-rango') {
        $desde = $params['desde'] ?? '';
        $hasta = $params['hasta'] ?? '';
        $cond = []; $args = [];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) { $cond[] = 'r.fecha_turno >= ?'; $args[] = $desde; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) { $cond[] = 'r.fecha_turno <= ?'; $args[] = $hasta; }
        $where = $cond ? ('WHERE ' . implode(' AND ', $cond)) : '';
        $stmt = $pdo->prepare(
            "SELECT r.*,
                    a.nombre AS actividad,
                    n.nombre AS nave,
                    CASE WHEN r.ubicacion_tipo = 'YARD' AND r.nave_id IS NOT NULL AND r.planned IS NULL
                         THEN COALESCE((SELECT SUM(tr2.interna)
                                          FROM tallyman_registros tr2
                                         WHERE tr2.nave_id = r.nave_id
                                           AND tr2.ubicacion_tipo = 'BERTH'
                                           AND tr2.interna > 0), 0)
                         ELSE r.planned
                    END AS planned
               FROM tallyman_registros r
               JOIN tallyman_actividades a ON a.id = r.actividad_id
               LEFT JOIN naves n ON n.id = r.nave_id
               $where
              ORDER BY r.fecha_turno DESC, r.turno, r.ubicacion_tipo, r.ubicacion, r.id"
        );
        $stmt->execute($args);
        $regs = $stmt->fetchAll();
        $out = [];
        foreach ($regs as $r) {
            $prev = tm_executed_previo($pdo, $r);
            $out[] = tm_con_resumen($r, $prev);
        }
        return json_encode(['success' => true, 'count' => count($out), 'data' => $out, '_fallback' => true]);
    }

    // GET /tallyman/registros?fecha=&turno=
    if ($path === 'tallyman/registros') {
        $fecha = $params['fecha'] ?? '';
        $turno = $params['turno'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $turno === '') {
            http_response_code(400);
            return json_encode(['success' => false, 'error' => 'fecha y turno son obligatorios.']);
        }
        $stmt = $pdo->prepare(
            "SELECT r.*,
                    a.nombre AS actividad,
                    n.nombre AS nave,
                    CASE WHEN r.ubicacion_tipo = 'YARD' AND r.nave_id IS NOT NULL AND r.planned IS NULL
                         THEN COALESCE((SELECT SUM(tr2.interna)
                                          FROM tallyman_registros tr2
                                         WHERE tr2.nave_id = r.nave_id
                                           AND tr2.ubicacion_tipo = 'BERTH'
                                           AND tr2.interna > 0), 0)
                         ELSE r.planned
                    END AS planned
               FROM tallyman_registros r
               JOIN tallyman_actividades a ON a.id = r.actividad_id
               LEFT JOIN naves n ON n.id = r.nave_id
              WHERE r.fecha_turno = ? AND r.turno = ?
              ORDER BY r.ubicacion_tipo, r.ubicacion, r.id"
        );
        $stmt->execute([$fecha, $turno]);
        $regs = $stmt->fetchAll();
        $out = [];
        foreach ($regs as $r) {
            $prev = tm_executed_previo($pdo, $r);
            $out[] = tm_con_resumen($r, $prev);
        }
        return json_encode(['success' => true, 'count' => count($out), 'data' => $out, '_fallback' => true]);
    }

    // GET /tallyman/incidencias?fecha=&turno=
    if ($path === 'tallyman/incidencias') {
        $fecha = $params['fecha'] ?? '';
        $turno = $params['turno'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $turno === '') {
            http_response_code(400);
            return json_encode(['success' => false, 'error' => 'fecha y turno son obligatorios.']);
        }
        $stmt = $pdo->prepare(
            "SELECT * FROM tallyman_incidencias WHERE fecha_turno = ? AND turno = ? LIMIT 1"
        );
        $stmt->execute([$fecha, $turno]);
        $row = $stmt->fetch() ?: null;
        if ($row) $row['hubo'] = (bool)$row['hubo'];
        return json_encode(['success' => true, 'data' => $row, '_fallback' => true]);
    }

    // GET /tallyman/relevo?fecha=&turno=
    if ($path === 'tallyman/relevo') {
        $fecha = $params['fecha'] ?? '';
        $turno = $params['turno'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $turno === '') {
            http_response_code(400);
            return json_encode(['success' => false, 'error' => 'fecha y turno son obligatorios.']);
        }
        $stmt = $pdo->prepare(
            "SELECT r.*,
                    a.nombre AS actividad,
                    n.nombre AS nave,
                    CASE WHEN r.ubicacion_tipo = 'YARD' AND r.nave_id IS NOT NULL AND r.planned IS NULL
                         THEN COALESCE((SELECT SUM(tr2.interna)
                                          FROM tallyman_registros tr2
                                         WHERE tr2.nave_id = r.nave_id
                                           AND tr2.ubicacion_tipo = 'BERTH'
                                           AND tr2.interna > 0), 0)
                         ELSE r.planned
                    END AS planned
               FROM tallyman_registros r
               JOIN tallyman_actividades a ON a.id = r.actividad_id
               LEFT JOIN naves n ON n.id = r.nave_id
              WHERE r.fecha_turno = ? AND r.turno = ?
              ORDER BY r.ubicacion_tipo, r.ubicacion, r.id"
        );
        $stmt->execute([$fecha, $turno]);
        $regs = $stmt->fetchAll();
        $registros = [];
        foreach ($regs as $r) {
            $prev = tm_executed_previo($pdo, $r);
            $registros[] = tm_con_resumen($r, $prev);
        }
        $inc = $pdo->prepare("SELECT * FROM tallyman_incidencias WHERE fecha_turno = ? AND turno = ? LIMIT 1");
        $inc->execute([$fecha, $turno]);
        $incidencia = $inc->fetch() ?: null;

        $totales = tm_calcular_totales($registros);
        // Coordinadores del turno (del sistema principal, si aplica)
        return json_encode(['success' => true, 'data' => [
            'fecha' => $fecha, 'turno' => $turno,
            'registros' => $registros, 'totales' => $totales,
            'incidencia' => $incidencia,
            'coord_saliente' => null, 'coord_entrante' => null,
            '_fallback' => true,
        ]]);
    }

    // GET /tallyman/estado-sugerido?nave_id=&actividad_id=
    if ($path === 'tallyman/estado-sugerido') {
        $actId = (int)($params['actividad_id'] ?? 0);
        if (!$actId) { http_response_code(400); return json_encode(['success' => false, 'error' => 'actividad_id es obligatorio.']); }
        $naveId   = isset($params['nave_id']) && $params['nave_id'] !== '' ? (int)$params['nave_id'] : null;
        $navePatio = $params['nave_patio'] ?? null;
        if ($navePatio !== null && $navePatio !== '') {
            $s = $pdo->prepare("SELECT status_act FROM tallyman_registros WHERE actividad_id = ? AND nave_patio = ? ORDER BY id DESC LIMIT 1");
            $s->execute([$actId, $navePatio]);
        } else {
            $s = $pdo->prepare("SELECT status_act FROM tallyman_registros WHERE actividad_id = ? AND (nave_id <=> ?) ORDER BY id DESC LIMIT 1");
            $s->execute([$actId, $naveId]);
        }
        $ultimo = ($s->fetch() ?: null);
        $ultimoStatus = $ultimo ? $ultimo['status_act'] : null;
        $status = (!$ultimoStatus || $ultimoStatus === 'Culminado') ? 'Inicio' : 'En Proceso';
        // Acumulado previo (executed de turnos anteriores, excluyendo el turno actual)
        // para mostrar el avance del turno anterior y la proyección al ingresar datos.
        $acumulado = 0.0;
        $ubic  = $params['ubicacion'] ?? null;
        $fT    = $params['fecha_turno'] ?? null;
        $tT    = $params['turno'] ?? null;
        if ($fT !== null && $fT !== '' && $tT !== null && $tT !== '') {
            $acumulado = tm_executed_previo($pdo, [
                'ubicacion_tipo' => ($navePatio !== null && $navePatio !== '') ? 'YARD' : 'BERTH',
                'actividad_id'   => $actId,
                'nave_id'        => $naveId,
                'nave_patio'     => $navePatio,
                'ubicacion'      => $ubic,
                'fecha_turno'    => $fT,
                'turno'          => $tT,
            ]);
        }
        return json_encode(['success' => true, 'data' => ['status' => $status, 'ultimo' => $ultimoStatus, 'acumulado' => $acumulado], '_fallback' => true]);
    }

    http_response_code(503);
    return json_encode(['success' => false, 'error' => 'Ruta no disponible en modo fallback (API Node no está corriendo).']);
}

// ── Helpers tallyman ──────────────────────────────────────────────────────────
function tm_executed_previo(PDO $pdo, array $r): float
{
    $f = $r['fecha_turno']; $t = $r['turno']; $aId = $r['actividad_id'];
    if ($r['ubicacion_tipo'] === 'YARD') {
        if ($r['nave_id'] !== null) {
            $s = $pdo->prepare("SELECT COALESCE(SUM(executed),0) AS p FROM tallyman_registros
              WHERE actividad_id=? AND nave_id=? AND ubicacion_tipo='YARD'
                AND (fecha_turno<? OR (fecha_turno=? AND turno<>?))");
            $s->execute([$aId, $r['nave_id'], $f, $f, $t]);
        } elseif (!empty($r['nave_patio'])) {
            $s = $pdo->prepare("SELECT COALESCE(SUM(executed),0) AS p FROM tallyman_registros
              WHERE actividad_id=? AND nave_patio=? AND ubicacion_tipo='YARD'
                AND (fecha_turno<? OR (fecha_turno=? AND turno<>?))");
            $s->execute([$aId, $r['nave_patio'], $f, $f, $t]);
        } else { return 0.0; }
    } else {
        $s = $pdo->prepare("SELECT COALESCE(SUM(executed),0) AS p FROM tallyman_registros
          WHERE actividad_id=? AND ubicacion=? AND (nave_id<=>?)
            AND (fecha_turno<? OR (fecha_turno=? AND turno<>?))");
        $s->execute([$aId, $r['ubicacion'], $r['nave_id'], $f, $f, $t]);
    }
    return (float)($s->fetch()['p'] ?? 0);
}

function tm_con_resumen(array $r, float $prev): array
{
    $planned     = $r['planned'] !== null ? (float)$r['planned'] : null;
    $executed    = (float)$r['executed'];
    $accumulated = $prev + $executed;
    $pending     = $planned !== null ? max($planned - $accumulated, 0) : null;
    $porcentaje  = ($planned !== null && $planned > 0)
        ? min(round($accumulated / $planned * 1000) / 10, 100)
        : null;
    return array_merge($r, [
        'accumulated' => $accumulated,
        'pending'     => $pending,
        'porcentaje'  => $porcentaje,
    ]);
}

function tm_calcular_totales(array $regs): array
{
    $planned = $executed = $pending = $executedConPlan = 0;
    foreach ($regs as $r) {
        $p = $r['planned'] !== null ? (float)$r['planned'] : null;
        $e = (float)$r['executed'];
        $executed += $e;
        if ($p !== null && $p > 0) {
            $planned += $p;
            $executedConPlan += (float)($r['accumulated'] ?? $e);
            $pending += (float)($r['pending'] ?? 0);
        }
    }
    $porcentaje = $planned > 0
        ? min(round($executedConPlan / $planned * 1000) / 10, 100)
        : null;
    return [
        'planned'       => round($planned * 100) / 100,
        'executed'      => round($executed * 100) / 100,
        'pending'       => round($pending * 100) / 100,
        'porcentaje'    => $porcentaje,
        'n_actividades' => count($regs),
    ];
}

function fetch_nave_by_id(PDO $pdo, string $cols, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT $cols
        FROM naves n
        JOIN tipos_nave t ON t.id = n.tipo_nave_id
        LEFT JOIN tallyman_actividades ta ON ta.id = n.actividad_id
        WHERE n.id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ? decode_json_col($row, 'datos_adicionales') : null;
}

function decode_json_col(array $row, string $col): array
{
    if (isset($row[$col]) && is_string($row[$col])) {
        $row[$col] = json_decode($row[$col], true) ?? null;
    }
    return $row;
}
