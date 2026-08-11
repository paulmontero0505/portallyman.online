<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Listado de evaluaciones EVADES (JSON)
   ───────────────────────────────────────────────────────────────────────
   Sin ?id= devuelve la lista (cabeceras, sin detalle de competencias).
   Con ?id=N devuelve una evaluación con su detalle de 10 competencias.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/evades_catalogo.php');
require_once('../includes/evades_engine.php');
require_once('../includes/evades_apreciaciones.php');
api_require_report();

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$rol = (string)($_SESSION['user_rol'] ?? '');
$uid = (int)($_SESSION['user_id'] ?? 0);
$visibilidad = in_array($rol, ['Administrador', 'Supervisor'], true)
    ? '1=1'
    : ($rol === 'Coordinador' && $uid > 0
        ? "((ev.bloque_id IS NOT NULL AND ev.coordinador_id=$uid) OR (ev.bloque_id IS NULL AND col.coordinador_id=$uid))"
        : '0=1');

if ($id > 0) {
    $sql = "SELECT ev.*, col.coordinador_id AS coord_cargo_id,
                   b.estado AS bloque_estado,b.version AS bloque_version
              FROM evades_evaluaciones ev
              LEFT JOIN colaboradores col ON col.id = ev.colaborador_id
              LEFT JOIN evades_bloques b ON b.id=ev.bloque_id
             WHERE ev.id = ? AND ($visibilidad)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $ev = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$ev) {
        echo json_encode(['success' => false, 'error' => 'Evaluación no encontrada.']); exit;
    }

    $stmt = mysqli_prepare(
        $conn,
        "SELECT competencia_key, tipo, base, auto_incremento, auto_descuento,
                incremento_final, descuento_final, puntaje_final, motivo_ajuste, evidencia_json
           FROM evades_competencias WHERE evaluacion_id=? ORDER BY id ASC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $comp = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $row['base'] = (int)$row['base'];
        $row['auto_incremento'] = $row['auto_incremento'] !== null ? (int)$row['auto_incremento'] : null;
        $row['auto_descuento']  = $row['auto_descuento']  !== null ? (int)$row['auto_descuento']  : null;
        $row['incremento_final'] = (int)$row['incremento_final'];
        $row['descuento_final']  = (int)$row['descuento_final'];
        $row['puntaje_final']    = (int)$row['puntaje_final'];
        $row['evidencia'] = json_decode($row['evidencia_json'], true) ?: [];
        foreach ($row['evidencia'] as $indice => $itemEvidencia) {
            if (($itemEvidencia['tipo'] ?? '') !== 'calculo_motor') continue;
            $row['cobertura'] = $itemEvidencia['cobertura'] ?? 'parcial';
            $row['regla'] = $itemEvidencia['regla'] ?? null;
            $row['resumen_calculo'] = $itemEvidencia['descripcion'] ?? null;
            unset($row['evidencia'][$indice]);
        }
        $row['evidencia'] = array_values($row['evidencia']);
        unset($row['evidencia_json']);
        $comp[] = $row;
    }
    mysqli_stmt_close($stmt);

    // Backfill de 'punto_mejorar'/'detalle' para evidencia de incidencias
    // guardada antes de que el motor de cálculo empezara a capturarlos
    // (evades_engine.php).
    $idsFaltantes = [];
    foreach ($comp as $row) {
        foreach ($row['evidencia'] as $e) {
            if (($e['tipo'] ?? '') === 'incidencia' && !array_key_exists('punto_mejorar', $e)) {
                $idsFaltantes[] = (int)$e['id'];
            }
        }
    }
    if ($idsFaltantes) {
        $idsFaltantes = array_values(array_unique($idsFaltantes));
        $placeholders = implode(',', array_fill(0, count($idsFaltantes), '?'));
        $tipos = str_repeat('i', count($idsFaltantes));
        $stmtInc = mysqli_prepare($conn, "SELECT id, punto_mejorar, detalle FROM incidencias WHERE id IN ($placeholders)");
        mysqli_stmt_bind_param($stmtInc, $tipos, ...$idsFaltantes);
        mysqli_stmt_execute($stmtInc);
        $resInc = mysqli_stmt_get_result($stmtInc);
        $datosPorId = [];
        while ($r = mysqli_fetch_assoc($resInc)) $datosPorId[(int)$r['id']] = $r;
        mysqli_stmt_close($stmtInc);

        foreach ($comp as &$row) {
            foreach ($row['evidencia'] as &$e) {
                if (($e['tipo'] ?? '') === 'incidencia' && !array_key_exists('punto_mejorar', $e)) {
                    $datos = $datosPorId[(int)$e['id']] ?? null;
                    $e['punto_mejorar'] = $datos['punto_mejorar'] ?? null;
                    $e['detalle'] = $datos['detalle'] ?? null;
                }
            }
            unset($e);
        }
        unset($row);
    }

    // Enriquece el snapshot persistido con la lectura actual del motor. Esto
    // incluye apreciaciones añadidas después de generar el bloque, sin borrar
    // los valores finales ni la evidencia histórica guardada.
    $sugerenciasActuales = $ev['bloque_estado'] === 'cerrado'
        ? []
        : evades_calcular_sugerencias($conn, (int)$ev['colaborador_id'], $ev['periodo']);
    $sugerenciasPorClave = [];
    foreach (($sugerenciasActuales ?: []) as $sugerencia) {
        $sugerenciasPorClave[$sugerencia['competencia_key']] = $sugerencia;
    }
    foreach ($comp as &$competencia) {
        $actual = $sugerenciasPorClave[$competencia['competencia_key']] ?? null;
        if (!$actual) continue;
        $competencia['auto_incremento_actual'] = $actual['auto_incremento'];
        $competencia['auto_descuento_actual'] = $actual['auto_descuento'];
        $competencia['cobertura'] = $actual['cobertura'];
        $competencia['regla'] = $actual['regla'];
        $competencia['resumen_calculo'] = $actual['resumen_calculo'];
        $competencia['evidencia_actual'] = $actual['evidencia'];
    }
    unset($competencia);

    $ev['id'] = (int)$ev['id'];
    $ev['bloque_id'] = $ev['bloque_id'] !== null ? (int)$ev['bloque_id'] : null;
    $ev['version'] = (int)$ev['version'];
    $ev['bloque_version'] = $ev['bloque_version'] !== null ? (int)$ev['bloque_version'] : null;
    $ev['colaborador_id'] = $ev['colaborador_id'] !== null ? (int)$ev['colaborador_id'] : null;
    $ev['coordinador_id'] = $ev['coordinador_id'] !== null ? (int)$ev['coordinador_id'] : null;
    $ev['puntaje_total'] = (int)$ev['puntaje_total'];
    $ev['puntaje_anterior'] = $ev['puntaje_anterior'] !== null ? (int)$ev['puntaje_anterior'] : null;
    $ev['variacion_pct'] = $ev['variacion_pct'] !== null ? (float)$ev['variacion_pct'] : null;
    $ev['competencias'] = $comp;
    $ev['apreciaciones'] = evades_listar_apreciaciones($conn, $id);
    unset($ev['coord_cargo_id']);

    echo json_encode(['success' => true, 'data' => $ev]);
    exit;
}

