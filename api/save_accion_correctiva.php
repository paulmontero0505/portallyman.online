<?php
/* ═══════════════════════════════════════════════════════════════════════
   ESTIBA_TURNO · Acción correctiva de un reporte de inspección (JSON)
   ───────────────────────────────────────────────────────────────────────
   El reporte de inspección es la petición; esto guarda la RESPUESTA del
   coordinador sobre un reporte YA existente. Es una por reporte: volver a
   llamar a este endpoint la reemplaza (permite editarla y añadir o quitar
   evidencias).

   Payload:
   {
     reporte_id: 42,
     opciones:   ['epp','limpieza'],          // claves del catálogo del servidor
     comentario: 'texto libre',
     evidencias: [ { nombre, mime, peso_bytes, drive_file_id, drive_url,
                     ruta_local, estado }, ... ]  // ya subidas por
                                                  // upload_reporte_inspeccion_file.php
   }
   ═══════════════════════════════════════════════════════════════════════ */

require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/reporte_inspeccion_catalogo.php');
api_require_report();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'error' => 'Payload inválido.']); exit;
}

$reporteId  = isset($data['reporte_id']) ? (int)$data['reporte_id'] : 0;
$opciones   = is_array($data['opciones'] ?? null) ? $data['opciones'] : [];
$comentario = trim($data['comentario'] ?? '');
$evidencias = is_array($data['evidencias'] ?? null) ? $data['evidencias'] : [];

if ($reporteId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Reporte inválido.']); exit;
}

// El reporte tiene que existir: la acción correctiva siempre responde a uno.
$stmt = mysqli_prepare($conn, "SELECT id FROM reporte_inspeccion WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, 'i', $reporteId);
mysqli_stmt_execute($stmt);
$existe = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$existe) {
    echo json_encode(['success' => false, 'error' => 'El reporte no existe.']); exit;
}

// Las opciones se validan contra el catálogo del servidor: no se confía en
// lo que mande el cliente. Se deduplican y se guardan en el orden del catálogo.
$catalogo = ri_acciones_correctivas();
foreach ($opciones as $k) {
    if (!array_key_exists($k, $catalogo)) {
        echo json_encode(['success' => false, 'error' => 'Acción correctiva inválida: "' . htmlspecialchars((string)$k) . '".']); exit;
    }
}
$marcadas = array_values(array_filter(array_keys($catalogo), fn($k) => in_array($k, $opciones, true)));

if (!$marcadas && $comentario === '' && !$evidencias) {
    echo json_encode(['success' => false, 'error' => 'Marca al menos una acción, escribe un comentario o adjunta una evidencia.']); exit;
}
if (mb_strlen($comentario) > 2000) {
    echo json_encode(['success' => false, 'error' => 'El comentario no puede superar los 2000 caracteres.']); exit;
}

$opcionesJson = json_encode($marcadas, JSON_UNESCAPED_UNICODE);
$comentarioSql = $comentario !== '' ? $comentario : null;
$autor   = $_SESSION['user_name'] ?? 'Coordinador';
$autorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// Cabecera + evidencias en una transacción: si algo falla, no queda una
// acción guardada con adjuntos a medias.
mysqli_begin_transaction($conn);
try {
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE reporte_inspeccion
            SET accion_opciones=?, accion_comentario=?, accion_por=?, accion_por_id=?,
                accion_fecha=NOW()
          WHERE id=?"
    );
    mysqli_stmt_bind_param($stmt, 'sssii', $opcionesJson, $comentarioSql, $autor, $autorId, $reporteId);
    if (!mysqli_stmt_execute($stmt)) {
        $e = mysqli_stmt_error($stmt); mysqli_stmt_close($stmt);
        throw new Exception($e ?: 'No se pudo guardar la acción correctiva.');
    }
    mysqli_stmt_close($stmt);

    // Se reemplazan por completo: el cliente manda la lista final.
    $del = mysqli_prepare($conn, "DELETE FROM reporte_inspeccion_evidencias WHERE reporte_id=?");
    mysqli_stmt_bind_param($del, 'i', $reporteId);
    mysqli_stmt_execute($del);
    mysqli_stmt_close($del);

    if ($evidencias) {
        $ins = mysqli_prepare(
            $conn,
            "INSERT INTO reporte_inspeccion_evidencias
                (reporte_id, nombre_archivo, mime, peso_bytes, drive_file_id, drive_url, ruta_local, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        // bind_param liga por referencia: se declaran antes y el bucle sólo
        // cambia sus valores, reutilizando la misma sentencia preparada.
        $nombre = ''; $mime = ''; $peso = 0;
        $driveId = null; $driveUrl = null; $local = null; $estado = 'pendiente';
        mysqli_stmt_bind_param($ins, 'ississss',
            $reporteId, $nombre, $mime, $peso, $driveId, $driveUrl, $local, $estado);
        foreach ($evidencias as $ev) {
            $nombre  = mb_substr(trim($ev['nombre'] ?? $ev['nombre_archivo'] ?? ''), 0, 150);
            $mime    = mb_substr(trim($ev['mime'] ?? ''), 0, 120);
            if ($nombre === '' || $mime === '') continue;   // metadato incompleto: se descarta
            $peso    = (int)($ev['peso_bytes'] ?? 0);
            $driveId = $ev['drive_file_id'] ?: null;
            $driveUrl= $ev['drive_url'] ?: null;
            $local   = $ev['ruta_local'] ?: null;
            $estado  = in_array($ev['estado'] ?? '', ['subido', 'pendiente', 'error'], true)
                       ? $ev['estado'] : 'pendiente';
            if (!mysqli_stmt_execute($ins)) {
                $e = mysqli_stmt_error($ins); mysqli_stmt_close($ins);
                throw new Exception($e ?: 'No se pudo guardar una evidencia.');
            }
        }
        mysqli_stmt_close($ins);
    }

    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]); exit;
}

echo json_encode(['success' => true, 'id' => $reporteId]);
