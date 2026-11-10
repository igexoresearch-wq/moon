<?php
declare(strict_types=1);
/**
 * Sample actual simulated velocities once per tick at 0.01 m/s resolution.
 * Proper acceleration = (measured velocity difference / simulated elapsed time)
 *                    - average gravitational acceleration.
 * This excludes ordinary gravitational free fall; NOT derived from thrust.
 * CM historical ACCEL G and LM T/W instruments were primarily axial.
 */
function moonGMeterGravity(array $craft,array $bodies):array {
 $out=[0.,0.,0.];
 foreach($bodies as $body){
  if((int)$body['id']===(int)$craft['id'])continue;
  $delta=[(float)$body['x']-(float)$craft['x'],
          (float)$body['y']-(float)$craft['y'],
          (float)$body['z']-(float)$craft['z']];
  $r2=$delta[0]**2+$delta[1]**2+$delta[2]**2;
  if($r2<=1.)continue;
  $factor=6.67430e-11*(float)$body['dry_mass']/($r2*sqrt($r2));
  for($i=0;$i<3;$i++)$out[$i]+=$factor*$delta[$i];
 }
 return $out;
}
function moonGMeterTick(mysqli $db,int $tick):void {
 // Recover missing baseline rows without resetting previously collected samples.
 $db->query('INSERT IGNORE INTO g_meter_samples(craft_id) VALUES (3),(6)');
 $objects=$db->query('SELECT id,active,x,y,z,delta_x,delta_y,delta_z,pitch,yaw,dry_mass FROM objects WHERE id IN (1,2,3,6) ORDER BY id FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
 $indexed=[];foreach($objects as $o)$indexed[(int)$o['id']]=$o;
 if(!isset($indexed[1],$indexed[2],$indexed[3],$indexed[6]))throw new RuntimeException('G meter celestial or craft objects missing');
 $components=$db->query("SELECT component_key,state FROM reliability_components WHERE component_key IN ('cm_g_meter','lm_g_meter') FOR UPDATE")->fetch_all(MYSQLI_ASSOC);
 if(count($components)!==2)throw new RuntimeException('Install g_meter_schema.sql first');
 $powered=[];foreach($components as $row)$powered[$row['component_key']]=(int)$row['state']===1;
 $q=$db->prepare("SELECT * FROM g_meter_samples WHERE craft_id=? FOR UPDATE");
 $update=$db->prepare("UPDATE g_meter_samples SET sample_tick=?,sampled_at=NOW(6),
 velocity_x_mps=?,velocity_y_mps=?,velocity_z_mps=?,
 gravity_x_mps2=?,gravity_y_mps2=?,gravity_z_mps2=?,
 acceleration_x_mps2=?,acceleration_y_mps2=?,acceleration_z_mps2=?,
 g_magnitude=?,g_forward=?,valid=? WHERE craft_id=?");
 foreach([3,6] as $craftId){
  $key=$craftId===3?'cm_g_meter':'lm_g_meter';$craft=$indexed[$craftId];
  $q->bind_param('i',$craftId);$q->execute();$previous=$q->get_result()->fetch_assoc();
  if(!$previous)throw new RuntimeException('G meter sample record missing');
  $grav=moonGMeterGravity($craft,[$indexed[1],$indexed[2]]);
  // Quantize each observed velocity component BEFORE differencing.
  $v=[round((float)$craft['delta_x'],2),round((float)$craft['delta_y'],2),round((float)$craft['delta_z'],2)];
  $valid=0;$a=[null,null,null];$magnitude=null;$forward=null;
  $isOn=($powered[$key]??false)&&(int)$craft['active']===1;
  if($isOn && (int)$previous['valid']>=0 && $previous['sample_tick']!==null){
   $elapsed=$tick-(int)$previous['sample_tick'];
   // Only consecutive samples while powered; no enormous restart spikes.
   if($elapsed===1 && $previous['velocity_x_mps']!==null){
    $priorVel=[(float)$previous['velocity_x_mps'],(float)$previous['velocity_y_mps'],(float)$previous['velocity_z_mps']];
    $priorGravity=[(float)$previous['gravity_x_mps2'],(float)$previous['gravity_y_mps2'],(float)$previous['gravity_z_mps2']];
    for($k=0;$k<3;$k++)$a[$k]=($v[$k]-$priorVel[$k])/$elapsed-($grav[$k]+$priorGravity[$k])/2;
    $magnitude=sqrt($a[0]**2+$a[1]**2+$a[2]**2)/9.80665;
    $pitch=deg2rad((float)$craft['pitch']);$yaw=deg2rad((float)$craft['yaw']);
    $nose=[cos($pitch)*cos($yaw),cos($pitch)*sin($yaw),sin($pitch)];
    $forward=($a[0]*$nose[0]+$a[1]*$nose[1]+$a[2]*$nose[2])/9.80665;
    $valid=1;
   }
  }
  // Inactive or powered off = no instrument sample and no retained baseline.
  $sampleTick=$isOn?$tick:null;
  $vx=$isOn?$v[0]:null;$vy=$isOn?$v[1]:null;$vz=$isOn?$v[2]:null;
  $gx=$isOn?$grav[0]:null;$gy=$isOn?$grav[1]:null;$gz=$isOn?$grav[2]:null;
  $ax=$a[0];$ay=$a[1];$az=$a[2];
  $update->bind_param('idddddddddddii',$sampleTick,$vx,$vy,$vz,$gx,$gy,$gz,$ax,$ay,$az,$magnitude,$forward,$valid,$craftId);
  $update->execute();
 }
}
