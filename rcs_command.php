<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
require_once __DIR__.'/reliability.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
$origin=$_SERVER['HTTP_ORIGIN']??'';
$host=preg_replace('/:\d+$/','',$_SERVER['HTTP_HOST']??'');
if(!$origin || parse_url($origin,PHP_URL_HOST)!==$host){http_response_code(403);exit;}
$data=json_decode(file_get_contents('php://input'),true);
$craft=(int)($data['craft']??0);$axis=(string)($data['axis']??'');$sign=(int)($data['sign']??0);
if(!in_array($craft,[3,6],true)||!in_array($axis,['pitch','yaw','roll','forward','lateral','vertical'],true)||!in_array($sign,[-1,1],true)){http_response_code(400);exit;}
$label=match($axis){
 'pitch'=>$sign===1?'pitch_up':'pitch_down',
 'yaw'=>$sign===1?'yaw_right':'yaw_left',
 'roll'=>$sign===1?'roll_right':'roll_left',
 'forward'=>$sign===1?'translate_forward':'translate_back',
 'lateral'=>$sign===1?'translate_right':'translate_left',
 'vertical'=>$sign===1?'translate_up':'translate_down'
};
$key=($craft===3?'cm':'lm').'_rcs_'.$label;
try{
 $conn->begin_transaction();
 $q=$conn->prepare('SELECT * FROM objects WHERE id=? FOR UPDATE');$q->bind_param('i',$craft);$q->execute();$o=$q->get_result()->fetch_assoc();
 if(!$o || !(int)$o['active']){ $conn->rollback();echo json_encode(['fired'=>false]);exit;}
 // When docked to SM, its RCS is the active attitude/translation system.
 $system=$craft===3?4:6;
 $q=$conn->prepare('SELECT * FROM rcs WHERE craft_id=? FOR UPDATE');$q->bind_param('i',$system);$q->execute();$rcs=$q->get_result()->fetch_assoc();
 if(!$rcs || !(int)$rcs['enabled']){ $conn->rollback();echo json_encode(['fired'=>false]);exit;}
 $reliability=moonReliabilityCommand($conn,$key,'RCS pulse');
 if(!$reliability['allowed']){$conn->commit();echo json_encode(['fired'=>false]);exit;}
 $dt=(float)$rcs['pulse_seconds'];$isp=(float)$rcs['isp_s'];$thrust=(float)$rcs['thrust_n'];
 $used=$thrust*$dt/($isp*9.80665);$fuelShare=0.4;$oxidizerShare=0.6;
 if($dt<=0||$isp<=0||$thrust<=0||$rcs['fuel_kg']<$used*$fuelShare||$rcs['oxidizer_kg']<$used*$oxidizerShare){
  $conn->commit();echo json_encode(['fired'=>false]);exit;
 }
 $pitch=deg2rad((float)$o['pitch']);$yaw=deg2rad((float)$o['yaw']);$roll=deg2rad((float)$o['roll']);
 $forward=[cos($pitch)*cos($yaw),cos($pitch)*sin($yaw),sin($pitch)];
 $right0=[-sin($yaw),cos($yaw),0.0];$up0=[-sin($pitch)*cos($yaw),-sin($pitch)*sin($yaw),cos($pitch)];
 $right=[];$up=[];for($i=0;$i<3;$i++){$right[$i]=$right0[$i]*cos($roll)+$up0[$i]*sin($roll);$up[$i]=$up0[$i]*cos($roll)-$right0[$i]*sin($roll);}
 $mass=max(1,(float)$o['dry_mass']+(float)$rcs['fuel_kg']+(float)$rcs['oxidizer_kg']);
 if($craft===3){$mass+=6100;} // approximate attached SM mass for this development model
 if(in_array($axis,['pitch','yaw','roll'],true)){
  $column=$axis.'_rate';$rate=(float)$o[$column]+$sign*$thrust*(float)$rcs['torque_arm_m']*$dt/(float)$rcs['inertia_kg_m2']*180/M_PI;
  $q=$conn->prepare("UPDATE objects SET $column=? WHERE id=?");$q->bind_param('di',$rate,$craft);$q->execute();
 }else{
  $vector=match($axis){'forward'=>$forward,'lateral'=>$right,default=>$up};
  $dv=$sign*$thrust*$dt/$mass;
  $dx=(float)$o['delta_x']+$dv*$vector[0];$dy=(float)$o['delta_y']+$dv*$vector[1];$dz=(float)$o['delta_z']+$dv*$vector[2];
  $q=$conn->prepare('UPDATE objects SET delta_x=?,delta_y=?,delta_z=? WHERE id=?');
  $q->bind_param('dddi',$dx,$dy,$dz,$craft);$q->execute();
 }
 $f=(float)$rcs['fuel_kg']-$used*$fuelShare;$ox=(float)$rcs['oxidizer_kg']-$used*$oxidizerShare;
 $q=$conn->prepare('UPDATE rcs SET fuel_kg=?,oxidizer_kg=? WHERE id=?');$id=(int)$rcs['id'];$q->bind_param('ddi',$f,$ox,$id);$q->execute();
 $conn->commit();
 echo json_encode(['fired'=>true,'fuel_kg'=>round($f,2),'oxidizer_kg'=>round($ox,2)]);
}catch(Throwable $e){$conn->rollback();error_log('Moon RCS: '.$e->getMessage());http_response_code(500);echo json_encode(['error'=>'RCS unavailable']);}
