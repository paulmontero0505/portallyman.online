<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Alta / edición de una Asistencia Pre-Operativa (JSON)
   ───────────────────────────────────────────────────────────────────────
   Payload JSON:
   {
     id?:         int (0/omitido = alta),
     tema, tipo_reunion, lugar, capacitador, turno, fecha, hora,
     zona_trabajo, observaciones,
     participantes: [ { colaborador_id, estado('asistio'|'falta'|'tardanza') }, ... ],
     evidencias:    [ { nombre, mime, peso_bytes, drive_file_id, drive_url,
                        ruta_local, estado }, ... ]   // ya subidas por upload_asistencia_file.php
   }
   · El coordinador sale SIEMPRE de la sesión.
   · Nombre/DNI/cargo del colaborador se copian (congelan) al guardar.
   · En edición se reemplazan por completo participantes y evidencias.
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/asistencias_catalogo.php');
require_once('../includes/turno.php');
require_once('../includes/acciones.php');
api_require_report();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']); exit;
}

$id           = isset($data['id']) ? (int)$data['id'] : 0;
$tema         = trim($data['tema'] ?? '');
$tipo         = trim($data['tipo_reunion'] ?? '');
$lugar        = trim($data['lugar'] ?? '');
$capacitador  = trim($data['capacitador'] ?? '');
$turno        = trim($data['turno'] ?? '');
$fecha        = trim($data['fecha'] ?? '');
$hora         = trim($data['hora'] ?? '');
$zona         = trim($data['zona_trabajo'] ?? '');
$observaciones= trim($data['observaciones'] ?? '');
$participantes= $data['participantes'] ?? [];
$evidencias   = $data['evidencias'] ?? [];

// ── Validaciones ────────────────────────────────────────────────────────
if ($tema === '')                                   { echo json_encode(['success'=>false,'error'=>'Indica el tema o actividad.']); exit; }
if (!array_key_exists($tipo, aso_tipos_reunion()))  { echo json_encode(['success'=>false,'error'=>'Tipo de reunión inválido.']); exit; }
if ($capacitador === '')                            { echo json_encode(['success'=>false,'error'=>'Indica el capacitador.']); exit; }
if (!array_key_exists($turno, inc_turnos()))        { echo json_encode(['success'=>false,'error'=>'Turno inválido.']); exit; }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha))   { echo json_encode(['success'=>false,'error'=>'Fecha inválida.']); exit; }
if ($hora !== '' && !preg_match('/^\d{2}:\d{2}$/', $hora)) { echo json_encode(['success'=>false,'error'=>'Hora inválida.']); exit; }
if ($zona !== '' && !in_array($zona, inc_zonas(false), true)) { echo json_encode(['success'=>false,'error'=>'Zona de trabajo inválida.']); exit; }
if (!is_array($participantes) || count($participantes) === 0) {
    echo json_encode(['success'=>false,'error'=>'Selecciona al menos un participante.']); exit;
}

$horaSql = $hora !== '' ? $hora . ':00' : null;
$zonaSql = $zona !== '' ? $zona : null;
$lugarSql= $lugar !== '' ? $lugar : null;
$obsSql  = $observaciones !== '' ? $observaciones : null;
$coordinador   = $_SESSION['user_name'] ?? 'Coordinador';
$coordinadorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// ── Resuelve nombre/DNI/cargo congelados de cada participante ────────────
$ids = [];
foreach ($participantes as $p) {
    $cid = (int)($p['colaborador_id'] ?? 0);
    if ($cid > 0) $ids[$cid] = true;
}
if (!$ids) { echo json_encode(['success'=>false,'error'=>'Participantes inválidos.']); exit; }

$inList = implode(',', array_map('intval', array_keys($ids)));
$catalogo = [];
$rc = mysqli_query($conn, "SELECT id, codigo, nombre, dni, funcion_principal FROM colaboradores WHERE id IN ($inList)");
while ($row = mysqli_fetch_assoc($rc)) $catalogo[(int)$row['id']] = $row;

