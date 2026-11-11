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
 $q=$db->prepare('UPDATE propulsion_engines SET command_on=0,firing=0 WHERE id=?');
 $q->bind_param('i',$id);$q->execute();
}
function moonDskyEngineIds(array $config):array {
 $ids=$config['engine_ids']??(isset($config['engine_id'])?[$config['engine_id']]:[]);
 if(!is_array($ids)||count($ids)<1||count($ids)>5)throw new DomainException('INVALID ENGINE LIST');
 $ids=array_map(static fn($x)=>(int)$x,$ids);
 if(count(array_unique($ids))!==count($ids)||min($ids)<=0)throw new DomainException('INVALID ENGINE LIST');
 return $ids;
}
function moonDskyEnginesOff(mysqli $db,array $ids):void {
 foreach($ids as $id)moonDskyEngineOff($db,(int)$id);
}
function moonDskyConfig(array $program):array {
 $config=json_decode((string)$program['config_json'],true,16,JSON_THROW_ON_ERROR);
 if(!is_array($config))throw new RuntimeException('Invalid program configuration');
 return $config;
}
function moonDskyAxisError(float $angle,float $target):float {
 $delta=fmod(fmod($target-$angle+180.,360.)+360.,360.)-180.;
 return $delta;
}
function moonDskyImuHold(mysqli $db,int $craft,int $slot,string $reason):void {
 $q=$db->prepare("UPDATE dsky_registers SET message=? WHERE craft_id=? AND register_no=? AND state='running'");
 $q->bind_param('sii',$reason,$craft,$slot);$q->execute();
}
/**
 * Acquire attitude and angular rate only from the instrument's successive
 * estimated readings. This never reads objects.pitch/yaw/roll or true rates.
 * IMU is sampled at the end of the preceding physics tick.
 */
