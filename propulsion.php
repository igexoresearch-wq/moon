<?php
declare(strict_types=1);
/**
 * Apollo propulsion. The SQL schema holds commands; flight programming will
 * eventually write them. This module does not expose any ignition controls.
 * All quantities SI. Must be called within the physics tick transaction.
 */
function moonPropulsionReady(mysqli $db):bool {
 static $ready=null;
 if($ready!==null)return $ready;
 $tables=[];
 $r=$db->query("SHOW TABLES LIKE 'propulsion_%'");
 while($row=$r->fetch_row())$tables[]=$row[0];
 return $ready=in_array('propulsion_tanks',$tables,true)&&in_array('propulsion_engines',$tables,true);
}
function moonPropulsionParent(array $objects,int $child,int $parent):bool {
 return isset($objects[$child],$objects[$parent])
  && (int)$objects[$child]['active']===1
  && (int)$objects[$parent]['active']===1
  && (int)$objects[$child]['dependent']===1
  && (int)($objects[$child]['dependency']??0)===$parent;
}
function moonPropulsionRoot(array $objects,int $id):int {
 $seen=[];$cur=$id;
 while(isset($objects[$cur])&&(int)$objects[$cur]['dependent']===1
   && $objects[$cur]['dependency']!==null){
  if(isset($seen[$cur]))throw new RuntimeException('Attachment cycle in propulsion assembly');
  $seen[$cur]=true;$p=(int)$objects[$cur]['dependency'];
  if(!isset($objects[$p])||(int)$objects[$p]['active']!==1)break;
  $cur=$p;
 }
 return $cur;
}
function moonPropulsionAllowed(array $o,int $vehicle):bool {
 // Sequential Saturn staging: the engine stage must be continuously
 // attached to CM, while all lower stages must have been separated.
 // Existing detached/offline simulation entities never fire.
 return match($vehicle){
  4=>moonPropulsionParent($o,4,3)
     && !moonPropulsionParent($o,8,4),
  8=>moonPropulsionParent($o,4,3)&&moonPropulsionParent($o,8,4)
     && !moonPropulsionParent($o,9,8),
  9=>moonPropulsionParent($o,4,3)&&moonPropulsionParent($o,8,4)
     && moonPropulsionParent($o,9,8)&&!moonPropulsionParent($o,10,9),
  10=>moonPropulsionParent($o,4,3)&&moonPropulsionParent($o,8,4)
     && moonPropulsionParent($o,9,8)&&moonPropulsionParent($o,10,9),
  // Lunar descent stage attached to the LM ascent body; LM ascent can
  // fire only after the descent stage is detached.
  7=>moonPropulsionParent($o,7,6)
     && moonPropulsionRoot($o,6)===6,
  6=>isset($o[6])&&(int)$o[6]['active']===1
     && moonPropulsionRoot($o,6)===6&&!moonPropulsionParent($o,7,6),
  default=>false
 };
}
/**
 * Returns acceleration vectors for attachment roots, consumed by moonV2Physics.
 * Fuel and engine state changes are transactional with the motion update.
 */