// ── Transacción: cabecera + participantes + evidencias ──────────────────
mysqli_begin_transaction($conn);
try {
    if ($id > 0) {
        $stmt = mysqli_prepare($conn,
            "UPDATE asistencias_preoperativas SET
                tema=?, tipo_reunion=?, lugar=?, capacitador=?, turno=?, fecha=?, hora=?,
                zona_trabajo=?, observaciones=?
             WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'sssssssssi',
            $tema, $tipo, $lugarSql, $capacitador, $turno, $fecha, $horaSql, $zonaSql, $obsSql, $id);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_errno($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        // Reemplaza participantes y evidencias por completo.
        mysqli_query($conn, "DELETE FROM asistencias_participantes WHERE asistencia_id=" . (int)$id);
        mysqli_query($conn, "DELETE FROM asistencias_evidencias    WHERE asistencia_id=" . (int)$id);
        $asistenciaId = $id;
    } else {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO asistencias_preoperativas
                (tema, tipo_reunion, lugar, capacitador, turno, fecha, hora, zona_trabajo,
                 observaciones, coordinador, coordinador_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssssssssssi',
            $tema, $tipo, $lugarSql, $capacitador, $turno, $fecha, $horaSql, $zonaSql,
            $obsSql, $coordinador, $coordinadorId);
        mysqli_stmt_execute($stmt);
        if (mysqli_stmt_errno($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        $asistenciaId = (int)mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);
    }

    // Participantes
    $estadosValidos = ['asistio', 'falta', 'tardanza'];
    $stmtP = mysqli_prepare($conn,
        "INSERT INTO asistencias_participantes
            (asistencia_id, colaborador_id, colaborador_nombre, colaborador_dni, colaborador_cargo, estado)
         VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($participantes as $p) {
        $cid = (int)($p['colaborador_id'] ?? 0);
        $col = $catalogo[$cid] ?? null;
        if (!$col) continue;                       // ignora ids inexistentes
        $nombre = $col['nombre'];
        $dni    = $col['dni'];
        $cargo  = $col['funcion_principal'];
        $estadoP = trim($p['estado'] ?? 'asistio');
        if (!in_array($estadoP, $estadosValidos, true)) $estadoP = 'asistio';
        mysqli_stmt_bind_param($stmtP, 'iissss', $asistenciaId, $cid, $nombre, $dni, $cargo, $estadoP);
        mysqli_stmt_execute($stmtP);
        if (mysqli_stmt_errno($stmtP)) throw new Exception(mysqli_stmt_error($stmtP));
    }
    mysqli_stmt_close($stmtP);

    // Evidencias (metadatos ya subidos por upload_asistencia_file.php)
    if (is_array($evidencias) && $evidencias) {
        $stmtE = mysqli_prepare($conn,
            "INSERT INTO asistencias_evidencias
                (asistencia_id, nombre_archivo, mime, peso_bytes, drive_file_id, drive_url, ruta_local, estado, error_msg)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($evidencias as $e) {
            $nom   = trim($e['nombre'] ?? '');
            if ($nom === '') continue;
            $mime  = trim($e['mime'] ?? 'application/octet-stream');
            $peso  = (int)($e['peso_bytes'] ?? 0);
            $fid   = ($e['drive_file_id'] ?? '') ?: null;
            $url   = ($e['drive_url'] ?? '') ?: null;
            $local = ($e['ruta_local'] ?? '') ?: null;
            $estado= in_array(($e['estado'] ?? ''), ['subido','pendiente','error'], true) ? $e['estado'] : 'subido';
            $emsg  = null;
            mysqli_stmt_bind_param($stmtE, 'ississsss',
                $asistenciaId, $nom, $mime, $peso, $fid, $url, $local, $estado, $emsg);
            mysqli_stmt_execute($stmtE);
            if (mysqli_stmt_errno($stmtE)) throw new Exception(mysqli_stmt_error($stmtE));
        }
        mysqli_stmt_close($stmtE);
    }

    mysqli_commit($conn);
} catch (Exception $ex) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'No se pudo guardar: ' . $ex->getMessage()]); exit;
}

/* ── Auto-alta en el turno ────────────────────────────────────────────────
   Los participantes de la charla que aún no estén en el turno correspondiente
   (misma fecha + jornada) se agregan automáticamente. Se respeta la regla de
   turno adyacente: quien ya trabajó el turno contiguo NO se agrega. */
$turnoSync = ['agregados' => 0, 'omitidos' => 0];
$codJornada = ($turno === 'noche') ? 'N' : 'D';
$jornadaRow = jornada_por_codigo($conn, $codJornada);
if ($jornadaRow) {
    [$turnoId, $estadoTurno] = _find_or_create_turno($conn, (int)$jornadaRow['id'], $fecha);
    if ($estadoTurno !== 'cerrado') {
        // Colaboradores ya presentes en el turno.
        $presentes = [];
        $q = mysqli_query($conn, "SELECT colaborador_id FROM turno_personal WHERE turno_id = " . (int)$turnoId);
        while ($r = mysqli_fetch_assoc($q)) $presentes[(int)$r['colaborador_id']] = true;

        $conflictos   = turno_conflict_set($conn, $fecha, $codJornada);
        $horarioTurno = $jornadaRow['nombre'] ?? null;
        $ubicTurno    = $zonaSql;   // la zona de la charla se usa como ubicación

        $stmtA = mysqli_prepare($conn,
            "INSERT INTO turno_personal (turno_id, colaborador_id, funcion, ubicacion, horario_colab, estado)
                  VALUES (?, ?, ?, ?, ?, 'activo')");
        foreach (array_keys($ids) as $cid) {
            $cid = (int)$cid;
            if (isset($presentes[$cid]))    continue;                          // ya está en el turno
            if (isset($conflictos[$cid])) { $turnoSync['omitidos']++; continue; }  // trabajó turno adyacente
            $col = $catalogo[$cid] ?? null;
            if (!$col) continue;
            $func = trim($col['funcion_principal'] ?? '');
            if ($func === '') { $turnoSync['omitidos']++; continue; }          // sin función asignada

            mysqli_stmt_bind_param($stmtA, 'iisss', $turnoId, $cid, $func, $ubicTurno, $horarioTurno);
            if (mysqli_stmt_execute($stmtA)) {
                $turnoSync['agregados']++;
                registrar_accion($conn, $turnoId, 'alta',
                    'Agregado desde charla · ' . $func . ($ubicTurno ? ' @ ' . $ubicTurno : ''),
                    $col['codigo'] ?? '', $col['nombre']);
            }
        }
        mysqli_stmt_close($stmtA);
    }
}

echo json_encode(['success' => true, 'id' => $asistenciaId, 'turnoSync' => $turnoSync]);
