<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
// Development alignment shortcut; future optical/star alignment replaces it.
// Same-origin check prevents cross-site button-triggered state changes.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
$origin=$_SERVER['HTTP_ORIGIN']??'';
$host=$_SERVER['HTTP_HOST']??'';
if($origin!=='' && parse_url($origin,PHP_URL_HOST)!==preg_replace('/:\\d+$/','',$host)){http_response_code(403);exit;}
$data=json_decode(file_get_contents('php://input'),true);
$id=(int)($data['craft']??0);
if(!in_array($id,[3,6],true)){http_response_code(400);exit;}
$locked=$conn->query("SELECT GET_LOCK('moon_v2_execute',5) AS acquired")->fetch_assoc();
if((int)$locked['acquired']!==1){http_response_code(503);exit;}
try{
 $conn->begin_transaction();
 $q=$conn->prepare("SELECT pitch,yaw,roll FROM objects WHERE id=? FOR UPDATE");
 $q->bind_param('i',$id);$q->execute();$row=$q->get_result()->fetch_assoc();
 if(!$row)throw new RuntimeException('Craft not found');
 $clock=$conn->query("SELECT tick FROM sim_clock WHERE id=1 FOR UPDATE")->fetch_assoc();
 $tick=(int)$clock['tick'];
 $q=$conn->prepare("UPDATE imu SET pitch_deg=?,yaw_deg=?,roll_deg=?,last_tick=?,aligned_at_tick=? WHERE object_id=?");
 $pitch=(float)$row['pitch'];$yaw=(float)$row['yaw'];$roll=(float)$row['roll'];
 $q->bind_param('dddiii',$pitch,$yaw,$roll,$tick,$tick,$id);
 $q->execute();
 if(!$q->affected_rows) {
  $check=$conn->prepare("SELECT object_id FROM imu WHERE object_id=?");
  $check->bind_param('i',$id);$check->execute();
  if(!$check->get_result()->fetch_assoc())throw new RuntimeException('IMU not installed');
 }
 $conn->commit();
 echo json_encode(['ok'=>true],JSON_THROW_ON_ERROR);
}catch(Throwable $e){$conn->rollback();http_response_code(500);echo json_encode(['error'=>'Alignment unavailable']);}
finally{$conn->query("SELECT RELEASE_LOCK('moon_v2_execute')");}
