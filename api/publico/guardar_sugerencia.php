<?php
require_once(__DIR__ . '/../../includes/db.php');
require_once(__DIR__ . '/../../includes/sugerencias_catalogo.php');
require_once(__DIR__ . '/../../includes/sugerencias_adjuntos.php');
require_once(__DIR__ . '/../../includes/drive_uploader.php');

header('Content-Type: application/json');

if (!empty($_POST)) {
    $data = $_POST;
} else {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        echo json_encode(['success' => false, 'error' => 'Payload inválido.']); exit;
    }
}

$dni     = trim($data['dni'] ?? '');
$canal   = trim($data['canal'] ?? '');
$detalle = trim($data['detalle'] ?? '');

if (!preg_match('/^\d{8}$/', $dni)) {
    echo json_encode(['success' => false, 'error' => 'Ingresa un DNI válido (8 dígitos).']); exit;
}
if (!array_key_exists($canal, sg_canales())) {
    echo json_encode(['success' => false, 'error' => 'Canal inválido.']); exit;
}

if ($canal === 'encuesta') {
    $respuestas = json_decode($detalle, true);
    $val = sg_encuesta_validar($respuestas);
    if (!$val['ok']) {
        echo json_encode(['success' => false, 'error' => $val['error']]); exit;
    }
    $detalle = json_encode($respuestas, JSON_UNESCAPED_UNICODE);
} else {
    if ($detalle === '') {
        echo json_encode(['success' => false, 'error' => 'Escribe el detalle antes de enviar.']); exit;
    }
    if (mb_strlen($detalle) > 2000) {
        echo json_encode(['success' => false, 'error' => 'El detalle es demasiado largo.']); exit;
    }
}

$archivos = sg_normalizar_archivos($_FILES['archivos'] ?? null);
if (count($archivos) > SG_MAX_ARCHIVOS) {
    echo json_encode(['success' => false, 'error' => 'Máximo ' . SG_MAX_ARCHIVOS . ' archivos por envío.']); exit;
}
foreach ($archivos as $a) {
    $err = sg_validar_archivo($a);
    if ($err !== null) { echo json_encode(['success' => false, 'error' => $err]); exit; }
}

$stmt = mysqli_prepare($conn,
    "SELECT id, nombre, funcion_principal FROM colaboradores WHERE dni = ? AND activo = 1 LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $dni);
mysqli_stmt_execute($stmt);
$colab = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$colab) {
    echo json_encode(['success' => false, 'error' => 'No se encontró un colaborador activo con ese DNI.']); exit;
}

$esAnonimo = ($canal === 'observacion');
$colId     = $esAnonimo ? null : (int)$colab['id'];
$colNombre = $esAnonimo ? null : $colab['nombre'];
$colCargo  = $esAnonimo ? null : $colab['funcion_principal'];

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO sugerencias_tallyman (canal, colaborador_id, colaborador_nombre, colaborador_cargo, detalle)
     VALUES (?, ?, ?, ?, ?)"
);
mysqli_stmt_bind_param($stmt, 'sisss', $canal, $colId, $colNombre, $colCargo, $detalle);
$ok    = mysqli_stmt_execute($stmt);
$err   = mysqli_stmt_error($stmt);
$newId = (int)mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

if (!$ok) {
    echo json_encode(['success' => false, 'error' => $err ?: 'No se pudo enviar.']); exit;
}

$carpeta   = sg_carpeta_drive($canal);
$sello     = date('Y-m-d_H-i-s');
$subidos   = 0;
$pendiente = 0;

foreach ($archivos as $i => $a) {
    $nombre = sg_nombre_archivo($sello, $i, $a['name']);
    $mime   = sg_mime_real($a['tmp_name']);

    $res = sg_drive_subir($carpeta, $nombre, $mime, $a['tmp_name']);

    if (!empty($res['ok'])) {
        sg_registrar_adjunto($conn, $newId, $res['nombre'], $mime, (int)$a['size'],
            $res['fileId'], $res['url'], null, 'subido', null);
        $subidos++;
    } else {
        $local  = sg_guardar_local($carpeta, $nombre, $a['tmp_name']);
        $estado = $local ? 'pendiente' : 'error';
        sg_registrar_adjunto($conn, $newId, $nombre, $mime, (int)$a['size'],
            null, null, $local, $estado, mb_substr($res['error'] ?? '', 0, 255));
        $pendiente++;
    }
}

echo json_encode([
    'success'            => true,
    'id'                 => $newId,
    'adjuntos_subidos'   => $subidos,
    'adjuntos_pendientes'=> $pendiente,
]);
