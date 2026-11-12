<?php
declare(strict_types=1);
/**
 * Independent FIDO orbit estimate. Uses its OWN ground ephemeris and state;
 * never reads navigation_states or G-meter acceleration. Simulated ground
 * tracking creates measured inertial position/velocity at 60 s intervals.
 * Tracking is a TEST surrogate for a real range/range-rate orbit solver:
 * true XYZ/velocity may be read ONLY inside moonFidoTrackingObservation()
 * or on explicit initialization. Never use physics truth for propagation.
 */
function moonFidoNavigationInstalled(mysqli $db):bool {
 static $ready=null;
 if($ready!==null)return $ready;
 foreach(['fido_navigation_bodies','fido_navigation_states','fido_navigation_telemetry','fido_tracking_observations'] as $table){
  $result=$db->query("SHOW TABLES LIKE '".$table."'");
  if(!$result->fetch_row())return $ready=false;
 }
 return $ready=true;
}
function moonFidoGravity(array $position,array $bodies):array {
 $out=[0.,0.,0.];
 foreach($bodies as $body){
  $d=[(float)$body['x']-$position[0],(float)$body['y']-$position[1],(float)$body['z']-$position[2]];
  $r2=$d[0]*$d[0]+$d[1]*$d[1]+$d[2]*$d[2];
  if($r2<1.)continue;
  $factor=6.67430e-11*(float)$body['mass_kg']/($r2*sqrt($r2));
  for($k=0;$k<3;$k++)$out[$k]+=$d[$k]*$factor;
 }
 return $out;
}
function moonFidoPropagate(array $pos,array $velocity,array $bodies,float $dt):array {
 $first=moonFidoGravity($pos,$bodies);
 $next=[];for($i=0;$i<3;$i++)$next[$i]=$pos[$i]+$dt*$velocity[$i]+0.5*$dt*$dt*$first[$i];
 $second=moonFidoGravity($next,$bodies);
 $v=[];for($i=0;$i<3;$i++)$v[$i]=$velocity[$i]+0.5*$dt*($first[$i]+$second[$i]);
 foreach(array_merge($next,$v) as $value)if(!is_finite($value))throw new RuntimeException('FIDO navigation nonfinite');
 return [$next,$v];
}
/** TEST ground measurement adapter. 0.1m and 0.001m/s resolution.
 * Real range / Doppler station geometry and tracking errors are not yet modeled.
 * The navigation integrator below receives ONLY this output, not true values.
 */