function moonPropulsionTick(mysqli $db,array $objects,float $dt):array {
 if(!moonPropulsionReady($db))return [];
 if($dt<=0)throw new InvalidArgumentException('Invalid propulsion time step');
 $tanks=[];
 $rs=$db->query('SELECT vehicle_id,capacity_kg,remaining_kg FROM propulsion_tanks ORDER BY vehicle_id FOR UPDATE');
 while($t=$rs->fetch_assoc())$tanks[(int)$t['vehicle_id']]=$t;
 $engines=$db->query('SELECT * FROM propulsion_engines ORDER BY vehicle_id,id FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
 $groups=[];
 foreach($engines as $e)$groups[(int)$e['vehicle_id']][]=$e;
 $updateEngine=$db->prepare('UPDATE propulsion_engines SET firing=?,command_on=?,starts_used=? WHERE id=?');
 $updateTank=$db->prepare('UPDATE propulsion_tanks SET remaining_kg=? WHERE vehicle_id=?');
 $forces=[];
 foreach($groups as $vehicle=>$members){
  $tank=$tanks[$vehicle]??null;
  $allowed=$tank!==null&&isset($objects[$vehicle])&&moonPropulsionAllowed($objects,$vehicle]);
  $available=$tank===null?0.:max(0.,(float)$tank['remaining_kg']);
  $request=[];$demand=0.;
  foreach($members as $e){
   $id=(int)$e['id'];$on=(int)$e['command_on']===1;
   $previous=(int)$e['firing']===1;$starts=(int)$e['starts_used'];
   $maxStarts=(int)$e['maximum_starts'];$throttle=(float)$e['throttle_pct'];
   $canThrottle=(int)$e['throttleable']===1;
   $validThrottle=($canThrottle&&$throttle>= (float)$e['minimum_throttle_pct']&&$throttle<=100.)
     ||(!$canThrottle&&abs($throttle-100.)<0.00001);
   // LM descent engine has a variable-thrust band (~10.6–63.8%)
   // plus a separate fixed full-thrust operating point (100%).
   if($vehicle===7&&$throttle>63.83&&$throttle<99.999)$validThrottle=false;
   $canStart=$previous||$maxStarts===0||$starts<$maxStarts;
   if(!$allowed||!$on||!$validThrottle||!$canStart||$available<=0.){
    // Invalid or inhibited commands are cancelled, never armed to fire later.
    $off=0;$command=0;
    if($previous||$on){
     $updateEngine->bind_param('iiii',$off,$command,$starts,$id);$updateEngine->execute();
    }
    continue;
   }
   $fraction=$canThrottle?$throttle/100.:1.;
   $flow=(float)$e['mass_flow_kg_s']*$fraction;
   if($flow<=0.||!is_finite($flow))throw new RuntimeException('Invalid engine mass flow '.$id);
   $demand+=$flow*$dt;
   $request[]=['row'=>$e,'flow'=>$flow,'fraction'=>$fraction,'starts'=>$starts+($previous?0:1)];
  }
  if($demand<=0.)continue;
  $scale=min(1.,$available/$demand);
  $consumed=min($available,$demand);
  // Use average propellant mass during the interval for acceleration.
  $root=moonPropulsionRoot($objects,$vehicle);
  $mass=0.;
  foreach($objects as $oid=>$obj){
   if((int)$obj['active']!==1)continue;
   if(moonPropulsionRoot($objects,(int)$oid)!==$root)continue;
   $mass+=(float)$obj['dry_mass']+max(0.,(float)($tanks[(int)$oid]['remaining_kg']??0.));
  }
  $mass-=$consumed/2.;
  if($mass<=0.)throw new RuntimeException('Nonpositive stack mass');
  // Engine exhaust points aft; thrust acts along the attached root's forward
  // pitch/yaw attitude. No artificial gimbal or torque simulation here.
  $p=deg2rad((float)$objects[$root]['pitch']);
  $y=deg2rad((float)$objects[$root]['yaw']);
  $direction=[cos($p)*cos($y),cos($p)*sin($y),sin($p)];
  $thrust=0.;
  foreach($request as $req){
   $e=$req['row'];$id=(int)$e['id'];
   $thrust+=(float)$e['thrust_n']*$req['fraction']*$scale;
   // Even a partial last-second burn counts as an ignition.
   $firing=$scale>0?1:0;$on=($scale<1.)?0:1;$starts=$req['starts'];
   $updateEngine->bind_param('iiii',$firing,$on,$starts,$id);$updateEngine->execute();
  }
  $remaining=max(0.,$available-$consumed);
  $updateTank->bind_param('di',$remaining,$vehicle);$updateTank->execute();
  for($k=0;$k<3;$k++)$forces[$root][$k]=($forces[$root][$k]??0.)+$direction[$k]*$thrust/$mass;
 }
 return $forces;
}
