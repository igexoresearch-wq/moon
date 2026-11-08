<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$id=(int)($_GET['craft']??0);
if(!in_array($id,[3,6],true)){http_response_code(400);echo json_encode(['error'=>'Invalid craft']);exit;}
$q=$conn->prepare("SELECT pitch_deg,yaw_deg,roll_deg,reference_pitch_deg,reference_yaw_deg,reference_roll_deg,last_tick FROM imu WHERE object_id=?");
$q->bind_param('i',$id);$q->execute();$row=$q->get_result()->fetch_assoc();
if(!$row){http_response_code(404);echo json_encode(['error'=>'IMU not installed']);exit;}
foreach($row as $key=>$value){$row[$key]=$key==='last_tick'?(int)$value:(float)$value;}
echo json_encode($row,JSON_THROW_ON_ERROR);
