<?php
require_once __DIR__.'/common.php';
$observer=(int)($_GET['observer']??3);
if(!in_array($observer,[3,6],true)){http_response_code(400);exit;}
$rows=$conn->query("SELECT id,name,radius_length,x,y,z,pitch,roll,yaw,pitch_rate,roll_rate,yaw_rate,delta_x,delta_y,delta_z,dependency,dependent,active FROM objects WHERE active=1 OR id=".$observer)->fetch_all(MYSQLI_ASSOC);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(['observer'=>$observer,'objects'=>$rows],JSON_THROW_ON_ERROR);
