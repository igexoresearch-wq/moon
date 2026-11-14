<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$craft=(int)($_GET['craft']??0);
if(!in_array($craft,[3,6],true)){http_response_code(400);echo json_encode(['error'=>'INVALID CRAFT']);exit;}
try{
 $q=$conn->prepare('SELECT * FROM terminal_rendezvous WHERE craft_id=?');
 $q->bind_param('i',$craft);$q->execute();$row=$q->get_result()->fetch_assoc();
 if(!$row){http_response_code(503);echo json_encode(['error'=>'P36 TRACKER NOT INSTALLED']);exit;}
 $now=$conn->query('SELECT tick FROM sim_clock WHERE id=1')->fetch_assoc();
 $tick=(int)($now['tick']??0);
 $valid=$row['sample_tick']!==null&&$tick-(int)$row['sample_tick']<=3;
 $velocity=$valid?sqrt((float)$row['vx']**2+(float)$row['vy']**2+(float)$row['vz']**2):null;
 echo json_encode([
  'craft'=>$craft,'status'=>(string)$row['status'],
  'sample_tick'=>$row['sample_tick']===null?null:(int)$row['sample_tick'],
  'samples'=>(int)$row['sample_count'],
  'valid'=>$valid&&$row['status']==='TRACKING',
  'range_m'=>$valid?(float)$row['last_range_m']:null,
  'relative_speed_mps'=>$velocity,
  'target_estimated_xyz_m'=>$valid?[(float)$row['target_x'],(float)$row['target_y'],(float)$row['target_z']]:null
 ],JSON_THROW_ON_ERROR);
}catch(Throwable $e){
 error_log('Moon P36 status: '.$e->getMessage());
 http_response_code(503);echo json_encode(['error'=>'P36 TRACKER UNAVAILABLE']);
}
