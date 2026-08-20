<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Turno actual
   Reemplaza el seed hardcoded de js/data-source.js.
   Resuelve/crea el turno vigente (fecha + jornada) y devuelve el contrato
   que espera el módulo, extendido con IDs para persistencia.
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/turno.php');
require_once('../includes/acciones.php');
api_require_login();

header('Content-Type: application/json');

// Selector del tablero: ?jornadaId=N&fecha=YYYY-MM-DD fuerza un turno concreto.
// Compatibilidad: ?jornada=D|N. Sin parámetros, turno vigente por la hora.
$jornadaId  = isset($_GET['jornadaId']) ? (int)$_GET['jornadaId'] : 0;
$fechaParam = isset($_GET['fecha']) ? trim($_GET['fecha']) : '';
$codigoComp = isset($_GET['jornada']) ? strtoupper(trim($_GET['jornada'])) : '';

if ($jornadaId > 0) {
    $turno = obtener_turno($conn, $jornadaId, $fechaParam !== '' ? $fechaParam : null);
} elseif ($codigoComp !== '') {
    $turno = obtener_turno_por_codigo($conn, $codigoComp);
} else {
    $turno = obtener_turno_actual($conn);
}

if (!$turno) {
    echo json_encode(['success' => false, 'error' => 'No hay jornadas configuradas o el turno solicitado no existe.']);
    exit;
}

// ── Catálogos ──────────────────────────────────────────────────────────
$funciones = [];
$r = mysqli_query($conn, "SELECT nombre FROM funciones WHERE activo=1 ORDER BY orden, nombre");
while ($row = mysqli_fetch_assoc($r)) $funciones[] = $row['nombre'];

$ubicaciones = [];
$r = mysqli_query($conn, "SELECT nombre FROM ubicaciones WHERE activo=1 ORDER BY orden, nombre");
while ($row = mysqli_fetch_assoc($r)) $ubicaciones[] = $row['nombre'];

$limites = [];
$r = mysqli_query($conn, "SELECT tipo, limite_min FROM limites_pausa");
while ($row = mysqli_fetch_assoc($r)) {
    $limites[$row['tipo']] = $row['limite_min'] === null ? null : (int)$row['limite_min'];
}

// ── Personal del turno + bitácora ────────────────────────────────────────
$personal = [];
$index    = [];   // tp.id → posición en $personal

$stmt = mysqli_prepare(
    $conn,
    "SELECT tp.id AS tp_id, tp.colaborador_id, tp.funcion, tp.ubicacion, tp.nave_id,
            tp.estado, tp.radio, tp.horario_colab, tp.foto_ingreso,
            c.codigo, c.nombre, c.tipo_funcion, c.cuadrilla
       FROM turno_personal tp
       JOIN colaboradores c ON c.id = tp.colaborador_id
      WHERE tp.turno_id = ?
      ORDER BY c.nombre ASC"
);
mysqli_stmt_bind_param($stmt, 'i', $turno['id']);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $tpId = (int)$row['tp_id'];
    $index[$tpId] = count($personal);
    $personal[] = [
        'tpId'          => $tpId,
        'id'            => $row['codigo'],            // código ST-xxx (display)
        'colaboradorId' => (int)$row['colaborador_id'],
        'nombre'        => $row['nombre'],
        'funcion'       => $row['funcion'],
        'tipoFuncion'   => $row['tipo_funcion'] ?? '',
        'cuadrilla'     => $row['cuadrilla']    ?? '',
        'ubicacion'     => $row['ubicacion'],
        'naveId'        => $row['nave_id'] !== null ? (int)$row['nave_id'] : null,
        'naveNombre'    => null,                      // se resuelve más abajo
        'horario'       => $row['horario_colab'],     // horario asignado a esta persona
        'estado'        => $row['estado'],
        'radio'         => (int)$row['radio'],
        'fotoIngreso'   => $row['foto_ingreso'] ?? null,  // foto de asistencia (auto-registro)
        'bitacora'      => [],
        'checklist'     => [],
    ];
}
mysqli_stmt_close($stmt);

// Nombre de la nave asignada. Se resuelve con una consulta aparte contra la
// base de Operaciones en vez de con un JOIN entre bases: así el turno sigue
// cargando si esa base no está disponible, y no se acopla el SQL a que ambas
// vivan en el mismo servidor (que es justo lo que la arquitectura evita).
$naveIds = array_filter(array_column($personal, 'naveId'));
if ($naveIds) {
    require_once('../includes/operaciones_naves.php');
    $nombresNave = opn_nombres_naves($naveIds);
    foreach ($personal as $i => $p) {
        if ($p['naveId'] !== null) {
            $personal[$i]['naveNombre'] = $nombresNave[$p['naveId']] ?? null;
        }
    }
}