function moonFidoTrackingObservation(mysqli $db,int $craft,int $tick):array {
 $q=$db->prepare('SELECT x,y,z,delta_x,delta_y,delta_z FROM objects WHERE id=?');
 $q->bind_param('i',$craft);$q->execute();$raw=$q->get_result()->fetch_assoc();
 if(!$raw)throw new RuntimeException('FIDO tracking object missing');
 $obs=[];
 foreach(['x','y','z'] as $name)$obs[$name]=round((float)$raw[$name],1);
 foreach(['x','y','z'] as $name)$obs['v'.$name]=round((float)$raw['delta_'.$name],3);
 $q=$db->prepare("INSERT INTO fido_tracking_observations
 (craft_id,tick,measured_x,measured_y,measured_z,measured_vx,measured_vy,measured_vz)
 VALUES (?,?,?,?,?,?,?,?)
 ON DUPLICATE KEY UPDATE measured_x=VALUES(measured_x),measured_y=VALUES(measured_y),measured_z=VALUES(measured_z),
 measured_vx=VALUES(measured_vx),measured_vy=VALUES(measured_vy),measured_vz=VALUES(measured_vz)");
 $q->bind_param('iidddddd',$craft,$tick,$obs['x'],$obs['y'],$obs['z'],$obs['vx'],$obs['vy'],$obs['vz']);$q->execute();
 return $obs;
}
function moonFidoNavigationSeed(mysqli $db,int $tick):void {
 // Only bootstrap at install or a backward clock reset; do not continuously sync to truth.
 $rows=$db->query('SELECT id,x,y,z,delta_x,delta_y,delta_z,dry_mass FROM objects WHERE id IN (1,2,3,6) ORDER BY id FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
 $objects=[];foreach($rows as $r)$objects[(int)$r['id']]=$r;
 foreach([1,2,3,6] as $id)if(!isset($objects[$id]))throw new RuntimeException('FIDO seed missing object '.$id);
 $body=$db->prepare('REPLACE INTO fido_navigation_bodies(body_id,x,y,z,vx,vy,vz,mass_kg,last_tick,seeded_tick) VALUES(?,?,?,?,?,?,?,?,?,?)');
 foreach([1,2] as $id){
  $r=$objects[$id];$x=round((float)$r['x'],1);$y=round((float)$r['y'],1);$z=round((float)$r['z'],1);
  $vx=round((float)$r['delta_x'],4);$vy=round((float)$r['delta_y'],4);$vz=round((float)$r['delta_z'],4);
  $mass=(float)$r['dry_mass'];
  $body->bind_param('idddddddii',$id,$x,$y,$z,$vx,$vy,$vz,$mass,$tick,$tick);$body->execute();
 }
 $state=$db->prepare("REPLACE INTO fido_navigation_states
 (craft_id,x,y,z,vx,vy,vz,last_tick,seeded_tick,last_tracking_tick,status)
 VALUES(?,?,?,?,?,?,?,?,?,NULL,'INITIALIZED')");
 foreach([3,6] as $id){
  $r=$objects[$id];$x=round((float)$r['x'],1);$y=round((float)$r['y'],1);$z=round((float)$r['z'],1);
  $vx=round((float)$r['delta_x'],3);$vy=round((float)$r['delta_y'],3);$vz=round((float)$r['delta_z'],3);
  $state->bind_param('iddddddii',$id,$x,$y,$z,$vx,$vy,$vz,$tick,$tick);$state->execute();
 }
 $db->query('DELETE FROM fido_navigation_telemetry');
 $db->query('DELETE FROM fido_tracking_observations');
}
function moonFidoNavigationTick(mysqli $db,int $tick):void {
 if(!moonFidoNavigationInstalled($db))return;
 $states=$db->query('SELECT * FROM fido_navigation_states ORDER BY craft_id FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
 $bodies=$db->query('SELECT * FROM fido_navigation_bodies ORDER BY body_id FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
 if(count($states)!==2||count($bodies)!==2){
  moonFidoNavigationSeed($db,$tick);return;
 }
 $body=[];foreach($bodies as $b)$body[(int)$b['body_id']]=$b;
 if(!isset($body[1],$body[2]) ||(int)$states[0]['last_tick']>$tick ||
    (int)$states[1]['last_tick']>$tick ||(int)$body[2]['last_tick']>$tick){
  moonFidoNavigationSeed($db,$tick);return;
 }
 $previous=$tick-1;
 if((int)$body[2]['last_tick']!==$previous)throw new RuntimeException('FIDO lunar ephemeris tick mismatch');
 $moon=$body[2];
 [$moonP,$moonV]=moonFidoPropagate([(float)$moon['x'],(float)$moon['y'],(float)$moon['z']],
  [(float)$moon['vx'],(float)$moon['vy'],(float)$moon['vz']],[$body[1]],1.);
 $moveMoon=$db->prepare('UPDATE fido_navigation_bodies SET x=?,y=?,z=?,vx=?,vy=?,vz=?,last_tick=? WHERE body_id=2');
 $moveMoon->bind_param('ddddddi',$moonP[0],$moonP[1],$moonP[2],$moonV[0],$moonV[1],$moonV[2],$tick);$moveMoon->execute();
 foreach(['x','y','z'] as $k=>$axis)$body[2][$axis]=$moonP[$k];
 $update=$db->prepare('UPDATE fido_navigation_states SET x=?,y=?,z=?,vx=?,vy=?,vz=?,last_tick=?,last_tracking_tick=?,status=? WHERE craft_id=?');
 $history=$db->prepare('INSERT INTO fido_navigation_telemetry(craft_id,tick,x,y,z,vx,vy,vz,status) VALUES(?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE x=VALUES(x),y=VALUES(y),z=VALUES(z),vx=VALUES(vx),vy=VALUES(vy),vz=VALUES(vz),status=VALUES(status)');
 foreach($states as $r){
  $craft=(int)$r['craft_id'];
  if(!in_array($craft,[3,6],true))continue;
  if((int)$r['last_tick']!==$previous)throw new RuntimeException('FIDO state tick mismatch for '.$craft);
  [$p,$v]=moonFidoPropagate([(float)$r['x'],(float)$r['y'],(float)$r['z']],
   [(float)$r['vx'],(float)$r['vy'],(float)$r['vz']],[$body[1],$body[2]],1.);
  $trackingTick=$r['last_tracking_tick']===null?null:(int)$r['last_tracking_tick'];
  $status='COAST PREDICT';
  if($tick%60===0){
   $obs=moonFidoTrackingObservation($db,$craft,$tick);
   $p=[$obs['x'],$obs['y'],$obs['z']];$v=[$obs['vx'],$obs['vy'],$obs['vz']];
   $trackingTick=$tick;$status='TRACKING FIX';
  }
  $update->bind_param('ddddddiisi',$p[0],$p[1],$p[2],$v[0],$v[1],$v[2],$tick,$trackingTick,$status,$craft);$update->execute();
  if($tick%60===0){
   $history->bind_param('iidddddds',$craft,$tick,$p[0],$p[1],$p[2],$v[0],$v[1],$v[2],$status);$history->execute();
  }
 }
}
