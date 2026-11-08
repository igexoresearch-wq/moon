<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$craft=(int)($_GET['craft']??$_POST['craft']??0);
$system=(string)($_GET['system']??$_POST['system']??'');
if(!in_array($craft,[3,6],true)||!in_array($system,['rcs','imu'],true)){http_response_code(400);exit;}
$key=($craft===3?'cm':'lm').($system==='rcs'?'_rcs_cluster':'_imu');
if($_SERVER['REQUEST_METHOD']==='POST'){
 $origin=$_SERVER['HTTP_ORIGIN']??'';
 $host=preg_replace('/:\d+$/','',$_SERVER['HTTP_HOST']??'');
 if(!$origin||parse_url($origin,PHP_URL_HOST)!==$host){http_response_code(403);exit;}
 $data=json_decode(file_get_contents('php://input'),true);
 $command=$data['command']??null;
 if(!in_array($command,['on','off'],true)){http_response_code(400);exit;}
 $conn->begin_transaction();
 try{
  $q=$conn->prepare('SELECT state FROM reliability_components WHERE component_key=? FOR UPDATE');
  $q->bind_param('s',$key);$q->execute();$row=$q->get_result()->fetch_assoc();
  if(!$row)throw new RuntimeException('Component missing');
  $state=(int)$row['state'];
  if($command==='off' && $state===1)$new=4;
  elseif($command==='on' && $state===4)$new=1;
  else $new=$state; // 2 / 3 are faults, not resettable by switch
  if($new!==$state){
   $q=$conn->prepare('UPDATE reliability_components SET state=?,auto_shed=0,state_since=NOW() WHERE component_key=?');
   $q->bind_param('is',$new,$key);$q->execute();
   if($system==='rcs'){
    // Only healthy/off thrusters follow the cluster switch; 2/3 faults persist.
    $prefix=$craft===3?'cm_rcs_%':'lm_rcs_%';
    $target=$new===4?4:1;$prior=$new===4?1:4;
    $jets=$conn->prepare("UPDATE reliability_components SET state=?,state_since=NOW() WHERE component_key LIKE ? AND component_key<>? AND state=?");
    $jets->bind_param('issi',$target,$prefix,$key,$prior);
    $jets->execute();
   }
  }
  $conn->commit();
 }catch(Throwable $e){$conn->rollback();http_response_code(500);exit;}
}
$q=$conn->prepare('SELECT state FROM reliability_components WHERE component_key=?');
$q->bind_param('s',$key);$q->execute();$v=$q->get_result()->fetch_assoc();
if(!$v){http_response_code(404);exit;}
echo json_encode(['on'=>(int)$v['state']===1],JSON_THROW_ON_ERROR);
