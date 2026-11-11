<?php
declare(strict_types=1);
require_once __DIR__.'/reliability.php';

/**
 * One physical RCS attitude pulse (not a direct attitude setter).
 * Uses the same propellant, impulse, component status and torque assumptions as
 * the current manual rcs_command.php. Called only inside an existing DB transaction.
 */
function moonDskyRcsPulse(mysqli $db,int $craft,string $axis,int $sign,?float $pulseSeconds=null):bool {
 if(!in_array($craft,[3,6],true)||!in_array($axis,['pitch','yaw','roll'],true)||!in_array($sign,[-1,1],true))
  throw new InvalidArgumentException('Invalid RCS axis');
 $obj=$db->query('SELECT * FROM objects WHERE id='.(int)$craft.' FOR UPDATE')->fetch_assoc();
 if(!$obj||(int)$obj['active']!==1)return false;
 $system=$craft===3?4:6;
 // The SM must remain attached to the CM to control the CSM.
 if($craft===3){
  $sm=$db->query('SELECT active,dependent,dependency FROM objects WHERE id=4 FOR UPDATE')->fetch_assoc();
  if(!$sm||(int)$sm['active']!==1||(int)$sm['dependent']!==1||(int)$sm['dependency']!==3)return false;
 }
 $q=$db->prepare('SELECT * FROM rcs WHERE craft_id=? FOR UPDATE');
 $q->bind_param('i',$system);$q->execute();$rcs=$q->get_result()->fetch_assoc();
 if(!$rcs||(int)$rcs['enabled']!==1)return false;
 $prefix=$craft===3?'cm':'lm';
 $key=$prefix.'_rcs_'.match($axis){
  'pitch'=>$sign>0?'pitch_up':'pitch_down',
  'yaw'=>$sign>0?'yaw_right':'yaw_left',
  'roll'=>$sign>0?'roll_right':'roll_left'
 };
 $cluster=$db->query("SELECT state FROM reliability_components WHERE component_key='".$prefix."_rcs_cluster' FOR UPDATE")->fetch_assoc();
 if(!$cluster||(int)$cluster['state']!==1)return false;
 if(!moonReliabilityCommand($db,$key,'DSKY RCS pulse')['allowed'])return false;
 $dt=$pulseSeconds===null?(float)$rcs['pulse_seconds']:min((float)$rcs['pulse_seconds'],max(0.,$pulseSeconds));$isp=(float)$rcs['isp_s'];$thrust=(float)$rcs['thrust_n'];
 $arm=(float)$rcs['torque_arm_m'];$inertia=(float)$rcs['inertia_kg_m2'];
 if($dt<=0||$isp<=0||$thrust<=0||$arm<=0||$inertia<=0)throw new RuntimeException('Invalid RCS calibration');
 $consumed=$thrust*$dt/($isp*9.80665);
 if((float)$rcs['fuel_kg']<$consumed*.4||(float)$rcs['oxidizer_kg']<$consumed*.6)return false;
 $rate=(float)$obj[$axis.'_rate']+$sign*$thrust*$arm*$dt/$inertia*180/M_PI;
 $column=$axis.'_rate'; // constant, validated against whitelist above
 $q=$db->prepare("UPDATE objects SET $column=? WHERE id=?");
 $q->bind_param('di',$rate,$craft);$q->execute();
 $fuel=(float)$rcs['fuel_kg']-$consumed*.4;$ox=(float)$rcs['oxidizer_kg']-$consumed*.6;
 $id=(int)$rcs['id'];$q=$db->prepare('UPDATE rcs SET fuel_kg=?,oxidizer_kg=? WHERE id=?');
 $q->bind_param('ddi',$fuel,$ox,$id);$q->execute();
 return true;
}