$sql = "SELECT ev.id,ev.bloque_id,ev.version, ev.colaborador_id, ev.colaborador_nombre, ev.colaborador_codigo,
               ev.colaborador_cargo, ev.coordinador_id, ev.coordinador_nombre,
               ev.periodo, ev.fecha_evaluacion, ev.puntaje_total, ev.clasificacion,
               ev.puntaje_anterior, ev.variacion_pct,b.estado AS bloque_estado,b.version AS bloque_version,
               ev.created_at, ev.updated_at
          FROM evades_evaluaciones ev
          LEFT JOIN colaboradores col ON col.id = ev.colaborador_id
          LEFT JOIN evades_bloques b ON b.id=ev.bloque_id
         WHERE $visibilidad
         ORDER BY ev.periodo DESC, ev.id DESC";
$r = mysqli_query($conn, $sql);
if (!$r) {
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    exit;
}

$out = [];
while ($row = mysqli_fetch_assoc($r)) {
    $row['id'] = (int)$row['id'];
    $row['bloque_id'] = $row['bloque_id'] !== null ? (int)$row['bloque_id'] : null;
    $row['version'] = (int)$row['version'];
    $row['bloque_version'] = $row['bloque_version'] !== null ? (int)$row['bloque_version'] : null;
    $row['colaborador_id'] = $row['colaborador_id'] !== null ? (int)$row['colaborador_id'] : null;
    $row['coordinador_id'] = $row['coordinador_id'] !== null ? (int)$row['coordinador_id'] : null;
    $row['puntaje_total'] = (int)$row['puntaje_total'];
    $row['puntaje_anterior'] = $row['puntaje_anterior'] !== null ? (int)$row['puntaje_anterior'] : null;
    $row['variacion_pct'] = $row['variacion_pct'] !== null ? (float)$row['variacion_pct'] : null;
    $out[] = $row;
}

echo json_encode(['success' => true, 'data' => $out]);
