<?php
require_once('../includes/db.php');
require_once('../includes/auth.php');
api_require_report();
header('Content-Type: application/json; charset=utf-8');

if (isset($_GET['lista'])) {
    $esCoordinador = ($_SESSION['user_rol'] ?? '') === 'Coordinador';
    $sql = "SELECT c.id, c.codigo, c.nombre
              FROM colaboradores c
             WHERE c.activo=1" . ($esCoordinador ? " AND c.coordinador_id=?" : "") . "
             ORDER BY c.nombre ASC";
    $stmt = mysqli_prepare($conn, $sql);
    if ($esCoordinador) {
        $coordinadorId = (int)$_SESSION['user_id'];
        mysqli_stmt_bind_param($stmt, 'i', $coordinadorId);
    }
    echo json_encode(['success' => true, 'data' => rpt_all($stmt)], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = isset($_GET['colaborador_id']) ? (int)$_GET['colaborador_id'] : 0;
if ($id <= 0) { http_response_code(422); echo json_encode(['success'=>false,'error'=>'Selecciona un tallyman válido.']); exit; }
function rpt_all($stmt) { mysqli_stmt_execute($stmt); $result=mysqli_stmt_get_result($stmt); $rows=[]; while($row=mysqli_fetch_assoc($result)) $rows[]=$row; mysqli_stmt_close($stmt); return $rows; }
function rpt_query($conn, $sql, $id) { $stmt=mysqli_prepare($conn,$sql); mysqli_stmt_bind_param($stmt,'i',$id); return rpt_all($stmt); }
function rpt_cumple($fecha) { if(!$fecha)return null; try{$n=new DateTime($fecha);$h=new DateTime('today');$p=new DateTime($h->format('Y').'-'.$n->format('m-d'));if($p<$h)$p->modify('+1 year');return (int)$h->diff($p)->format('%a');}catch(Exception $e){return null;} }

$perfil=rpt_query($conn,"SELECT c.id,c.codigo,c.nombre,c.funcion_principal,c.cuadrilla,c.fecha_nacimiento,c.fecha_ingreso,c.activo,c.coordinador_id,u.nombre coordinador_nombre FROM colaboradores c LEFT JOIN usuarios u ON u.id=c.coordinador_id WHERE c.id=? LIMIT 1",$id);
if(!$perfil){http_response_code(404);echo json_encode(['success'=>false,'error'=>'No se encontró el tallyman seleccionado.']);exit;}
$perfil=$perfil[0];
if(($_SESSION['user_rol']??'')==='Coordinador' && (int)$perfil['coordinador_id']!==(int)$_SESSION['user_id']){http_response_code(403);echo json_encode(['success'=>false,'error'=>'Solo puedes consultar al personal que tienes asignado.']);exit;}
$perfil['id']=(int)$perfil['id'];$perfil['activo']=(int)$perfil['activo'];$perfil['coordinador_id']=$perfil['coordinador_id']===null?null:(int)$perfil['coordinador_id'];$perfil['dias_para_cumpleanos']=rpt_cumple($perfil['fecha_nacimiento']);$perfil['dias_desde_ingreso']=null;
if($perfil['fecha_ingreso'])try{$perfil['dias_desde_ingreso']=max(0,(int)(new DateTime($perfil['fecha_ingreso']))->diff(new DateTime('today'))->format('%r%a'));}catch(Exception $e){}

$incidencias=rpt_query($conn,"SELECT id,punto_mejorar,competencia,impacto,fecha,turno,zona_trabajo,detalle FROM incidencias WHERE colaborador_id=? ORDER BY fecha DESC,id DESC",$id);
$sanciones=rpt_query($conn,"SELECT id,tipo_sancion,impacto,punto_mejorar,fecha_incidencia,fecha_inicio,fecha_fin,dias_sancion,zona_trabajo,aplicado_por FROM sanciones_disciplinarias WHERE colaborador_id=? ORDER BY fecha_incidencia DESC,id DESC",$id);
$propuestas=rpt_query($conn,"SELECT id,detalle,puntaje,puntaje_comentario,puntaje_por,puntaje_at,created_at FROM sugerencias_tallyman WHERE colaborador_id=? AND canal='propuesta' AND puntaje IS NOT NULL ORDER BY puntaje_at DESC,id DESC",$id);
$reconocimientos=rpt_query($conn,"SELECT id,competencia,concepto,estado,fecha,detalle,supervisor,aprobado_at FROM reconocimientos WHERE colaborador_id=? ORDER BY fecha DESC,id DESC",$id);
$charlas=rpt_query($conn,"SELECT a.id,a.tema,a.tipo_reunion,a.fecha,a.hora,a.lugar,a.capacitador,p.estado FROM asistencias_participantes p JOIN asistencias_preoperativas a ON a.id=p.asistencia_id WHERE p.colaborador_id=? AND p.estado IN ('asistio','tardanza') ORDER BY a.fecha DESC,a.hora DESC,a.id DESC",$id);
$capacitaciones=rpt_query($conn,"SELECT c.id,c.titulo,c.fecha,c.hora,c.duracion_min,c.lugar,c.estado estado_capacitacion,a.estado estado_asistencia FROM capacitaciones_asistentes a JOIN capacitaciones c ON c.id=a.capacitacion_id WHERE a.colaborador_id=? AND a.estado IN ('asistio','tardanza') ORDER BY c.fecha DESC,c.hora DESC,c.id DESC",$id);
$aprobados=count(array_filter($reconocimientos,fn($x)=>$x['estado']==='aprobado'));
$tardanzasCharlas=count(array_filter($charlas,fn($x)=>$x['estado']==='tardanza'));
echo json_encode(['success'=>true,'perfil'=>$perfil,'resumen'=>['incidencias'=>count($incidencias),'sanciones'=>count($sanciones),'propuestas'=>count($propuestas),'reconocimientos_aprobados'=>$aprobados,'charlas'=>count($charlas),'tardanzas_charlas'=>$tardanzasCharlas,'capacitaciones'=>count($capacitaciones)],'historial'=>compact('incidencias','sanciones','propuestas','reconocimientos','charlas','capacitaciones')],JSON_UNESCAPED_UNICODE);
