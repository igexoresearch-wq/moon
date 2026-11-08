<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$station=(string)($_GET['console']??$_POST['console']??'');
$ids=match($station){'cmc'=>[3,4],'lmc'=>[6,7],default=>null};
if(!$ids){http_response_code(400);echo json_encode(['error'=>'Unknown station']);exit;}
// Do not disclose component condition/state, type, duration or failure cause.
$where=implode(',',array_map('intval',$ids));
if($_SERVER['REQUEST_METHOD']==='POST'){
 $origin=$_SERVER['HTTP_ORIGIN']??'';
 $host=preg_replace('/:\\d+$/','',$_SERVER['HTTP_HOST']??'');
 if($origin==='' || parse_url($origin,PHP_URL_HOST)!==$host){
  http_response_code(403);echo json_encode(['error'=>'Same-origin request required']);exit;
 }
 $input=json_decode(file_get_contents('php://input'),true);
 if(!is_array($input) || ($input['action']??'')!=='acknowledge'){
  http_response_code(400);echo json_encode(['error'=>'Invalid request']);exit;
 }
 $conn->query("UPDATE reliability_failures f JOIN reliability_components c ON c.id=f.component_id
 SET f.acknowledged=1,f.acknowledged_at=NOW()
 WHERE f.acknowledged=0 AND c.spacecraft_id IN ($where)");
 if($station==='cmc')$conn->query("UPDATE electrical_alarms SET acknowledged=1 WHERE craft_id=3 AND active=1");
}
$rows=$conn->query("SELECT c.id,c.alarm_name,c.component_name,
   (c.state IN (2,3) OR EXISTS(SELECT 1 FROM reliability_failures f WHERE f.component_id=c.id AND f.acknowledged=0)) AS lit
 FROM reliability_components c
 WHERE c.master=1 AND c.spacecraft_id IN ($where) ORDER BY c.id")->fetch_all(MYSQLI_ASSOC);
$alarm=$conn->query("SELECT EXISTS(
 SELECT 1 FROM reliability_failures f JOIN reliability_components c ON c.id=f.component_id
 WHERE f.acknowledged=0 AND c.spacecraft_id IN ($where)
 ) AS pending")->fetch_assoc();
$electric=[];$electricPending=false;
if($station==='cmc'){
 $electric=$conn->query("SELECT alarm_key,active,acknowledged FROM electrical_alarms WHERE craft_id=3 ORDER BY alarm_key")->fetch_all(MYSQLI_ASSOC);
 foreach($electric as $row)if((int)$row['active']===1 && (int)$row['acknowledged']===0)$electricPending=true;
}
$lights=[];
foreach($rows as $row)$lights[]=['label'=>($row['alarm_name']?:$row['component_name']),'lit'=>(bool)$row['lit']];
foreach($electric as $row)$lights[]=['label'=>$row['alarm_key'],'lit'=>(bool)$row['active']];
echo json_encode(['master'=>(bool)$alarm['pending']||$electricPending,'lights'=>$lights],JSON_THROW_ON_ERROR);
