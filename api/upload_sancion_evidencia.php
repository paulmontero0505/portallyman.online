<?php
require_once('../includes/auth.php'); api_require_admin(); header('Content-Type: application/json');
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) { echo json_encode(['success'=>false,'error'=>'No se recibió la evidencia.']); exit; }
$f=$_FILES['file']; if ($f['size'] > 8*1024*1024) { echo json_encode(['success'=>false,'error'=>'La evidencia supera 8 MB.']); exit; }
$ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION)); if (!in_array($ext,['pdf','jpg','jpeg','png'],true)) { echo json_encode(['success'=>false,'error'=>'Formato permitido: PDF, JPG o PNG.']); exit; }
$dir=__DIR__.'/../uploads/sanciones'; if (!is_dir($dir)) mkdir($dir,0755,true); $name='sancion_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$ext;
if (!move_uploaded_file($f['tmp_name'],$dir.'/'.$name)) { echo json_encode(['success'=>false,'error'=>'No se pudo guardar la evidencia.']); exit; }
echo json_encode(['success'=>true,'path'=>'uploads/sanciones/'.$name]);
