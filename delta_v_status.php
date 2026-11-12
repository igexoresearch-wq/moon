<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['moon_delta_v_csrf']))$_SESSION['moon_delta_v_csrf']=bin2hex(random_bytes(24));
$craft=(int)($_GET['craft']??0);
if(!in_array($craft,[3,6],true)){http_response_code(400);echo json_encode(['error'=>'INVALID CRAFT']);exit;}
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  $v=json_decode(file_get_contents('php://input'),true);
  if(!is_array($v)||!hash_equals($_SESSION['moon_delta_v_csrf'],(string)($v['csrf']??''))){
   http_response_code(403);echo json_encode(['error'=>'RESET REJECTED']);exit;
  }
  if(($v['action']??null)!=='reset'){http_response_code(422);echo json_encode(['error'=>'UNKNOWN COMMAND']);exit;}
  $conn->begin_transaction();
  try{
   $q=$conn->prepare('SELECT craft_id FROM delta_v_accumulators WHERE craft_id=? FOR UPDATE');
   $q->bind_param('i',$craft);$q->execute();
   if(!$q->get_result()->fetch_assoc())throw new RuntimeException('Accumulator missing');
   $clock=$conn->query('SELECT tick FROM sim_clock WHERE id=1')->fetch_assoc();
   if(!$clock)throw new RuntimeException('Simulation clock missing');
   $tick=(int)$clock['tick'];
   // A reset means the current instrument reading is the new zero point.
   // Do not undo a real engine burn or reset the G-meter.
   $q=$conn->prepare("UPDATE delta_v_accumulators SET accumulated_mps=0,samples_integrated=0,last_sample_tick=?,reset_at_tick=?,status='RESET' WHERE craft_id=?");
   $q->bind_param('iii',$tick,$tick,$craft);$q->execute();
   $conn->commit();
  }catch(Throwable $e){$conn->rollback();throw $e;}
 }
 $q=$conn->prepare('SELECT accumulated_mps,samples_integrated,last_sample_tick,reset_at_tick,status FROM delta_v_accumulators WHERE craft_id=?');
 $q->bind_param('i',$craft);$q->execute();$value=$q->get_result()->fetch_assoc();
 if(!$value){http_response_code(503);echo json_encode(['error'=>'INSTALL delta_v_schema.sql']);exit;}
 $q=$conn->prepare('SELECT sample_tick,g_forward,valid FROM g_meter_samples WHERE craft_id=?');
 $q->bind_param('i',$craft);$q->execute();$g=$q->get_result()->fetch_assoc();
 $clock=$conn->query('SELECT tick FROM sim_clock WHERE id=1')->fetch_assoc();
 $tick=$clock===null?null:(int)$clock['tick'];
 $activeBurn=null;
 if($conn->query("SHOW TABLES LIKE 'dsky_dv_burns'")->fetch_row()){
  $burn=$conn->prepare("SELECT r.program_no,r.register_no,r.field2,m.achieved_mps,m.status
   FROM dsky_registers r JOIN dsky_dv_burns m
   ON m.craft_id=r.craft_id AND m.register_no=r.register_no
   WHERE r.craft_id=? AND r.program_no IN (32,33) AND r.state='running'
   ORDER BY r.register_no LIMIT 1");
  $burn->bind_param('i',$craft);$burn->execute();$row=$burn->get_result()->fetch_assoc();
  if($row){
   $activeBurn=['program'=>(int)$row['program_no'],'register'=>(int)$row['register_no'],
    'achieved_mps'=>(float)$row['achieved_mps'],'target_mps'=>(float)$row['field2']/10.,
    'status'=>(string)$row['status']];
  }
 }
 $valid=$g!==null&&(int)$g['valid']===1&&$tick!==null
  &&$g['sample_tick']!==null&&$tick-(int)$g['sample_tick']<=1;
 echo json_encode([
  'delta_v_mps'=>(float)$value['accumulated_mps'],
  'sample_count'=>(int)$value['samples_integrated'],
  'status'=>$value['status'],
  'axial_g'=>$valid?(float)$g['g_forward']:null,
  'valid_sample'=>$valid,
  'active_dsky_burn'=>$activeBurn,
  'csrf'=>$_SESSION['moon_delta_v_csrf']
 ],JSON_THROW_ON_ERROR);
}catch(Throwable $e){
 error_log('Moon delta-V status: '.$e->getMessage());
 http_response_code(503);echo json_encode(['error'=>'DELTA-V SYSTEM UNAVAILABLE']);
}
