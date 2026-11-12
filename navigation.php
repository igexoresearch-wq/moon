<?php
declare(strict_types=1);
/**
 * Independent inertial navigation estimate.
 * Bootstrap once from rounded true position and velocity for an in-progress test.
 * Thereafter NEVER read spacecraft truth except in the explicit bootstrap path.
 * Specific force comes exclusively from g_meter_samples acceleration_*.
 * Gravity uses predicted Earth/Moon positions and onboard estimated craft position.
 *
 * Approximation: sensor axes are currently simulation inertial XYZ. A true
 * Apollo-stable-platform/body-to-inertial transform is a later upgrade.
 */
function moonNavigationInstalled(mysqli $db):bool {
 static $installed=null;
 if($installed!==null)return $installed;
 return $installed=(bool)$db->query("SHOW TABLES LIKE 'navigation_states'")->fetch_row()
   &&(bool)$db->query("SHOW TABLES LIKE 'navigation_bodies'")->fetch_row()
   &&(bool)$db->query("SHOW TABLES LIKE 'navigation_telemetry'")->fetch_row();
}
function moonNavigationGravity(array $p,array $bodies):array {
 $a=[0.,0.,0.];
 foreach($bodies as $body){
  $dx=[(float)$body['x']-$p[0],(float)$body['y']-$p[1],(float)$body['z']-$p[2]];
  $r2=$dx[0]**2+$dx[1]**2+$dx[2]**2;
  if($r2<=1.)continue;
  $scale=6.67430e-11*(float)$body['mass_kg']/($r2*sqrt($r2));
  for($k=0;$k<3;$k++)$a[$k]+=$scale*$dx[$k];
 }
 return $a;
}
function moonNavigationSeed(mysqli $db,int $tick):void {
 // Only used at first installation, or after an explicit clock reset.
 $rows=$db->query('SELECT id,x,y,z,delta_x,delta_y,delta_z,dry_mass FROM objects WHERE id IN (1,2,3,6) ORDER BY id FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
 $objects=[];foreach($rows as $row)$objects[(int)$row['id']]=$row;
 foreach([1,2,3,6] as $id)if(!isset($objects[$id]))throw new RuntimeException('Navigation bootstrap object missing: '.$id);
 $body=$db->prepare('REPLACE INTO navigation_bodies(body_id,x,y,z,vx,vy,vz,mass_kg,last_tick,seeded_tick) VALUES (?,?,?,?,?,?,?,?,?,?)');
 foreach([1,2] as $id){
  $r=$objects[$id];$x=round((float)$r['x'],0);$y=round((float)$r['y'],0);$z=round((float)$r['z'],0);
  $vx=round((float)$r['delta_x'],3);$vy=round((float)$r['delta_y'],3);$vz=round((float)$r['delta_z'],3);
  $mass=(float)$r['dry_mass'];
  $body->bind_param('idddddddii',$id,$x,$y,$z,$vx,$vy,$vz,$mass,$tick,$tick);$body->execute();
 }
 $state=$db->prepare("REPLACE INTO navigation_states(craft_id,x,y,z,vx,vy,vz,last_tick,seeded_tick,last_sensor_tick,status) VALUES (?,?,?,?,?,?,?,?,?,NULL,'INITIALIZED')");
 foreach([3,6] as $id){
  $r=$objects[$id];$x=round((float)$r['x'],0);$y=round((float)$r['y'],0);$z=round((float)$r['z'],0);
  $vx=round((float)$r['delta_x'],2);$vy=round((float)$r['delta_y'],2);$vz=round((float)$r['delta_z'],2);
  $state->bind_param('iddddddii',$id,$x,$y,$z,$vx,$vy,$vz,$tick,$tick);$state->execute();
 }
 $db->query('DELETE FROM navigation_telemetry');
}
/**
 * Called after the G-meter sample and before sim_clock increments, every tick.
 * Missing sensor measurements HOLD non-gravitational acceleration at zero.
 * Gravity-only propagation continues and marks navigation as DEGRADED.
 */
function moonNavigationTick(mysqli $db,int $tick):void {
 if(!moonNavigationInstalled($db))return;
 $states=$db->query('SELECT * FROM navigation_states ORDER BY craft_id FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
 $bodies=$db->query('SELECT * FROM navigation_bodies ORDER BY body_id FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
 $previousTick=$tick-1;
 if(count($states)!==2||count($bodies)!==2 ||
    (int)$states[0]['last_tick']>$tick || (int)$bodies[0]['last_tick']>$tick){
  moonNavigationSeed($db,$tick);
  return;
 }
 $bd=[];foreach($bodies as $row)$bd[(int)$row['body_id']]=$row;
 if(!isset($bd[1],$bd[2]))throw new RuntimeException('Navigation reference bodies missing');
 if((int)$bd[2]['last_tick']!==$previousTick)throw new RuntimeException('Navigation Moon prediction out of sync');
 // Move onboard Moon prediction using its own previously stored ephemeris.
 // Earth is fixed in this simulator; no lookup of the true Moon after seed.
 $dt=1.0;
 $earth=$bd[1];$moon=$bd[2];
 $p=[(float)$moon['x'],(float)$moon['y'],(float)$moon['z']];
 $v=[(float)$moon['vx'],(float)$moon['vy'],(float)$moon['vz']];
 $g0=moonNavigationGravity($p,[$earth]);
 $p2=[];for($k=0;$k<3;$k++)$p2[$k]=$p[$k]+$v[$k]*$dt+0.5*$g0[$k]*$dt*$dt;
 $g1=moonNavigationGravity($p2,[$earth]);
 $v2=[];for($k=0;$k<3;$k++)$v2[$k]=$v[$k]+0.5*($g0[$k]+$g1[$k])*$dt;
 $u=$db->prepare('UPDATE navigation_bodies SET x=?,y=?,z=?,vx=?,vy=?,vz=?,last_tick=? WHERE body_id=2');
 $u->bind_param('ddddddi',$p2[0],$p2[1],$p2[2],$v2[0],$v2[1],$v2[2],$tick);$u->execute();
 $bd[2]=array_merge($moon,['x'=>$p2[0],'y'=>$p2[1],'z'=>$p2[2]]);
 $sensor=$db->prepare('SELECT sample_tick,valid,acceleration_x_mps2,acceleration_y_mps2,acceleration_z_mps2 FROM g_meter_samples WHERE craft_id=?');
 $update=$db->prepare('UPDATE navigation_states SET x=?,y=?,z=?,vx=?,vy=?,vz=?,last_tick=?,last_sensor_tick=?,status=? WHERE craft_id=?');
 $log=$db->prepare('INSERT INTO navigation_telemetry(craft_id,tick,x,y,z,vx,vy,vz,status) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE x=VALUES(x),y=VALUES(y),z=VALUES(z),vx=VALUES(vx),vy=VALUES(vy),vz=VALUES(vz),status=VALUES(status)');
 foreach($states as $r){
  $id=(int)$r['craft_id'];if(!in_array($id,[3,6],true))continue;
  if((int)$r['last_tick']!==$previousTick)throw new RuntimeException('Navigation state out of sync for craft '.$id);
  $sensor->bind_param('i',$id);$sensor->execute();$sample=$sensor->get_result()->fetch_assoc();
  $valid=$sample && (int)$sample['valid']===1 && (int)$sample['sample_tick']===$tick;
  $force=[0.,0.,0.];$sensorTick=$r['last_sensor_tick']===null?null:(int)$r['last_sensor_tick'];
  if($valid){
   foreach(['acceleration_x_mps2','acceleration_y_mps2','acceleration_z_mps2'] as $k=>$key){
    if($sample[$key]===null||!is_finite((float)$sample[$key])){$valid=false;break;}
    $force[$k]=(float)$sample[$key];
   }
  }
  if($valid)$sensorTick=$tick;
  else $force=[0.,0.,0.];
  $pos=[(float)$r['x'],(float)$r['y'],(float)$r['z']];
  $vel=[(float)$r['vx'],(float)$r['vy'],(float)$r['vz']];
  $g0=moonNavigationGravity($pos,[$bd[1],$bd[2]]);
  $next=[];
  for($k=0;$k<3;$k++)$next[$k]=$pos[$k]+$vel[$k]*$dt+0.5*($g0[$k]+$force[$k])*$dt*$dt;
  $g1=moonNavigationGravity($next,[$bd[1],$bd[2]]);
  $velNext=[];
  for($k=0;$k<3;$k++)$velNext[$k]=$vel[$k]+0.5*($g0[$k]+$g1[$k]+2*$force[$k])*$dt;
  foreach(array_merge($next,$velNext) as $n)if(!is_finite($n))throw new RuntimeException('Navigation estimate nonfinite');
  $status=$valid?'INTEGRATING':'DEGRADED SENSOR';
  $update->bind_param('ddddddiisi',$next[0],$next[1],$next[2],$velNext[0],$velNext[1],$velNext[2],$tick,$sensorTick,$status,$id);
  $update->execute();
  if($tick%60===0){
   $log->bind_param('iidddddds',$id,$tick,$next[0],$next[1],$next[2],$velNext[0],$velNext[1],$velNext[2],$status);$log->execute();
  }
 }
}
