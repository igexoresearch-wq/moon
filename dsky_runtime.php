<?php
declare(strict_types=1);
require_once __DIR__.'/dsky_rcs.php';
require_once __DIR__.'/propulsion.php';
function moonDskyInstalled(mysqli $db):bool {
 static $available=null;
 if($available!==null)return $available;
 $tables=[];
 $q=$db->query("SHOW TABLES LIKE 'dsky_%'");
 while($r=$q->fetch_row())$tables[]=$r[0];
 return $available=in_array('dsky_programs',$tables,true)&&in_array('dsky_registers',$tables,true);
}


function moonDskyFailure(mysqli $db,int $craft,int $slot,string $reason):void {
 $q=$db->prepare("UPDATE dsky_registers SET state='failed',message=? WHERE craft_id=? AND register_no=?");
 $q->bind_param('sii',$reason,$craft,$slot);$q->execute();
}
function moonDskyEngineOff(mysqli $db,int $id):void {
 $q=$db->prepare('UPDATE propulsion_engines SET command_on=0 WHERE id=?');
 $q->bind_param('i',$id);$q->execute();
}
function moonDskyConfig(array $program):array {
 $config=json_decode((string)$program['config_json'],true,16,JSON_THROW_ON_ERROR);
 if(!is_array($config))throw new RuntimeException('Invalid program configuration');
 return $config;
}
function moonDskyAxisError(float $angle,float $target):float {
 $delta=fmod($target-$angle+540.,360.)-180.;
 return $delta;
}
function moonDskyTargetTick(mysqli $db,int $craft,int $slot,array $reg,array $config):void {
 $axis=(string)($config['axis']??'');
 $axes=$axis==='all'?['pitch','yaw','roll']:[$axis];
 if(array_diff($axes,['pitch','yaw','roll'])){moonDskyFailure($db,$craft,$slot,'UNSUPPORTED AXIS');return;}
 $scale=(float)($config['scale']??0);
 if($scale<=0){moonDskyFailure($db,$craft,$slot,'INVALID SCALE');return;}
 $obj=$db->query('SELECT active,pitch,yaw,roll,pitch_rate,yaw_rate,roll_rate FROM objects WHERE id='.(int)$craft.' FOR UPDATE')->fetch_assoc();
 if(!$obj||(int)$obj['active']!==1){moonDskyFailure($db,$craft,$slot,'CRAFT NOT ACTIVE');return;}
 $finished=true;
 foreach($axes as $index=>$name){
  $field='field'.($index+1);
  if($reg[$field]===null){moonDskyFailure($db,$craft,$slot,'TARGET NOT ENTERED');return;}
  $target=(float)$reg[$field]/$scale;
  $error=moonDskyAxisError((float)$obj[$name],$target);
  $rate=(float)$obj[$name.'_rate'];
  if(abs($error)<=0.5 && abs($rate)<=0.15)continue;
  $finished=false;
  // Stop-distance anticipation; only physical RCS impulses adjust angular rate.
  // One RCS pulse per axis per simulation second at most.
  $system=$craft===3?4:6;
  $q=$db->prepare('SELECT thrust_n,torque_arm_m,inertia_kg_m2,pulse_seconds FROM rcs WHERE craft_id=?');
  $q->bind_param('i',$system);$q->execute();$rcs=$q->get_result()->fetch_assoc();
  if(!$rcs){moonDskyFailure($db,$craft,$slot,'RCS UNAVAILABLE');return;}
  $dv=(float)$rcs['thrust_n']*(float)$rcs['torque_arm_m']*(float)$rcs['pulse_seconds']
    /max(1.,(float)$rcs['inertia_kg_m2'])*180/M_PI;
  if($dv<=0){moonDskyFailure($db,$craft,$slot,'RCS CALIBRATION');return;}
  $brake=($rate*$rate)/(2*$dv); // angular stop distance with one pulse per second
  $desired=abs($rate)>0.05 && (($error*$rate<0)||abs($error)<=$brake)
    ? ($rate>0?-1:1)
    : ($error>0?1:-1);
  if(!moonDskyRcsPulse($db,$craft,$name,$desired)){
   moonDskyFailure($db,$craft,$slot,'RCS POWER / FUEL / ATTACHMENT');
   return;
  }
 }
 if($finished){
  $q=$db->prepare("UPDATE dsky_registers SET state='complete',message='TARGET REACHED' WHERE craft_id=? AND register_no=?");
  $q->bind_param('ii',$craft,$slot);$q->execute();
 }
}
/** Must run just before moonV2Physics, within the same one-second transaction. */
function moonDskyBeforePhysics(mysqli $db,int $nextTick):void {
 $rows=$db->query("SELECT r.*,p.operation,p.config_json FROM dsky_registers r
 JOIN dsky_programs p ON p.craft_id=r.craft_id AND p.program_no=r.program_no
 WHERE r.state IN ('queued','running') ORDER BY r.craft_id,CASE WHEN r.state='running' THEN 0 ELSE 1 END,r.register_no FOR UPDATE")->fetch_all(MYSQLI_ASSOC);
 $active=[];
 foreach($rows as $r){
  $craft=(int)$r['craft_id'];$slot=(int)$r['register_no'];$kind=(string)$r['operation'];
  if(!in_array($craft,[3,6],true)){moonDskyFailure($db,$craft,$slot,'INVALID COMPUTER');continue;}
  if(!in_array($kind,['burn_seconds','attitude_target'],true)){moonDskyFailure($db,$craft,$slot,'INVALID OPERATION');continue;}
  $group=$kind==='burn_seconds'?'engine':'attitude';
  if(isset($active[$craft][$group]))continue;
  $active[$craft][$group]=true;
  if($r['state']==='queued'){
   $q=$db->prepare("UPDATE dsky_registers SET state='running',started_tick=?,elapsed_ticks=0,message=NULL WHERE craft_id=? AND register_no=?");
   $q->bind_param('iii',$nextTick,$craft,$slot);$q->execute();
  }
  $config=moonDskyConfig($r);
  if($kind==='attitude_target'){
   moonDskyTargetTick($db,$craft,$slot,$r,$config);
   continue;
  }
  $engine=(int)($config['engine_id']??0);$scale=(float)($config['scale']??0);
  $seconds=$scale>0 && $r['field1']!==null?(float)$r['field1']/$scale:0.;
  if($seconds<1||$seconds>9999||abs($seconds-round($seconds))>1e-8){
   moonDskyFailure($db,$craft,$slot,'INVALID BURN DURATION');continue;
  }
  $q=$db->prepare('SELECT vehicle_id,command_on FROM propulsion_engines WHERE id=? FOR UPDATE');
  $q->bind_param('i',$engine);$q->execute();$e=$q->get_result()->fetch_assoc();
  // For the first CM programs only the service propulsion engine is permitted.
  if(!$e||$craft!==3||(int)$e['vehicle_id']!==4){
   moonDskyFailure($db,$craft,$slot,'ENGINE NOT ASSIGNED');continue;
  }
  $o=[];$all=$db->query('SELECT id,active,dependent,dependency FROM objects ORDER BY id FOR UPDATE');
  while($row=$all->fetch_assoc())$o[(int)$row['id']]=$row;
  if(!moonPropulsionAllowed($o,4)){
   moonDskyFailure($db,$craft,$slot,'STAGE INTERLOCK');moonDskyEngineOff($db,$engine);continue;
  }
  $q=$db->prepare('SELECT remaining_kg FROM propulsion_tanks WHERE vehicle_id=4 FOR UPDATE');
  $q->execute();$tank=$q->get_result()->fetch_assoc();
  if(!$tank||(float)$tank['remaining_kg']<=0){
   moonDskyFailure($db,$craft,$slot,'NO PROPELLANT');moonDskyEngineOff($db,$engine);continue;
  }
  $q=$db->prepare('UPDATE propulsion_engines SET command_on=1 WHERE id=?');
  $q->bind_param('i',$engine);$q->execute();
 }
}
/** After physics: verify actual ignition and count only seconds of real engine firing. */
function moonDskyAfterPhysics(mysqli $db):void {
 $rows=$db->query("SELECT r.*,p.operation,p.config_json FROM dsky_registers r
 JOIN dsky_programs p ON p.craft_id=r.craft_id AND p.program_no=r.program_no
 WHERE r.state='running' AND p.operation='burn_seconds' ORDER BY r.craft_id,r.register_no FOR UPDATE")->fetch_all(MYSQLI_ASSOC);
 foreach($rows as $r){
  $cfg=moonDskyConfig($r);$id=(int)($cfg['engine_id']??0);
  $q=$db->prepare('SELECT firing,command_on FROM propulsion_engines WHERE id=? FOR UPDATE');
  $q->bind_param('i',$id);$q->execute();$engine=$q->get_result()->fetch_assoc();
  $craft=(int)$r['craft_id'];$slot=(int)$r['register_no'];
  if(!$engine||(int)$engine['firing']!==1){
   moonDskyFailure($db,$craft,$slot,'ENGINE DID NOT FIRE');moonDskyEngineOff($db,$id);continue;
  }
  $ticks=(int)$r['elapsed_ticks']+1;
  $duration=(int)round((float)$r['field1']/(float)$cfg['scale']);
  if($ticks>=$duration){
   moonDskyEngineOff($db,$id);
   $q=$db->prepare("UPDATE dsky_registers SET state='complete',elapsed_ticks=?,message='BURN COMPLETE' WHERE craft_id=? AND register_no=?");
  }else{
   $q=$db->prepare('UPDATE dsky_registers SET elapsed_ticks=? WHERE craft_id=? AND register_no=?');
  }
  $q->bind_param('iii',$ticks,$craft,$slot);$q->execute();
 }
}
