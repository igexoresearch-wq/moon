<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$craft=(int)($_GET['craft']??0);
if(!in_array($craft,[3,6],true)){http_response_code(400);exit;}
$prefix=$craft===3?'cm':'lm';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $origin=$_SERVER['HTTP_ORIGIN']??'';$host=preg_replace('/:\d+$/','',$_SERVER['HTTP_HOST']??'');
 if(!$origin || parse_url($origin,PHP_URL_HOST)!==$host){http_response_code(403);exit;}
 $v=json_decode(file_get_contents('php://input'),true);
 $unit=$v['unit']??'';$command=$v['command']??'';
 if(!in_array($unit,['radar','responder'],true)||!in_array($command,['on','off'],true)){http_response_code(400);exit;}
 $key=$prefix.($unit==='radar'?'_rendezvous_radar':'_radar_responder');
 $conn->begin_transaction();
 try{
  $q=$conn->prepare('SELECT state FROM reliability_components WHERE component_key=? FOR UPDATE');
  $q->bind_param('s',$key);$q->execute();$row=$q->get_result()->fetch_assoc();
  if(!$row)throw new RuntimeException('Radar component missing');
  $state=(int)$row['state'];
  $target=$command==='on'?1:4;
  if(($state===1&&$target===4)||($state===4&&$target===1)){
   $q=$conn->prepare('UPDATE reliability_components SET state=?,auto_shed=0,state_since=NOW() WHERE component_key=?');
   $q->bind_param('is',$target,$key);$q->execute();
  }
  $conn->commit();
 }catch(Throwable $e){$conn->rollback();http_response_code(500);exit;}
}
$keys=[$prefix.'_rendezvous_radar',$prefix.'_radar_responder'];
$q=$conn->prepare('SELECT component_key,state FROM reliability_components WHERE component_key IN (?,?)');
$q->bind_param('ss',$keys[0],$keys[1]);$q->execute();$states=$q->get_result()->fetch_all(MYSQLI_ASSOC);
$power=['radar'=>false,'responder'=>false];foreach($states as $s)$power[str_ends_with($s['component_key'],'_responder')?'responder':'radar']=(int)$s['state']===1;
$q=$conn->prepare('SELECT register_key,numeric_value,valid,updated_tick FROM computer_registers WHERE craft_id=?');
$q->bind_param('i',$craft);$q->execute();$rows=$q->get_result()->fetch_all(MYSQLI_ASSOC);
$registers=[];foreach($rows as $row)if(str_starts_with($row['register_key'],'RENDEZVOUS_'))$registers[$row['register_key']]=['value'=>$row['valid']?(float)$row['numeric_value']:null,'valid'=>(bool)$row['valid'],'tick'=>$row['updated_tick']===null?null:(int)$row['updated_tick']];
echo json_encode(['power'=>$power,'registers'=>$registers],JSON_THROW_ON_ERROR);
