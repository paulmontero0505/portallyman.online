<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Datos del relevo desde la BD del PHP (estiba_turno)
   GET ?fecha=YYYY-MM-DD&turno=CODIGO
   Devuelve: personal/radios (de turno_personal), incidencias (del módulo
   Incidencias) y el último relevo generado (para prefilar observaciones).
   ═══════════════════════════════════════════════════════════════════════ */
require_once(__DIR__ . '/../includes/auth.php');
require_once(__DIR__ . '/../includes/db.php');
require_once(__DIR__ . '/../includes/turno.php');

header('Content-Type: application/json; charset=utf-8');
api_require_operaciones();

$fecha = trim($_GET['fecha'] ?? '');
$turno = strtoupper(trim($_GET['turno'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $turno === '') {
    echo json_encode(['success' => false, 'error' => 'fecha (YYYY-MM-DD) y turno son obligatorios.']); exit;
}

// ── Resolver turno_id (fecha + jornada por código) ──
$turnoId = null;
$j = jornada_por_codigo($conn, $turno);
if ($j && ($stmt = mysqli_prepare($conn, "SELECT id FROM turnos WHERE fecha=? AND jornada_id=? LIMIT 1"))) {
    $jid = (int)$j['id'];
    mysqli_stmt_bind_param($stmt, 'si', $fecha, $jid);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $tid);
    if (mysqli_stmt_fetch($stmt)) $turnoId = (int)$tid;
    mysqli_stmt_close($stmt);
}

// ── Personal del turno agrupado por ubicación + radios ──
$porUbic = [];
$totPersonas = 0; $totRadios = 0;
$coordExiste = false; $coordRadio = false;
$tpIndex = []; // tp.id → posición en $checklistPorPersona
$checklistPorPersona = [];
if ($turnoId && ($stmt = mysqli_prepare(
    $conn,
    "SELECT tp.id AS tp_id, tp.funcion, tp.ubicacion, tp.radio, c.nombre
       FROM turno_personal tp
       JOIN colaboradores c ON c.id = tp.colaborador_id
      WHERE tp.turno_id=?"
))) {
    mysqli_stmt_bind_param($stmt, 'i', $turnoId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $ubic = ($row['ubicacion'] !== null && $row['ubicacion'] !== '') ? $row['ubicacion'] : 'Sin ubicación';
        if (!isset($porUbic[$ubic])) $porUbic[$ubic] = ['ubicacion' => $ubic, 'personas' => 0, 'radios' => 0];
        $porUbic[$ubic]['personas']++;
        $totPersonas++;
        if ((int)$row['radio'] === 1) { $porUbic[$ubic]['radios']++; $totRadios++; }
        if (stripos((string)$row['funcion'], 'coord') !== false) {
            $coordExiste = true;
            if ((int)$row['radio'] === 1) $coordRadio = true;
        }
        $tpId = (int)$row['tp_id'];
        $tpIndex[$tpId] = count($checklistPorPersona);
        $checklistPorPersona[] = ['nombre' => $row['nombre'], 'ubicacion' => $ubic, 'items' => []];
    }
    mysqli_stmt_close($stmt);
}

// ── Checklist tallyman (item + comentario) por persona del turno ──
if ($turnoId && $checklistPorPersona) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT cl.turno_personal_id, cl.item, cl.comentario
           FROM turno_checklist_items cl
           JOIN turno_personal tp ON tp.id = cl.turno_personal_id
          WHERE tp.turno_id=?
          ORDER BY cl.created_at ASC, cl.id ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $turnoId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $tpId = (int)$row['turno_personal_id'];
        if (!isset($tpIndex[$tpId])) continue;
        $checklistPorPersona[$tpIndex[$tpId]]['items'][] = [
            'item'       => $row['item'],
            'comentario' => $row['comentario'] ?? '',
        ];
    }
    mysqli_stmt_close($stmt);
}
// Solo se exponen las personas que sí tienen ítems registrados.
$checklistPorPersona = array_values(array_filter($checklistPorPersona, function ($p) { return !empty($p['items']); }));

$personal = [
    'porUbicacion'  => array_values($porUbic),
    'totalPersonas' => $totPersonas,
    'totalRadios'   => $totRadios,
    'coordinador'   => ['existe' => $coordExiste, 'radio' => $coordRadio],
    'checklist'     => $checklistPorPersona,
];

// ── Incidencias del módulo (por fecha; por turno solo cuando es Día/Noche) ──
$turnoEnum = $turno === 'D' ? 'dia' : ($turno === 'N' ? 'noche' : null);
$incidencias = [];
if ($turnoEnum) {
    $stmt = mysqli_prepare($conn, "SELECT punto_mejorar, colaborador_nombre, colaborador_cargo, zona_trabajo, detalle
                                     FROM incidencias WHERE fecha=? AND turno=? ORDER BY created_at ASC, id ASC");
    mysqli_stmt_bind_param($stmt, 'ss', $fecha, $turnoEnum);
} else {
    $stmt = mysqli_prepare($conn, "SELECT punto_mejorar, colaborador_nombre, colaborador_cargo, zona_trabajo, detalle
                                     FROM incidencias WHERE fecha=? ORDER BY created_at ASC, id ASC");
    mysqli_stmt_bind_param($stmt, 's', $fecha);
}
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $incidencias[] = [
        'punto'       => $row['punto_mejorar'],
        'colaborador' => $row['colaborador_nombre'],
        'cargo'       => $row['colaborador_cargo'],
        'zona'        => $row['zona_trabajo'],
        'detalle'     => $row['detalle'],
    ];
}
mysqli_stmt_close($stmt);

// ── Último relevo generado para este turno (prefill de observaciones) ──
$generado = null;
if ($stmt = mysqli_prepare($conn, "SELECT generado_por, generado_en, observaciones
                                     FROM relevo_generado WHERE fecha=? AND turno=? ORDER BY id DESC LIMIT 1")) {
    mysqli_stmt_bind_param($stmt, 'ss', $fecha, $turno);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($res)) {
        $generado = [
            'generado_por'  => $row['generado_por'],
            'generado_en'   => $row['generado_en'],
            'observaciones' => $row['observaciones'],
        ];
    }
    mysqli_stmt_close($stmt);
}

echo json_encode([
    'success'     => true,
    'personal'    => $personal,
    'incidencias' => $incidencias,
    'generado'    => $generado,
]);
