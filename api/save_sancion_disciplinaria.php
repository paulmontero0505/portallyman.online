<?php
require_once('../includes/db.php'); require_once('../includes/auth.php'); api_require_admin(); header('Content-Type: application/json');
$d=json_decode(file_get_contents('php://input'),true); $id=(int)($d['id']??0); $ini=trim($d['fecha_inicio']??''); $fin=trim($d['fecha_fin']??''); $evid=trim($d['evidencia_path']??'');
if(!$id||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$ini)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$fin)||$fin<$ini){echo json_encode(['success'=>false,'error'=>'Indica un periodo válido.']);exit;}
$dias=(new DateTime($ini))->diff(new DateTime($fin))->days+1;
$s=mysqli_prepare($conn,'UPDATE sanciones_disciplinarias SET fecha_inicio=?,fecha_fin=?,dias_sancion=?,evidencia_path=NULLIF(?,\'\') WHERE id=?'); mysqli_stmt_bind_param($s,'ssisi',$ini,$fin,$dias,$evid,$id); $ok=mysqli_stmt_execute($s);$err=mysqli_stmt_error($s);mysqli_stmt_close($s);
echo json_encode(['success'=>$ok,'error'=>$ok?null:($err?:'No se pudo actualizar.')]);
