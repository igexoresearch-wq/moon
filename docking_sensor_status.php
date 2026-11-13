<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
require_once __DIR__.'/docking_sensor.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$craft=(int)($_GET['craft']??0);
if(!in_array($craft,[3,6],true)){http_response_code(400);echo json_encode(['error'=>'INVALID DOCKING PROBE']);exit;}
try{
 $key=$craft===3?'cm_docking_probe':'lm_docking_probe';
 $q=$conn->prepare('SELECT state FROM reliability_components WHERE component_key=?');
 $q->bind_param('s',$key);$q->execute();$probe=$q->get_result()->fetch_assoc();
 if(!$probe){http_response_code(503);echo json_encode(['error'=>'DOCKING PROBE NOT INSTALLED']);exit;}
 $contact=$conn->query('SELECT * FROM docking_contact WHERE id=1')->fetch_assoc();
 if(!$contact)throw new RuntimeException('Docking state missing');
 $status=(int)$probe['state']===1?'ONLINE':'PROBE FAILED / OFFLINE';
 $out=['status'=>$status,'available'=>(int)$probe['state']===1,
   'docked'=>(int)$contact['docked']===1,'last_event'=>$contact['last_event'],
   'last_speed_mps'=>$contact['last_speed_mps']===null?null:(float)$contact['last_speed_mps'],
   'last_damage_cm'=>(int)$contact['last_damage_cm'],'last_damage_lm'=>(int)$contact['last_damage_lm']];
 if($out['available']){
  $rs=$conn->query('SELECT * FROM objects WHERE id IN (3,6) ORDER BY id')->fetch_all(MYSQLI_ASSOC);
  $objects=[];foreach($rs as $r)$objects[(int)$r['id']]=$r;
  if(!isset($objects[3],$objects[6]))throw new RuntimeException('Spacecraft missing');
  $geometry=moonDockGeometry($objects[3],$objects[6]);
  $out+=$geometry;
  if($out['docked'])$out['condition']='DOCKED / LATCHED';
  elseif((int)$objects[3]['active']!==1||(int)$objects[6]['active']!==1)$out['condition']='SPACECRAFT INACTIVE';
  elseif((int)$objects[3]['dependent']===1||(int)$objects[6]['dependent']===1)$out['condition']='CRAFT ATTACHED / INHIBITED';
  elseif(!$geometry['axes_aligned'])$out['condition']='PORT AXIS MISALIGNED';
  elseif($geometry['port_gap_m']>1.)$out['condition']='PORT RANGE > 1 M';
  elseif($geometry['relative_speed_mps']>1.)$out['condition']='CLOSING SPEED EXCESS';
  elseif($geometry['closing_mps']<-.05)$out['condition']='RECEDING';
  else $out['condition']='DOCKING ENVELOPE';
 }
 echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(Throwable $e){
 error_log('Moon docking probe: '.$e->getMessage());
 http_response_code(503);echo json_encode(['error'=>'DOCKING SENSOR UNAVAILABLE']);
}
