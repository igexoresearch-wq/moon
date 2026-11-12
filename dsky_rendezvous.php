<?php
declare(strict_types=1);
require_once __DIR__.'/dsky_rcs_translate.php';
require_once __DIR__.'/dsky_dv_safety.php';
/**
 * P32: absolute mission-clock HHMM, delta-V magnitude in tenths of m/s,
 * IMU-referenced inertial pitch/yaw in tenths of a degree.
 * All commanding is inside the existing per-second worker transaction.
 */
function moonDvMissionTick(int $nowTick,int $hhmm):int {
 if($nowTick<0||$hhmm<0||$hhmm>9959)throw new DomainException('INVALID MET');
 $hours=intdiv($hhmm,100);$minutes=$hhmm%100;
 if($minutes>59)throw new DomainException('INVALID MET MINUTES');
 $cycle=360000; // 100 mission hours; display omits the hundred-hours digit.
 $within=($hours*60+$minutes)*60;
 $base=intdiv($nowTick,$cycle)*$cycle;
 $target=$base+$within;
 if($target<=$nowTick)$target+=$cycle;
 return $target;
}
function moonDvSchedule(mysqli $db,int $craft,int $slot,array $draft):void {
 if($craft!==3)throw new DomainException('CM MANEUVER ONLY');
 foreach(['field1','field2','field3','field4'] as $f)if($draft[$f]===null)throw new DomainException('FOUR VALUES REQUIRED');
 $clock=$db->query('SELECT tick FROM sim_clock WHERE id=1 FOR UPDATE')->fetch_assoc();
 if(!$clock)throw new DomainException('MISSION CLOCK UNAVAILABLE');
 $target=moonDvMissionTick((int)$clock['tick'],(int)$draft['field1']);
 if((int)$draft['field2']<1||(int)$draft['field2']>9999)throw new DomainException('DELTA-V RANGE');
 if((int)$draft['field3']>3599||(int)$draft['field4']>3599)throw new DomainException('ANGLE RANGE');
 $q=$db->prepare("INSERT INTO dsky_dv_burns
 (craft_id,register_no,target_tick,achieved_mps,last_sample_tick,ignited_tick,status)
 VALUES(?,?,?,0,NULL,NULL,'SLEWING')
 ON DUPLICATE KEY UPDATE target_tick=VALUES(target_tick),achieved_mps=0,
 last_sample_tick=NULL,ignited_tick=NULL,status='SLEWING'");
 $q->bind_param('iii',$craft,$slot,$target);$q->execute();
}
function moonDvFail(mysqli $db,int $craft,int $slot,string $reason):void {
 $q=$db->prepare("SELECT program_no FROM dsky_registers WHERE craft_id=? AND register_no=?");
 $q->bind_param('ii',$craft,$slot);$q->execute();$program=$q->get_result()->fetch_assoc();
 if($program && (int)$program['program_no']===32)moonDskyEngineOff($db,401);
 moonDskyFailure($db,$craft,$slot,$reason);
 $q=$db->prepare("UPDATE dsky_dv_burns SET status='FAILED' WHERE craft_id=? AND register_no=?");
 $q->bind_param('ii',$craft,$slot);$q->execute();
}
function moonDvBeforePhysics(mysqli $db,int $craft,int $slot,array $r,int $tick,bool $rcsMode=false):void {
 $q=$db->prepare('SELECT * FROM dsky_dv_burns WHERE craft_id=? AND register_no=? FOR UPDATE');
 $q->bind_param('ii',$craft,$slot);$q->execute();$m=$q->get_result()->fetch_assoc();
 if(!$m){moonDvFail($db,$craft,$slot,'SCHEDULE ABSENT - UPDATE DSKY API');return;}
 $target=(int)$m['target_tick'];
 if($m['ignited_tick']!==null){
  if((int)$m['ignited_tick']>$tick || $tick-$target>2400){
   moonDvFail($db,$craft,$slot,'BURN TIMEOUT');return;
  }
  // Continuous IMU-referenced attitude hold throughout the burn, using
  // real RCS jets. Unlike the pre-ignition guard, temporary trim
  // corrections do not cancel an already firing maneuver.
  $steer=['field1'=>$r['field3'],'field2'=>$r['field4']];
  moonDskyTargetTick($db,$craft,$slot,$steer,['axis'=>'pitch_yaw','scale'=>10],$tick,false);
  // If the steering controller has marked a hard failure (e.g. RCS
  // unavailable), do not leave the SPS burning without guidance.
  $stateQuery=$db->prepare('SELECT state,message FROM dsky_registers WHERE craft_id=? AND register_no=?');
  $stateQuery->bind_param('ii',$craft,$slot);$stateQuery->execute();
  $steeringState=$stateQuery->get_result()->fetch_assoc();
  if(!$steeringState||$steeringState['state']!=='running'){
   moonDvFail($db,$craft,$slot,'ATTITUDE CONTROL FAILED');return;
  }
  if($rcsMode){
   if(!moonDvRcsTranslate($db))moonDvFail($db,$craft,$slot,'RCS TRANSLATION UNAVAILABLE');
   return;
  }
  $q=$db->prepare('SELECT command_on FROM propulsion_engines WHERE id=401 FOR UPDATE');
  $q->execute();$engine=$q->get_result()->fetch_assoc();
  if(!$engine||(int)$engine['command_on']!==1){moonDvFail($db,$craft,$slot,'ENGINE STOPPED');return;}
  return;
 }
 if($tick>$target){moonDvFail($db,$craft,$slot,'MISSED IGNITION TIME');return;}
 // Reuse IMU-only RCS steering during the pre-burn slew and wait.
 // A maneuver is ready when pitch/yaw are within ±0.3 deg and
 // the measured rates have settled within the controller's rate limit.
 $steer=['field1'=>$r['field3'],'field2'=>$r['field4']];
 $aligned=moonDskyTargetTick($db,$craft,$slot,$steer,['axis'=>'pitch_yaw','scale'=>10],$tick,false);
 if($tick<$target){
  $status=$aligned?'WAITING MET':'SLEWING';
  $q=$db->prepare('UPDATE dsky_dv_burns SET status=? WHERE craft_id=? AND register_no=?');
  $q->bind_param('sii',$status,$craft,$slot);$q->execute();
  return;
 }
 if(!$aligned){moonDvFail($db,$craft,$slot,'ATTITUDE NOT READY AT TIG');return;}
 if($rcsMode){
  if(!moonDvRcsTranslate($db)){moonDvFail($db,$craft,$slot,'RCS TRANSLATION UNAVAILABLE');return;}
  $q=$db->prepare("UPDATE dsky_dv_burns SET ignited_tick=?,status='BURNING' WHERE craft_id=? AND register_no=?");
  $q->bind_param('iii',$tick,$craft,$slot);$q->execute();
  moonDskyImuHold($db,$craft,$slot,'RCS IGNITION / DELTA-V');
  return;
 }
 // The service module must be attached and no third stage may remain.
 $assembly=[];$rows=$db->query('SELECT id,active,dependent,dependency FROM objects ORDER BY id FOR UPDATE');
 while($a=$rows->fetch_assoc())$assembly[(int)$a['id']]=$a;
 if(!moonPropulsionAllowed($assembly,4)){moonDvFail($db,$craft,$slot,'SPS STAGE INTERLOCK');return;}
 $q=$db->prepare('SELECT remaining_kg FROM propulsion_tanks WHERE vehicle_id=4 FOR UPDATE');
 $q->execute();$tank=$q->get_result()->fetch_assoc();
 if(!$tank||(float)$tank['remaining_kg']<=0){moonDvFail($db,$craft,$slot,'SPS FUEL UNAVAILABLE');return;}
 $q=$db->prepare('SELECT command_on,firing,starts_used,maximum_starts FROM propulsion_engines WHERE id=401 FOR UPDATE');
 $q->execute();$engine=$q->get_result()->fetch_assoc();
 if(!$engine||(int)$engine['command_on']===1){moonDvFail($db,$craft,$slot,'SPS ENGINE OCCUPIED');return;}
 if((int)$engine['maximum_starts']>0&&(int)$engine['starts_used']>=(int)$engine['maximum_starts']){
  moonDvFail($db,$craft,$slot,'SPS START LIMIT');return;
 }
 $q=$db->prepare('UPDATE propulsion_engines SET command_on=1,throttle_pct=100 WHERE id=401');$q->execute();
 $q=$db->prepare("UPDATE dsky_dv_burns SET ignited_tick=?,status='BURNING' WHERE craft_id=? AND register_no=?");
 $q->bind_param('iii',$tick,$craft,$slot);$q->execute();
 moonDskyImuHold($db,$craft,$slot,'SPS IGNITION / DELTA-V');
}
/** Called AFTER moonGMeterTick so actual sampled proper acceleration is available. */
function moonDvAfterSensors(mysqli $db,int $tick):void {
 if(!$db->query("SHOW TABLES LIKE 'dsky_dv_burns'")->fetch_row())return;
 $q=$db->query("SELECT r.craft_id,r.register_no,r.program_no,r.field2,r.field3,r.field4,m.achieved_mps,m.last_sample_tick,m.ignited_tick
 FROM dsky_registers r JOIN dsky_dv_burns m
 ON m.craft_id=r.craft_id AND m.register_no=r.register_no
 WHERE r.state='running' AND m.status='BURNING' FOR UPDATE");
 $burns=$q->fetch_all(MYSQLI_ASSOC);
 foreach($burns as $r){
  $craft=(int)$r['craft_id'];$slot=(int)$r['register_no'];
  if((int)$r['program_no']===32){
   $q=$db->prepare('SELECT firing FROM propulsion_engines WHERE id=401 FOR UPDATE');
   $q->execute();$engine=$q->get_result()->fetch_assoc();
   if(!$engine||(int)$engine['firing']!==1){moonDvFail($db,$craft,$slot,'SPS NOT FIRING');continue;}
  }
  $q=$db->prepare('SELECT sample_tick,valid,acceleration_x_mps2,acceleration_y_mps2,acceleration_z_mps2 FROM g_meter_samples WHERE craft_id=?');
  $q->bind_param('i',$craft);$q->execute();$g=$q->get_result()->fetch_assoc();
  if(!$g||(int)$g['valid']!==1||(int)$g['sample_tick']!==$tick
    ||($r['last_sample_tick']!==null&&(int)$r['last_sample_tick']>=$tick)){
   moonDvFail($db,$craft,$slot,'DELTA-V SENSOR UNAVAILABLE');continue;
  }
  $pitch=deg2rad((float)$r['field3']/10.);$yaw=deg2rad((float)$r['field4']/10.);
  $dir=[cos($pitch)*cos($yaw),cos($pitch)*sin($yaw),sin($pitch)];
  $a=[(float)$g['acceleration_x_mps2'],(float)$g['acceleration_y_mps2'],(float)$g['acceleration_z_mps2']];
  foreach($a as $v)if(!is_finite($v)){moonDvFail($db,$craft,$slot,'BAD DELTA-V SAMPLE');continue 2;}
  $measured=$a[0]*$dir[0]+$a[1]*$dir[1]+$a[2]*$dir[2];
  if(!is_finite($measured)||$measured<0.){moonDvFail($db,$craft,$slot,'NEGATIVE THRUST READING');continue;}
  $achieved=(float)$r['achieved_mps']+$measured;
  $desired=(float)$r['field2']/10.;
  $ticks=null;
  $modelDv=null;
  if((int)$r['program_no']===32){
   // Independent bound based on real physical engine thrust and current
   // attached stack mass. Never allow a broken G-meter/projection to
   // keep the SPS firing indefinitely.
   try{
    $model=moonDvSpsMassModel($db);
    $acceleration=moonDvSpsExpectedAcceleration($model['thrust_n'],$model['mass_kg']);
    $count=$db->prepare('SELECT elapsed_ticks FROM dsky_registers WHERE craft_id=? AND register_no=? FOR UPDATE');
    $count->bind_param('ii',$craft,$slot);$count->execute();
    $record=$count->get_result()->fetch_assoc();
    $ticks=(int)($record['elapsed_ticks']??0)+1;
    $modelDv=$ticks*$acceleration;
    if(!moonDvSpsSamplePlausible($measured,$acceleration)){
     moonDvFail($db,$craft,$slot,'SPS DELTA-V SENSOR DISAGREES');continue;
    }
    if(moonDvSpsWatchdogExceeded($desired,$model['thrust_n'],$model['mass_kg'],$ticks)){
     moonDvFail($db,$craft,$slot,'SPS DELTA-V WATCHDOG CUTOFF');continue;
    }
    $count=$db->prepare('UPDATE dsky_registers SET elapsed_ticks=? WHERE craft_id=? AND register_no=?');
    $count->bind_param('iii',$ticks,$craft,$slot);$count->execute();
   }catch(Throwable $safetyError){
    error_log('Moon SPS watchdog: '.$safetyError->getMessage());
    moonDvFail($db,$craft,$slot,'SPS SAFETY MODEL UNAVAILABLE');continue;
   }
  }
  // Normal cutoff uses the G-meter. The independently estimated impulse
  // prevents overburn if sensor integration lags; display records the
  // measured value, not the model estimate.
  if($achieved>=$desired || ($modelDv!==null&&$modelDv>=$desired)){
   if((int)$r['program_no']===32)moonDskyEngineOff($db,401);
   $q=$db->prepare("UPDATE dsky_registers SET state='complete',message=? WHERE craft_id=? AND register_no=?");
   $message=sprintf('DV %.2f / TARGET %.1f MPS%s',$achieved,$desired,$modelDv!==null&&$modelDv>=$desired&&$achieved<$desired?' / MODEL LIMIT':'');
   $q->bind_param('sii',$message,$craft,$slot);$q->execute();
   $status='COMPLETE';
  }else{
   $status='BURNING';
   moonDskyImuHold($db,$craft,$slot,sprintf('DELTA-V %.1f / %.1f',$achieved,$desired));
  }
  $q=$db->prepare('UPDATE dsky_dv_burns SET achieved_mps=?,last_sample_tick=?,status=? WHERE craft_id=? AND register_no=?');
  $q->bind_param('disii',$achieved,$tick,$status,$craft,$slot);$q->execute();
 }
}
