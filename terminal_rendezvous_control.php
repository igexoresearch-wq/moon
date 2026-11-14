<?php
declare(strict_types=1);
require_once __DIR__.'/terminal_rendezvous_tracker.php';
require_once __DIR__.'/reliability.php';
/**
 * P36: terminal relative-velocity cancellation. A monitored one-axis RCS
 * pulse per second, chosen in the onboard IMU frame. Real engine response
 * comes only from thrust, mass and propellant; no direct velocity matching.
 */
function moonTerminalDemand(array $r,array $v,float $hold):array {
 $distance=moonTerminalNorm($r);
 if($distance<1e-6)return [0.,0.,0.];
 $unit=array_map(static fn($a)=>$a/$distance,$r);
 // At range > hold, allow only a tiny closing velocity, never a rush.
 // At hold, arrest all relative velocity for manual docking.
 $closing=max(0.,min(.25,($distance-$hold)*.005));
 $target=array_map(static fn($x)=>-$closing*$x,$unit);
 $desired=[];
 for($k=0;$k<3;$k++)$desired[$k]=($v[$k]-$target[$k]);
 return $desired;
}
function moonTerminalP36Tick(mysqli $db,int $craft,int $slot,array $register,int $tick):void {
 $hold=(float)($register['field1']??50.);
 if($hold<20.||$hold>500.){moonDskyFailure($db,$craft,$slot,'INVALID HOLD RANGE');return;}
 $q=$db->prepare('SELECT * FROM terminal_rendezvous WHERE craft_id=? FOR UPDATE');
 $q->bind_param('i',$craft);$q->execute();$e=$q->get_result()->fetch_assoc();
 if(!$e||$e['sample_tick']===null||$tick-1-(int)$e['sample_tick']>2||
    $e['status']!=='TRACKING'||(int)$e['sample_count']<4){
  moonDskyImuHold($db,$craft,$slot,'WAITING RADAR / NAV TRACK');return;
 }
 $range=(float)$e['last_range_m'];
 if($range>1000.){moonDskyImuHold($db,$craft,$slot,'RANGE > 1000 M / P36 HOLD');return;}
 $r=[(float)$e['rx'],(float)$e['ry'],(float)$e['rz']];
 $v=[(float)$e['vx'],(float)$e['vy'],(float)$e['vz']];
 $speed=moonTerminalNorm($v);
 if($range<8.&&$speed<.12){
  moonDskyImuHold($db,$craft,$slot,'WITHIN 8 M / MANUAL DOCKING');
  $q=$db->prepare("UPDATE dsky_registers SET state='complete',message='MANUAL DOCKING / FINAL APPROACH' WHERE craft_id=? AND register_no=?");
  $q->bind_param('ii',$craft,$slot);$q->execute();return;
 }
 if($range<=$hold+10.&&$speed<.12){
  moonDskyImuHold($db,$craft,$slot,'STATION KEEP / READY MANUAL');
  $q=$db->prepare("UPDATE dsky_registers SET state='complete',message='REL SPEED <0.12 M/S / MANUAL CONTROL' WHERE craft_id=? AND register_no=?");
  $q->bind_param('ii',$craft,$slot);$q->execute();return;
 }
 $system=$craft===3?4:6;
 $state=$db->prepare('SELECT * FROM objects WHERE id=? FOR UPDATE');
 $state->bind_param('i',$craft);$state->execute();$object=$state->get_result()->fetch_assoc();
 if(!$object||(int)$object['active']!==1||(int)$object['dependent']===1){
  moonDskyFailure($db,$craft,$slot,'CRAFT NOT FREE');return;
 }
 if($craft===3){
  $sm=$db->query('SELECT active,dependent,dependency,dry_mass FROM objects WHERE id=4 FOR UPDATE')->fetch_assoc();
  if(!$sm||(int)$sm['active']!==1||(int)$sm['dependent']!==1||(int)$sm['dependency']!==3){
   moonDskyFailure($db,$craft,$slot,'SM NOT ATTACHED');return;
  }
 }
 // Avoid fighting another running main-engine or DSKY maneuver.
 $engaged=$db->query('SELECT id FROM propulsion_engines WHERE command_on=1 OR firing=1 LIMIT 1')->fetch_assoc();
 if($engaged){moonDskyImuHold($db,$craft,$slot,'OTHER ENGINE ACTIVE / HOLD');return;}
 $q=$db->prepare('SELECT * FROM rcs WHERE craft_id=? FOR UPDATE');
 $q->bind_param('i',$system);$q->execute();$rcs=$q->get_result()->fetch_assoc();
 if(!$rcs||(int)$rcs['enabled']!==1){moonDskyImuHold($db,$craft,$slot,'RCS OFFLINE');return;}
 $prefix=$craft===3?'cm':'lm';
 $power=$db->query("SELECT state FROM reliability_components WHERE component_key='".$prefix."_rcs_cluster' FOR UPDATE")->fetch_assoc();
 if(!$power||(int)$power['state']!==1){moonDskyImuHold($db,$craft,$slot,'RCS CLUSTER OFFLINE');return;}
 $q=$db->prepare('SELECT pitch_deg,yaw_deg,roll_deg,last_tick FROM imu WHERE object_id=?');
 $q->bind_param('i',$craft);$q->execute();$imu=$q->get_result()->fetch_assoc();
 if(!$imu||$imu['last_tick']===null||$tick-1-(int)$imu['last_tick']>2){
  moonDskyImuHold($db,$craft,$slot,'IMU UNAVAILABLE');return;
 }
 $basis=moonTerminalBasis((float)$imu['pitch_deg'],(float)$imu['yaw_deg'],(float)$imu['roll_deg']);
 // Warn rather than claiming impossible braking can be completed in
 // time. P36 retains limited authority at this range.
 $accel=(float)$rcs['thrust_n']/max(1.,(float)$object['dry_mass']+($craft===3?(float)$sm['dry_mass']:0.)+(float)$rcs['fuel_kg']+(float)$rcs['oxidizer_kg']);
 $brakingDistance=$speed*$speed/(2.*max($accel,1e-9));
 if($brakingDistance>max(0.,$range-$hold)&&$speed>.4){
  moonDskyImuHold($db,$craft,$slot,sprintf('BRAKING DIST %.0f M / RANGE %.0f M',$brakingDistance,$range));
 }
 $desired=moonTerminalDemand($r,$v,$hold);
 $components=[
  ['axis'=>'forward','vec'=>$basis[0]],
  ['axis'=>'lateral','vec'=>$basis[1]],
  ['axis'=>'vertical','vec'=>$basis[2]]
 ];
 $best=null;$amplitude=0.;
 foreach($components as $component){
  $along=moonTerminalDot($desired,$component['vec']);
  if(abs($along)>$amplitude){$amplitude=abs($along);$best=$component+['along'=>$along];}
 }
 if($best===null||$amplitude<.035){
  moonDskyImuHold($db,$craft,$slot,'FINE CONTROL / COAST');return;
 }
 $direction=$best['along']>0?-1:1;
 $label=match($best['axis']){
  'forward'=>$direction>0?'translate_forward':'translate_back',
  'lateral'=>$direction>0?'translate_right':'translate_left',
  default=>$direction>0?'translate_up':'translate_down'
 };
 $key=$prefix.'_rcs_'.$label;
 if(!moonReliabilityCommand($db,$key,'P36 terminal velocity-match')['allowed']){
  moonDskyImuHold($db,$craft,$slot,'RCS JET UNAVAILABLE / HOLD');return;
 }
 $thrust=(float)$rcs['thrust_n'];$isp=(float)$rcs['isp_s'];
 $maxPulse=(float)$rcs['pulse_seconds'];
 if($thrust<=0||$isp<=0||$maxPulse<=0)throw new DomainException('RCS CALIBRATION BAD');
 $mass=(float)$object['dry_mass']+(float)$rcs['fuel_kg']+(float)$rcs['oxidizer_kg'];
 if($craft===3){
  $mass+=(float)$sm['dry_mass'];
  $tank=$db->query('SELECT remaining_kg FROM propulsion_tanks WHERE vehicle_id=4')->fetch_assoc();
  if($tank)$mass+=(float)$tank['remaining_kg'];
 }
 $mass=max(1.,$mass);
 // Pulse duration proportional to estimated error but never more than the
 // hardware's discrete pulse limit. Actuators change velocity physically.
 // A continuous, one-second terminal pulse is allowed; the .1s
 // manual pulse setting is a tap length, not a physical thrust limit.
 // Cap by this physics second and never impulse more than commanded.
 $dt=min(1.,$amplitude*$mass/$thrust);
 if($dt<.0001){moonDskyImuHold($db,$craft,$slot,'PULSE BELOW RESOLUTION');return;}
 $consumed=$thrust*$dt/($isp*9.80665);
 if((float)$rcs['fuel_kg']<$consumed*.4||(float)$rcs['oxidizer_kg']<$consumed*.6){
  moonDskyFailure($db,$craft,$slot,'RCS PROPELLANT EXHAUSTED');return;
 }
 // Existing RCS simulator uses physical attitude for actual thrust,
 // but the DSKY controller uses only IMU orientation. Any IMU error
 // therefore creates genuine guidance error rather than perfect control.
 $p=deg2rad((float)$object['pitch']);$y=deg2rad((float)$object['yaw']);$roll=deg2rad((float)$object['roll']);
 $physical=moonTerminalBasis(rad2deg($p),rad2deg($y),rad2deg($roll));
 $axis=match($best['axis']){'forward'=>0,'lateral'=>1,default=>2};
 $dv=$direction*$thrust*$dt/$mass;$vector=$physical[$axis];
 $vel=[(float)$object['delta_x']+$dv*$vector[0],(float)$object['delta_y']+$dv*$vector[1],(float)$object['delta_z']+$dv*$vector[2]];
 $q=$db->prepare('UPDATE objects SET delta_x=?,delta_y=?,delta_z=? WHERE id=?');
 $q->bind_param('dddi',$vel[0],$vel[1],$vel[2],$craft);$q->execute();
 $fuel=(float)$rcs['fuel_kg']-$consumed*.4;$ox=(float)$rcs['oxidizer_kg']-$consumed*.6;
 $rid=(int)$rcs['id'];
 $q=$db->prepare('UPDATE rcs SET fuel_kg=?,oxidizer_kg=? WHERE id=?');
 $q->bind_param('ddi',$fuel,$ox,$rid);$q->execute();
 moonDskyImuHold($db,$craft,$slot,sprintf('P36 %.1f M / %.2f MPS / %s',$range,$speed,$label));
}
