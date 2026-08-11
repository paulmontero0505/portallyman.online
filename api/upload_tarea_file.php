<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
require_once('../includes/tareas_catalogo.php');
require_once('../includes/drive_uploader.php');
api_require_tareas();

header('Content-Type: application/json');

$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$origen = $_POST['origen'] ?? 'asignado';
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de tarea inválido.']); exit;
}
if (!in_array($origen, ['admin', 'asignado'], true)) $origen = 'asignado';

$st = mysqli_prepare($conn,
    "SELECT t.*, u.soporte_de_id AS asignado_soporte_de
       FROM tareas t LEFT JOIN usuarios u ON u.id = t.asignado_id
      WHERE t.id = ? LIMIT 1");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$tarea = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
mysqli_stmt_close($st);

$permiso = ($origen === 'admin') ? tk_puede_editar($tarea) : tk_puede_entregar($tarea);
if (!$permiso['ok']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $permiso['error']]); exit;
}

$st = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM tareas_adjuntos WHERE tarea_id=?");
mysqli_stmt_bind_param($st, 'i', $id);
mysqli_stmt_execute($st);
$n = (int)(mysqli_fetch_assoc(mysqli_stmt_get_result($st))['n'] ?? 0);
mysqli_stmt_close($st);
if ($n >= tk_max_adjuntos()) {
    echo json_encode(['success' => false,
        'error' => 'Ya hay ' . tk_max_adjuntos() . ' archivos en esta tarea, que es el máximo.']); exit;
}

if (empty($_FILES['file']) || !isset($_FILES['file']['error'])) {
    echo json_encode(['success' => false, 'error' => 'No se recibió ningún archivo.']); exit;
}
$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $codes = [
        UPLOAD_ERR_INI_SIZE  => 'El archivo excede el tamaño permitido por el servidor.',
        UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño permitido.',
        UPLOAD_ERR_PARTIAL   => 'La subida quedó incompleta. Reintenta.',
        UPLOAD_ERR_NO_FILE   => 'No se recibió ningún archivo.',
    ];
    echo json_encode(['success' => false, 'error' => $codes[$file['error']] ?? 'Error al subir el archivo.']); exit;
}
if ($file['size'] <= 0)              { echo json_encode(['success'=>false,'error'=>'El archivo está vacío.']); exit; }
if ($file['size'] > tk_max_bytes())  { echo json_encode(['success'=>false,'error'=>'El archivo supera los 4 MB.']); exit; }
if (!is_uploaded_file($file['tmp_name'])) { echo json_encode(['success'=>false,'error'=>'Archivo no válido.']); exit; }

$tipos = sg_tipos_permitidos();
$ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!isset($tipos[$ext])) {
    echo json_encode(['success' => false,
        'error' => 'El tipo de archivo ".' . htmlspecialchars($ext) . '" no está permitido.']); exit;
}
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mime, $tipos[$ext], true)) {
    echo json_encode(['success' => false, 'error' => 'El contenido del archivo no coincide con su extensión.']); exit;
}

$base = pathinfo($file['name'], PATHINFO_FILENAME);
$base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $base);
$base = trim(preg_replace('/_+/', '_', $base), '_.');
if ($base === '') $base = 'evidencia';
if (mb_strlen($base) > 90) $base = mb_substr($base, 0, 90);
$nombre = $base . '.' . $ext;

$carpeta = tk_carpeta_drive();
$res     = sg_drive_subir($carpeta, $nombre, $mime, $file['tmp_name']);

if (!empty($res['ok'])) {
    $nombreFinal = $res['nombre'] ?? $nombre;
    $fileId = $res['fileId'] ?? null;
    $url    = $res['url'] ?? null;
    $local  = null;  $estado = 'subido';  $errMsg = null;  $aviso = null;
} else {
    $nombreFinal = $nombre;
    $fileId = null;  $url = null;
    $local  = sg_guardar_local($carpeta, $nombre, $file['tmp_name']);
    $estado = $local ? 'pendiente' : 'error';
    $errMsg = mb_substr($res['error'] ?? 'Fallo desconocido de Drive.', 0, 255);
    $aviso  = 'No se pudo subir a Drive: ' . mb_substr($res['error'] ?? '', 0, 180)
            . ($local ? ' (guardado en el servidor, se subirá luego).' : '.');
}

$peso     = (int)$file['size'];
$ronda    = ($origen === 'asignado') ? (int)$tarea['entregas_count'] + 1 : 1;
$subidoP  = $_SESSION['user_name'] ?? '';
$subidoId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

mysqli_begin_transaction($conn);
try {
    $ins = mysqli_prepare($conn,
        "INSERT INTO tareas_adjuntos
            (tarea_id, nombre_archivo, mime, peso_bytes, drive_file_id, drive_url,
             ruta_local, estado, error_msg, origen, entrega_nro, subido_por, subido_por_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($ins, 'ississssssisi',
        $id, $nombreFinal, $mime, $peso, $fileId, $url,
        $local, $estado, $errMsg, $origen, $ronda, $subidoP, $subidoId);
    if (!mysqli_stmt_execute($ins)) throw new Exception(mysqli_stmt_error($ins));
    $adjId = (int)mysqli_insert_id($conn);
    mysqli_stmt_close($ins);

    tk_historial($conn, $id, 'adjunto',
        ($origen === 'admin' ? 'Material de referencia: ' : 'Evidencia (ronda ' . $ronda . '): ')
      . $nombreFinal . ($estado !== 'subido' ? ' [' . $estado . ']' : ''));

    mysqli_commit($conn);
    echo json_encode([
        'success' => true,
        'aviso'   => $aviso,
        'adjunto' => [
            'id'             => $adjId,
            'nombre_archivo' => $nombreFinal,
            'mime'           => $mime,
            'peso_bytes'     => $peso,
            'drive_file_id'  => $fileId,
            'drive_url'      => $url,
            'ruta_local'     => $local,
            'estado'         => $estado,
            'error_msg'      => $errMsg,
            'origen'         => $origen,
            'entrega_nro'    => $ronda,
            'subido_por'     => $subidoP,
            'subido_por_id'  => $subidoId,
        ],
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'error' => 'No se pudo registrar el adjunto: ' . $e->getMessage()]);
}
