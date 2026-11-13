<?php
declare(strict_types=1);
/**
 * CSM (3) / LM ascent (6) nose-to-nose docking and impact.
 * SI units. A centered forward port is assumed at each craft's radius.
 * Only truth physics resolves contact; FIDO's estimated nav remains separate.
 */
function moonDockVec(array $v):float {return sqrt($v[0]**2+$v[1]**2+$v[2]**2);}
function moonDockMinus(array $a,array $b):array {return [$a[0]-$b[0],$a[1]-$b[1],$a[2]-$b[2]];}
function moonDockDot(array $a,array $b):float {return $a[0]*$b[0]+$a[1]*$b[1]+$a[2]*$b[2];}
function moonDockPos(array $o):array {return [(float)$o['x'],(float)$o['y'],(float)$o['z']];}
function moonDockVel(array $o):array {return [(float)$o['delta_x'],(float)$o['delta_y'],(float)$o['delta_z']];}
function moonDockForward(array $o):array {
 $p=deg2rad((float)$o['pitch']);$y=deg2rad((float)$o['yaw']);
 return [cos($p)*cos($y),cos($p)*sin($y),sin($p)];
}
function moonDockGeometry(array $cm,array $lm):array {
 $cp=moonDockPos($cm);$lp=moonDockPos($lm);
 $d=moonDockMinus($lp,$cp);$distance=moonDockVec($d);
 $speed=moonDockVec(moonDockMinus(moonDockVel($lm),moonDockVel($cm)));
 $cf=moonDockForward($cm);$lf=moonDockForward($lm);
 $unit=$distance>1e-9?array_map(static fn($v)=>$v/$distance,$d):[0.,0.,0.];
 $cmFacing=moonDockDot($cf,$unit);
 $lmFacing=-moonDockDot($lf,$unit);
 $aligned=$distance>1e-9&&$cmFacing>=cos(deg2rad(15.))&&$lmFacing>=cos(deg2rad(15.));
 $cmPort=[$cp[0]+$cf[0]*(float)$cm['radius_length'],$cp[1]+$cf[1]*(float)$cm['radius_length'],$cp[2]+$cf[2]*(float)$cm['radius_length']];
 $lmPort=[$lp[0]+$lf[0]*(float)$lm['radius_length'],$lp[1]+$lf[1]*(float)$lm['radius_length'],$lp[2]+$lf[2]*(float)$lm['radius_length']];
 $gap=moonDockVec(moonDockMinus($lmPort,$cmPort));
 $relative=moonDockMinus(moonDockVel($lm),moonDockVel($cm));
 $closing=$distance>1e-9?-moonDockDot($relative,$unit):0.;
 return ['range_m'=>$distance,'relative_speed_mps'=>$speed,'closing_mps'=>$closing,
  'port_gap_m'=>$gap,'cm_facing'=>$cmFacing,'lm_facing'=>$lmFacing,
  'axes_aligned'=>$aligned,
  'dockable'=>$aligned&&$gap<=1.&&$speed<=1.&&$closing>=-0.05];
}
/** Return the minimum center-to-center gap over a one-second swept segment. */
function moonDockSweptDistance(array $oldCm,array $oldLm,array $newCm,array $newLm):float {
 $a=moonDockMinus(moonDockPos($oldLm),moonDockPos($oldCm));
 $b=moonDockMinus(moonDockPos($newLm),moonDockPos($newCm));
 $v=moonDockMinus($b,$a);$v2=moonDockDot($v,$v);
 $t=$v2>0?max(0.,min(1.,-moonDockDot($a,$v)/$v2)):0.;
 return moonDockVec([$a[0]+$t*$v[0],$a[1]+$t*$v[1],$a[2]+$t*$v[2]]);
}
function moonDockDamageCount(float $speed):int {
 if($speed<=1.)return 0;
 if($speed<=5.)return 1;
 if($speed<=10.)return 2;
 return 3;
}
function moonDockInstall(mysqli $db):void {
 $db->query("CREATE TABLE IF NOT EXISTS docking_contact (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  touching TINYINT(1) NOT NULL DEFAULT 0,
  docked TINYINT(1) NOT NULL DEFAULT 0,
  last_tick BIGINT UNSIGNED NULL,
  last_event VARCHAR(80) NOT NULL DEFAULT 'NONE',
  last_speed_mps DOUBLE NULL,
  last_port_gap_m DOUBLE NULL,
  last_damage_cm SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  last_damage_lm SMALLINT UNSIGNED NOT NULL DEFAULT 0
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $db->query("INSERT IGNORE INTO docking_contact(id) VALUES(1)");
 // Separate docking probe instruments can fail independently in collision.
 $db->query("INSERT IGNORE INTO reliability_components(component_key,component_name,spacecraft_id,master,alarm_name,state)
 VALUES ('cm_docking_probe','CM Docking Probe',3,1,'DOCK PROBE',1),
 ('lm_docking_probe','LM Docking Probe',6,1,'DOCK PROBE',1)");
 $db->query("CREATE TABLE IF NOT EXISTS docking_events(
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sim_tick BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(30) NOT NULL,
  speed_mps DOUBLE NOT NULL,
  port_gap_m DOUBLE NOT NULL,
  damage_cm SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  damage_lm SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX docking_events_tick(sim_tick)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function moonDockFailComponents(mysqli $db,int $craft,int $count,int $tick,float $speed):int {
 if($count<=0)return 0;
 // CM-controlled systems may live on service module id 4.
 $ids=$craft===3?[3,4]:[6,7];
 $sql='SELECT id,component_key FROM reliability_components WHERE spacecraft_id IN ('.implode(',',$ids).') AND state IN (1,4) FOR UPDATE';
 $rows=$db->query($sql)->fetch_all(MYSQLI_ASSOC);
 $eligible=[];
 foreach($rows as $row)$eligible[]=$row;
 $done=0;
 while($done<$count&&$eligible){
  $i=random_int(0,count($eligible)-1);
  $chosen=array_splice($eligible,$i,1)[0];
  $id=(int)$chosen['id'];$key=(string)$chosen['component_key'];
  $q=$db->prepare('UPDATE reliability_components SET state=3,state_since=NOW(),restore_at=NULL WHERE id=?');
  $q->bind_param('i',$id);$q->execute();
  $desc=sprintf('Docking impact %.2f m/s / permanently damaged %s',$speed,$craft===3?'CSM':'LM');
  $type='permanent';$roll=0;$restore=null;
  $q=$db->prepare('INSERT INTO reliability_failures(component_id,component_key,failure_type,description,roll,sim_tick,occurred_at,restore_at,acknowledged) VALUES(?,?,?,?,?,?,NOW(),?,0)');
  $q->bind_param('isssiis',$id,$key,$type,$desc,$roll,$tick,$restore);$q->execute();
  $done++;
 }
 return $done;
}
/**
 * Resolve an undocked hull impact: find the first swept-sphere contact,
 * put craft at the contact plane, and apply a low-restition normal impulse.
 * Prevents high-speed fly-through; does not alter propellant or orientation.
 * This is a simplified rigid-body translation model (no angular impulse).
 */
function moonDockResolveImpact(mysqli $db,array $oldCm,array $oldLm,array $cm,array $lm):void {
 $a=moonDockMinus(moonDockPos($oldLm),moonDockPos($oldCm));
 $b=moonDockMinus(moonDockPos($lm),moonDockPos($cm));
 $v=moonDockMinus($b,$a);
 $radius=(float)$cm['radius_length']+(float)$lm['radius_length'];
 $aa=moonDockDot($v,$v);$bb=2.*moonDockDot($a,$v);$cc=moonDockDot($a,$a)-$radius*$radius;
 $t=0.;
 if($aa>1e-12&&$cc>0.){
  $discriminant=$bb*$bb-4.*$aa*$cc;
  if($discriminant>=0.)$t=max(0.,min(1.,(-$bb-sqrt($discriminant))/(2.*$aa)));
  else $t=1.;
 }
 $c0=moonDockPos($oldCm);$c1=moonDockPos($cm);
 $l0=moonDockPos($oldLm);$l1=moonDockPos($lm);
 $cp=[];$lp=[];
 for($k=0;$k<3;$k++){
  $cp[$k]=$c0[$k]+$t*($c1[$k]-$c0[$k]);
  $lp[$k]=$l0[$k]+$t*($l1[$k]-$l0[$k]);
 }
 $normal=moonDockMinus($lp,$cp);$dist=moonDockVec($normal);
 if($dist<1e-9){$normal=moonDockVec($a)>1e-9?$a:[1.,0.,0.];$dist=moonDockVec($normal);}
 $normal=array_map(static fn($n)=>$n/$dist,$normal);
 $vcm=moonDockVel($cm);$vlm=moonDockVel($lm);
 $rv=moonDockMinus($vlm,$vcm);
 $normalSpeed=moonDockDot($rv,$normal);
 if($normalSpeed<0.){
  $mcm=max(1.,(float)$cm['dry_mass']+6100.);
  $mlm=max(1.,(float)$lm['dry_mass']);
  $restitution=.05;
  $impulse=-(1.+$restitution)*$normalSpeed/(1./$mcm+1./$mlm);
  for($k=0;$k<3;$k++){
   $vcm[$k]-=$impulse/$mcm*$normal[$k];
   $vlm[$k]+=$impulse/$mlm*$normal[$k];
  }
 }
 // When already penetrating at beginning of tick, separate conservatively.
 $correction=max(0.,$radius+.01-$dist);
 for($k=0;$k<3;$k++)$lp[$k]+=$correction*$normal[$k];
 $update=$db->prepare('UPDATE objects SET x=?,y=?,z=?,delta_x=?,delta_y=?,delta_z=? WHERE id=?');
 foreach([[3,$cp,$vcm],[6,$lp,$vlm]] as [$id,$p,$vel]){
  $update->bind_param('ddddddi',$p[0],$p[1],$p[2],$vel[0],$vel[1],$vel[2],$id);
  $update->execute();
 }
}

/** Called AFTER the 1s physics integration in the SAME transaction. */
function moonDockingTick(mysqli $db,array $before,int $tick):void {
 if(!isset($before[3],$before[6]))return;
 $now=[];
 $res=$db->query('SELECT * FROM objects WHERE id IN (3,6) ORDER BY id FOR UPDATE');
 foreach($res->fetch_all(MYSQLI_ASSOC) as $o)$now[(int)$o['id']]=$o;
 if(!isset($now[3],$now[6]))return;
 $cm=$now[3];$lm=$now[6];
 $state=$db->query('SELECT * FROM docking_contact WHERE id=1 FOR UPDATE')->fetch_assoc();
 if((int)$cm['active']!==1||(int)$lm['active']!==1||$cm['land']!==null||$lm['land']!==null){
  $db->query("UPDATE docking_contact SET touching=0 WHERE id=1");return;
 }
 $attached=(int)$lm['dependent']===1&&(int)($lm['dependency']??0)===3;
 if($attached){
  $db->query("UPDATE docking_contact SET touching=1,docked=1 WHERE id=1");
  return;
 }
 // A craft attached to ANY other parent cannot be docked independently.
 if((int)$cm['dependent']===1||(int)$lm['dependent']===1)return;
 $r=(float)$cm['radius_length']+(float)$lm['radius_length'];
 $g=moonDockGeometry($cm,$lm);
 $swept=moonDockSweptDistance($before[3],$before[6],$cm,$lm);
 $contact=$swept<=$r+1.0;
 if(!$contact){
  if((int)$state['touching']===1||(int)$state['docked']===1)$db->query('UPDATE docking_contact SET touching=0,docked=0 WHERE id=1');
  return;
 }
 if((int)$state['touching']===1)return;
 // Check geometrical mating at END of tick rather than allowing an off-axis
 // collision to snap the craft into place.
 $dock=$g['dockable']&&$g['range_m']>=$r-0.5&&$g['range_m']<=$r+1.;
 $event=$dock?'DOCKED':'CONTACT';
 $count=$dock?0:moonDockDamageCount($g['relative_speed_mps']);
 $damageCm=$dock?0:moonDockFailComponents($db,3,$count,$tick,$g['relative_speed_mps']);
 $damageLm=$dock?0:moonDockFailComponents($db,6,$count,$tick,$g['relative_speed_mps']);
 if(!$dock){
  moonDockResolveImpact($db,$before[3],$before[6],$cm,$lm);
 }
 if($dock){
  // LM becomes a child of CSM at a forward-facing mount. A negative
  // offset is intentional: child offset is measured behind CSM +X.
  $distance=$r;
  $offset=-$distance;
  $q=$db->prepare('UPDATE objects SET dependent=1,dependency=3,offset_distance=?,delta_x=?,delta_y=?,delta_z=? WHERE id=6');
  $vx=(float)$cm['delta_x'];$vy=(float)$cm['delta_y'];$vz=(float)$cm['delta_z'];
  $q->bind_param('dddd',$offset,$vx,$vy,$vz);$q->execute();
 }
 $q=$db->prepare('UPDATE docking_contact SET touching=1,docked=?,last_tick=?,last_event=?,last_speed_mps=?,last_port_gap_m=?,last_damage_cm=?,last_damage_lm=? WHERE id=1');
 $flag=$dock?1:0;
 $q->bind_param('iisddii',$flag,$tick,$event,$g['relative_speed_mps'],$g['port_gap_m'],$damageCm,$damageLm);$q->execute();
 $q=$db->prepare('INSERT INTO docking_events(sim_tick,event_type,speed_mps,port_gap_m,damage_cm,damage_lm) VALUES(?,?,?,?,?,?)');
 $q->bind_param('isddii',$tick,$event,$g['relative_speed_mps'],$g['port_gap_m'],$damageCm,$damageLm);$q->execute();
}
