<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$n=(int)($_GET['cell']??0);
if(!in_array($n,[1,2,3],true)){http_response_code(400);exit;}
$key='sm_fuel_cell_'.$n;
if($_SERVER['REQUEST_METHOD']==='POST'){
 $origin=$_SERVER['HTTP_ORIGIN']??'';
 $host=preg_replace('/:\d+$/','',$_SERVER['HTTP_HOST']??'');
 if(!$origin || parse_url($origin,PHP_URL_HOST)!==$host){http_response_code(403);exit;}
 $payload=json_decode(file_get_contents('php://input'),true);
 $command=$payload['command']??'';
 if(!in_array($command,['on','off'],true)){http_response_code(400);exit;}
 $conn->begin_transaction();
 try{
  $q=$conn->prepare('SELECT state FROM reliability_components WHERE component_key=? FOR UPDATE');
  $q->bind_param('s',$key);$q->execute();$row=$q->get_result()->fetch_assoc();
  if(!$row)throw new RuntimeException('Fuel cell not installed');
  $old=(int)$row['state'];
  $new=$old;
  if($old===1 && $command==='off')$new=4;
  if($old===4 && $command==='on'){
   // Cannot restart fuel cells once the SM is detached.
   $sm=$conn->query('SELECT dependency,dependent,active FROM objects WHERE id=4')->fetch_assoc();
   if($sm && (int)$sm['dependent']===1 && (int)$sm['dependency']===3 && (int)$sm['active']===1)$new=1;
  }
  if($old!==$new){
   $q=$conn->prepare('UPDATE reliability_components SET state=?,state_since=NOW() WHERE component_key=?');
   $q->bind_param('is',$new,$key);$q->execute();
  }
  $conn->commit();
 }catch(Throwable $e){$conn->rollback();http_response_code(500);exit;}
}
$q=$conn->prepare('SELECT state,amps FROM reliability_components WHERE component_key=?');
$q->bind_param('s',$key);$q->execute();$row=$q->get_result()->fetch_assoc();
if(!$row){http_response_code(404);exit;}
echo json_encode(['on'=>(int)$row['state']===1,'output_amps'=>(int)$row['state']===1?(float)$row['amps']:0.0],JSON_THROW_ON_ERROR);
