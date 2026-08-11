<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · API · Sincronización inicial (backfill) a Google Sheets
   Empuja TODO lo existente a la hoja, en lotes (un POST por pestaña).
   Solo Administrador. Reporta el resultado REAL de cada pestaña (ok/fallo).
   ═══════════════════════════════════════════════════════════════════════ */
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/acciones.php');             // label_accion()
require_once('../includes/incidencias_catalogo.php'); // inc_impactos(), inc_turnos()
require_once('../includes/sheets.php');
api_require_admin();

header('Content-Type: application/json');

if (!sheets_enabled()) {
    echo json_encode(['success' => false, 'error' => 'Google Sheets no está configurado (includes/sheets_config.php).']);
    exit;
}

$resumen = [];
$allOk   = true;
$rec = function ($tab, $filas, $ok) use (&$resumen, &$allOk) {
    $resumen[$tab] = ['filas' => $filas, 'ok' => (bool)$ok];
    if (!$ok) $allOk = false;
};
$labelTipoEv = ['traslado' => 'Traslado', 'refrigerio' => 'Refrigerio', 'permiso' => 'Permiso'];

// ── 1) Colaboradores (snapshot completo) ──
$okCol = sheets_sync_colaboradores($conn);
$cnt   = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM colaboradores"));
$rec('TALLYMANS', (int)$cnt[0], $okCol);

// ── 1b) Registros Tallyman ──
$okTm = sheets_sync_tallyman();
$rec('Registro_Tallyman', '—', $okTm);

// ── 2) Incidencias (todas) ──
$rows = [];
$r = mysqli_query($conn,
    "SELECT id, created_at, colaborador_nombre, colaborador_cargo, punto_mejorar, competencia,
            impacto, coordinador, turno, fecha, zona_trabajo, detalle, foto_path, declaracion_path
       FROM incidencias ORDER BY id");
while ($r && $row = mysqli_fetch_assoc($r)) {
    $rows[] = [
        (int)$row['id'], $row['created_at'], $row['colaborador_nombre'], $row['colaborador_cargo'],
        $row['punto_mejorar'], $row['competencia'],
        (inc_impactos()[$row['impacto']]['label'] ?? $row['impacto']), $row['coordinador'],
        (inc_turnos()[$row['turno']] ?? $row['turno']), $row['fecha'], $row['zona_trabajo'],
        $row['detalle'] ?? '', $row['foto_path'] ?? '', $row['declaracion_path'] ?? '',
    ];
}
$ok = sheets_push('Incidencias', $rows, 'replace', ['header' => [
    'ID', 'Registrado', 'Colaborador', 'Cargo', 'Punto a mejorar', 'Competencia',
    'Impacto', 'Coordinador', 'Turno', 'Fecha', 'Zona de trabajo', 'Detalle', 'Foto', 'Declaración',
]]);
$rec('Incidencias', count($rows), $ok);

// ── 3) Bitacora (todos los eventos) ──
$rows = [];
$r = mysqli_query($conn,
    "SELECT e.created_at, t.fecha, j.nombre AS jornada, c.codigo, c.nombre,
            tp.funcion, tp.ubicacion, e.tipo, e.hora_inicio, e.hora_fin, e.motivo, e.observaciones
       FROM turno_eventos e
       JOIN turno_personal tp ON tp.id = e.turno_personal_id
       JOIN turnos t          ON t.id = tp.turno_id
       JOIN jornadas j        ON j.id = t.jornada_id
       JOIN colaboradores c   ON c.id = tp.colaborador_id
      ORDER BY t.fecha, j.orden, e.hora_inicio, e.id");
while ($r && $row = mysqli_fetch_assoc($r)) {
    $ini = $row['hora_inicio'] !== null ? substr($row['hora_inicio'], 0, 5) : '';
    $fin = $row['hora_fin']    !== null ? substr($row['hora_fin'], 0, 5) : '';
    $rows[] = [
        $row['created_at'], $row['fecha'], $row['jornada'], $row['codigo'], $row['nombre'],
        $row['funcion'], $row['ubicacion'], ($labelTipoEv[$row['tipo']] ?? $row['tipo']),
        $ini, $fin, _sheets_dur($ini, $fin), $row['motivo'] ?? '', $row['observaciones'] ?? '', '',
    ];
}
$ok = sheets_push('Bitacora', $rows, 'replace', ['header' => [
    'Registrado', 'Fecha turno', 'Jornada', 'Código', 'Colaborador', 'Función', 'Ubicación',
    'Tipo', 'Inicio', 'Fin', 'Duración (min)', 'Motivo', 'Observaciones', 'Usuario',
]]);
$rec('Bitacora', count($rows), $ok);

// ── 4) Cambios_Estado y Auditoria (desde turno_acciones) ──
$cambios = []; $audit = [];
$accHeader = ['Registrado', 'Fecha turno', 'Jornada', 'Hora', 'Usuario', 'Rol', 'Acción', 'Código', 'Colaborador', 'Detalle'];
$r = mysqli_query($conn,
    "SELECT a.created_at, t.fecha, j.nombre AS jornada, TIME(a.created_at) AS hora,
            a.usuario_nombre, a.usuario_rol, a.tipo, a.colaborador_codigo, a.colaborador_nombre, a.detalle
       FROM turno_acciones a
       JOIN turnos t   ON t.id = a.turno_id
       JOIN jornadas j ON j.id = t.jornada_id
      ORDER BY t.fecha, j.orden, a.created_at, a.id");
while ($r && $row = mysqli_fetch_assoc($r)) {
    if ($row['tipo'] === 'evento') continue; // los eventos viven en 'Bitacora'
    $fila = [
        $row['created_at'], $row['fecha'], $row['jornada'], substr($row['hora'], 0, 5),
        $row['usuario_nombre'] ?? '', $row['usuario_rol'] ?? '', label_accion($row['tipo']),
        $row['colaborador_codigo'] ?? '', $row['colaborador_nombre'] ?? '', $row['detalle'] ?? '',
    ];
    if (in_array($row['tipo'], ['cambio', 'estado'], true)) $cambios[] = $fila;
    else $audit[] = $fila;
}
$rec('Cambios_Estado', count($cambios), sheets_push('Cambios_Estado', $cambios, 'replace', ['header' => $accHeader]));
$rec('Auditoria',      count($audit),   sheets_push('Auditoria',      $audit,   'replace', ['header' => $accHeader]));

// ── 5) Resumen_Turnos y Conteo_Personal (todos los turnos) ──
$resRows = []; $conteoRows = [];
$r = mysqli_query($conn, "SELECT id FROM turnos ORDER BY fecha, jornada_id");
while ($r && $row = mysqli_fetch_assoc($r)) {
    $agg = _sheets_turno_aggregates($conn, (int)$row['id']);
    if (!$agg) continue;
    $resRows[] = $agg['resumen'];
    foreach ($agg['conteo'] as $c) $conteoRows[] = $c;
}
$rec('Resumen_Turnos',  count($resRows),    sheets_push('Resumen_Turnos',  $resRows,    'replace', ['header' => _sheets_resumen_header()]));
$rec('Conteo_Personal', count($conteoRows), sheets_push('Conteo_Personal', $conteoRows, 'replace', ['header' => _sheets_conteo_header()]));

echo json_encode(['success' => true, 'allOk' => $allOk, 'resumen' => $resumen]);