function moonDskyImuObservation(mysqli $db,int $craft,int $expectedTick):?array {
 static $historyAvailable=null;
 if($historyAvailable===null){
  $historyAvailable=(bool)$db->query("SHOW TABLES LIKE 'dsky_imu_samples'")->fetch_row();
 }
 if(!$historyAvailable)return null;
 $q=$db->prepare('SELECT pitch_deg,yaw_deg,roll_deg,last_tick FROM imu WHERE object_id=? FOR UPDATE');
 $q->bind_param('i',$craft);$q->execute();$imu=$q->get_result()->fetch_assoc();
 if(!$imu||$imu['last_tick']===null||(int)$imu['last_tick']!==$expectedTick)return null;
 $q=$db->prepare('SELECT * FROM dsky_imu_samples WHERE craft_id=? FOR UPDATE');
 $q->bind_param('i',$craft);$q->execute();$prior=$q->get_result()->fetch_assoc();
 $angles=[];$rates=[];
 foreach(['pitch','yaw','roll'] as $axis){
  $angles[$axis]=(float)$imu[$axis.'_deg'];
  $rates[$axis]=null;
 }
 if($prior!==null && (int)$prior['imu_tick']<$expectedTick){
  $dt=$expectedTick-(int)$prior['imu_tick'];
  foreach(['pitch','yaw','roll'] as $axis){
   $rates[$axis]=moonDskyAxisError((float)$prior[$axis.'_deg'],$angles[$axis])/$dt;
  }
 }
 if($prior===null||(int)$prior['imu_tick']!==$expectedTick){
  $q=$db->prepare('INSERT INTO dsky_imu_samples (craft_id,imu_tick,pitch_deg,yaw_deg,roll_deg) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE imu_tick=VALUES(imu_tick),pitch_deg=VALUES(pitch_deg),yaw_deg=VALUES(yaw_deg),roll_deg=VALUES(roll_deg)');
  $q->bind_param('iiddd',$craft,$expectedTick,$angles['pitch'],$angles['yaw'],$angles['roll']);$q->execute();
 }
 return ['angles'=>$angles,'rates'=>$rates];
}
function moonDskyTargetTick(mysqli $db,int $craft,int $slot,array $reg,array $config,int $nextTick):void {
 $axis=(string)($config['axis']??'');
 $axes=$axis==='all'?['pitch','yaw','roll']:[$axis];
 if(array_diff($axes,['pitch','yaw','roll'])){moonDskyFailure($db,$craft,$slot,'UNSUPPORTED AXIS');return;}
 $scale=(float)($config['scale']??0);
 if($scale<=0){moonDskyFailure($db,$craft,$slot,'INVALID SCALE');return;}
 $reading=moonDskyImuObservation($db,$craft,$nextTick-1);
 if($reading===null){
  moonDskyImuHold($db,$craft,$slot,'WAITING FOR FRESH IMU');
  return;
 }
 foreach($axes as $name)if($reading['rates'][$name]===null){
  moonDskyImuHold($db,$craft,$slot,'ACQUIRING IMU RATE');
  return;
 }
 $finished=true;
 moonDskyImuHold($db,$craft,$slot,'');
 foreach($axes as $index=>$name){
  $field='field'.($index+1);
  if($reg[$field]===null){moonDskyFailure($db,$craft,$slot,'TARGET NOT ENTERED');return;}
  $target=(float)$reg[$field]/$scale;
  $error=moonDskyAxisError($reading['angles'][$name],$target);
  $rate=$reading['rates'][$name];
  if(abs($error)<=0.5 && abs($rate)<=0.02)continue;
  $finished=false;
  // The stopping decision uses only the IMU angle and its inferred rate.
  // The physical RCS jet then changes the real angular velocity.
  $system=$craft===3?4:6;
  $q=$db->prepare('SELECT thrust_n,torque_arm_m,inertia_kg_m2,pulse_seconds FROM rcs WHERE craft_id=?');
  $q->bind_param('i',$system);$q->execute();$rcs=$q->get_result()->fetch_assoc();
  if(!$rcs){moonDskyFailure($db,$craft,$slot,'RCS UNAVAILABLE');return;}
  $dv=(float)$rcs['thrust_n']*(float)$rcs['torque_arm_m']*(float)$rcs['pulse_seconds']
    /max(1.,(float)$rcs['inertia_kg_m2'])*180/M_PI;
  if($dv<=0){moonDskyFailure($db,$craft,$slot,'RCS CALIBRATION');return;}
  $brake=($rate*$rate)/(2*$dv);
  $desired=abs($rate)>0.05 && (($error*$rate<0)||abs($error)<=$brake)
    ? ($rate>0?-1:1)
    : ($error>0?1:-1);
  $pulse=null;
  if($desired*$rate<0){
   $angularAcceleration=(float)$rcs['thrust_n']*(float)$rcs['torque_arm_m']
     /max(1.,(float)$rcs['inertia_kg_m2'])*180/M_PI;
   if($angularAcceleration>0)$pulse=min((float)$rcs['pulse_seconds'],abs($rate)/$angularAcceleration);
  }
  if(!moonDskyRcsPulse($db,$craft,$name,$desired,$pulse)){
   moonDskyImuHold($db,$craft,$slot,'RCS HOLD - POWER / FUEL / ATTACHMENT');
   return;
  }
 }
 if($finished){
  $q=$db->prepare("UPDATE dsky_registers SET state='complete',message='IMU TARGET REACHED' WHERE craft_id=? AND register_no=?");
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
   moonDskyTargetTick($db,$craft,$slot,$r,$config,$nextTick);
   continue;
  }
  $scale=(float)($config['scale']??0);
  $seconds=$scale>0 && $r['field1']!==null?(float)$r['field1']/$scale:0.;
  if($seconds<1||$seconds>9999||abs($seconds-round($seconds))>1e-8){
   moonDskyFailure($db,$craft,$slot,'INVALID BURN DURATION');continue;
  }
  try{$ids=moonDskyEngineIds($config);}catch(DomainException $e){
   moonDskyFailure($db,$craft,$slot,$e->getMessage());continue;
  }
  $vehicle=null;$engines=[];
  $q=$db->prepare('SELECT vehicle_id,throttleable,minimum_throttle_pct FROM propulsion_engines WHERE id=? FOR UPDATE');
  foreach($ids as $id){
   $q->bind_param('i',$id);$q->execute();$engine=$q->get_result()->fetch_assoc();
   if(!$engine){$vehicle=null;break;}
   $owner=(int)$engine['vehicle_id'];
   if($vehicle!==null&&$vehicle!==$owner){$vehicle=null;break;}
   $vehicle=$owner;$engines[]=$engine;
  }
  $permitted=$craft===3?[4,8,9,10]:[6,7];
  if($vehicle===null||!in_array($vehicle,$permitted,true)){
   moonDskyFailure($db,$craft,$slot,'ENGINE NOT ASSIGNED');continue;
  }
  // Optional second program field can set throttle percent for a future
  // SQL-defined LM descent program. Other engines accept only 100%.
  $throttle=100.;
  if(isset($config['throttle_field'])){
   $field=(int)$config['throttle_field'];
   if($field<2||$field>4||$r['field'.$field]===null){
    moonDskyFailure($db,$craft,$slot,'THROTTLE NOT ENTERED');continue;
   }
   $throttle=(float)$r['field'.$field]/max(1.,(float)($config['throttle_scale']??1));
  }
  $validThrottle=$throttle>0&&$throttle<=100.;
  foreach($engines as $engine){
   if((int)$engine['throttleable']===0 && abs($throttle-100.)>.00001)$validThrottle=false;
   if((int)$engine['throttleable']===1&&$throttle<(float)$engine['minimum_throttle_pct'])$validThrottle=false;
  }
  if($vehicle===7&&$throttle>63.83&&$throttle<99.999)$validThrottle=false;
  if(!$validThrottle){moonDskyFailure($db,$craft,$slot,'THROTTLE INVALID');continue;}
  $o=[];$all=$db->query('SELECT id,active,dependent,dependency FROM objects ORDER BY id FOR UPDATE');
  while($row=$all->fetch_assoc())$o[(int)$row['id']]=$row;
  if(!moonPropulsionAllowed($o,$vehicle)){
   moonDskyFailure($db,$craft,$slot,'STAGE INTERLOCK');moonDskyEnginesOff($db,$ids);continue;
  }
  $q=$db->prepare('SELECT remaining_kg FROM propulsion_tanks WHERE vehicle_id=? FOR UPDATE');
  $q->bind_param('i',$vehicle);$q->execute();$tank=$q->get_result()->fetch_assoc();
  if(!$tank||(float)$tank['remaining_kg']<=0){
   moonDskyFailure($db,$craft,$slot,'NO PROPELLANT');moonDskyEnginesOff($db,$ids);continue;
  }
  // Ignite only on entry. If one engine later shuts down, do not silently
  // restart it on subsequent ticks; the remaining engines may continue.
  if($r['state']==='queued'){
   $q=$db->prepare('UPDATE propulsion_engines SET command_on=1,throttle_pct=? WHERE id=?');
   foreach($ids as $id){$q->bind_param('di',$throttle,$id);$q->execute();}
  }
 }
}
/** After physics: verify actual ignition and count only seconds of real engine firing. */
function moonDskyAfterPhysics(mysqli $db):void {
 $rows=$db->query("SELECT r.*,p.operation,p.config_json FROM dsky_registers r
 JOIN dsky_programs p ON p.craft_id=r.craft_id AND p.program_no=r.program_no
 WHERE r.state='running' AND p.operation='burn_seconds' ORDER BY r.craft_id,r.register_no FOR UPDATE")->fetch_all(MYSQLI_ASSOC);
 foreach($rows as $r){
  $cfg=moonDskyConfig($r);
  $craft=(int)$r['craft_id'];$slot=(int)$r['register_no'];
  $ids=moonDskyEngineIds($cfg);
  $q=$db->prepare('SELECT firing FROM propulsion_engines WHERE id=? FOR UPDATE');
  $fired=0;
  foreach($ids as $id){
   $q->bind_param('i',$id);$q->execute();$e=$q->get_result()->fetch_assoc();
   if($e&&(int)$e['firing']===1)$fired++;
  }
  // A failed or shut-down individual engine must not magically continue
  // consuming fuel. The burn continues if at least one engine really fires.
  if($fired===0){
   moonDskyFailure($db,$craft,$slot,'NO ENGINE FIRING');moonDskyEnginesOff($db,$ids);continue;
  }
  $ticks=(int)$r['elapsed_ticks']+1;
  $duration=(int)round((float)$r['field1']/(float)$cfg['scale']);
  if($ticks>=$duration){
   moonDskyEnginesOff($db,$ids);
   $q=$db->prepare("UPDATE dsky_registers SET state='complete',elapsed_ticks=?,message='BURN COMPLETE' WHERE craft_id=? AND register_no=?");
  }else{
   $q=$db->prepare('UPDATE dsky_registers SET elapsed_ticks=? WHERE craft_id=? AND register_no=?');
  }
  $q->bind_param('iii',$ticks,$craft,$slot);$q->execute();
 }
}