// Eventos (solo si hay personal en el turno)
if ($personal) {
    $r = mysqli_query(
        $conn,
        "SELECT e.id, e.turno_personal_id, e.tipo, e.motivo,
                e.hora_inicio, e.hora_fin, e.observaciones
           FROM turno_eventos e
           JOIN turno_personal tp ON tp.id = e.turno_personal_id
          WHERE tp.turno_id = " . (int)$turno['id'] . "
          ORDER BY e.hora_inicio ASC, e.id ASC"
    );
    while ($row = mysqli_fetch_assoc($r)) {
        $tpId = (int)$row['turno_personal_id'];
        if (!isset($index[$tpId])) continue;
        $personal[$index[$tpId]]['bitacora'][] = [
            'id'            => (int)$row['id'],
            'tipo'          => $row['tipo'],
            'motivo'        => $row['motivo'] ?? '',
            'horaInicio'    => $row['hora_inicio'] !== null ? substr($row['hora_inicio'], 0, 5) : '',
            'horaFin'       => $row['hora_fin']    !== null ? substr($row['hora_fin'],    0, 5) : '',
            'observaciones' => $row['observaciones'] ?? '',
        ];
    }
}

// Checklist de tallyman (ítem + comentario, solo si hay personal en el turno)
if ($personal) {
    $r = mysqli_query(
        $conn,
        "SELECT cl.id, cl.turno_personal_id, cl.item, cl.comentario, cl.created_at
           FROM turno_checklist_items cl
           JOIN turno_personal tp ON tp.id = cl.turno_personal_id
          WHERE tp.turno_id = " . (int)$turno['id'] . "
          ORDER BY cl.created_at ASC, cl.id ASC"
    );
    while ($row = mysqli_fetch_assoc($r)) {
        $tpId = (int)$row['turno_personal_id'];
        if (!isset($index[$tpId])) continue;
        $personal[$index[$tpId]]['checklist'][] = [
            'id'         => (int)$row['id'],
            'item'       => $row['item'],
            'comentario' => $row['comentario'] ?? '',
        ];
    }
}

// Catálogo de motivos (activos) para los selects.
$motivos = [];
$r = mysqli_query($conn, "SELECT nombre, requiere_texto FROM motivos WHERE activo=1 ORDER BY orden, nombre");
while ($row = mysqli_fetch_assoc($r)) {
    $motivos[] = ['nombre' => $row['nombre'], 'requiereTexto' => (int)$row['requiere_texto']];
}

// Muelles con naves activas (BD operaciones)
$muellesConNave = [];
$conn_op = function_exists('conn_operaciones')
    ? conn_operaciones()
    : @mysqli_connect('127.0.0.1', 'root', '', getenv('OPER_DB_NAME') ?: 'portally_operaciones');
if ($conn_op) {
    $r_op = mysqli_query($conn_op, "SELECT DISTINCT muelle FROM naves WHERE muelle IS NOT NULL AND estado != 'Finalizada'");
    while ($row = mysqli_fetch_assoc($r_op)) {
        if ($row['muelle'] !== '') $muellesConNave[] = $row['muelle'];
    }
    mysqli_close($conn_op);
}

echo json_encode([
    'success'                => true,
    'turnoId'                => $turno['id'],
    'turnoLabel'             => $turno['label'],
    'turnoEstado'            => $turno['estado'],
    'turnoFecha'             => $turno['fecha'],
    'turnoJornada'           => $turno['jornada'],     // código (etiqueta)
    'turnoJornadaId'         => $turno['jornada_id'],
    'jornadas'               => listar_jornadas($conn),   // solo activas, para el selector
    'limitesMin'             => $limites,
    'funcionesDisponibles'   => $funciones,
    'ubicacionesDisponibles' => $ubicaciones,
    'muellesConNave'         => $muellesConNave,
    'motivosDisponibles'     => $motivos,
    'personal'               => $personal,
    'acciones'               => listar_acciones($conn, $turno['id']),
    'puedeValidar'           => can_validate(),   // Admin/Supervisor: validar y REABRIR
    'puedeCerrar'            => can_report(),      // Admin/Supervisor/Coordinador: CERRAR
    'rol'                    => $_SESSION['user_rol'] ?? null,
    'serverFecha'            => date('Y-m-d'),     // fecha del servidor (America/Lima): base para permisos por rol, no el reloj del dispositivo.
]);
