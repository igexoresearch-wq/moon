<?php
declare(strict_types=1);
/** Rendezvous radar: deterministic ideal line-of-sight measurement (no occlusion yet). */
function moonRendezvousRadarTick(mysqli $db,int $tick):void {
 $objects=$db->query("SELECT id,x,y,z,delta_x,delta_y,delta_z,pitch,yaw,roll,active FROM objects WHERE id IN (3,6) ORDER BY id FOR UPDATE")->fetch_all(MYSQLI_ASSOC);
 if(count($objects)!==2)throw new RuntimeException('Rendezvous craft records missing');
 $by=[];foreach($objects as $v)$by[(int)$v['id']]=$v;
 $states=$db->query("SELECT component_key,state FROM reliability_components WHERE component_key IN
 ('cm_rendezvous_radar','lm_rendezvous_radar','cm_radar_responder','lm_radar_responder') FOR UPDATE")->fetch_all(MYSQLI_ASSOC);
 if(count($states)!==4)throw new RuntimeException('Install rendezvous_radar_schema.sql first');
 $on=[];foreach($states as $x)$on[$x['component_key']]=(int)$x['state']===1;
 // Upgrade existing deployments automatically; no destructive schema migration.
 $db->query("INSERT IGNORE INTO computer_registers(craft_id,register_key,numeric_value,valid) VALUES (3,'RENDEZVOUS_RANGE_RATE_MPS',NULL,0),(6,'RENDEZVOUS_RANGE_RATE_MPS',NULL,0)");
 $q=$db->prepare("UPDATE computer_registers SET numeric_value=?,valid=?,updated_tick=? WHERE craft_id=? AND register_key=?");
 foreach([3,6] as $craft){
  $other=$craft===3?6:3;$prefix=$craft===3?'cm':'lm';$otherPrefix=$other===3?'cm':'lm';
  $valid=($on[$prefix.'_rendezvous_radar']??false)&&($on[$otherPrefix.'_radar_responder']??false)
    &&(int)$by[$craft]['active']===1&&(int)$by[$other]['active']===1;
  $metrics=['RENDEZVOUS_RANGE_M'=>null,'RENDEZVOUS_RANGE_RATE_MPS'=>null,'RENDEZVOUS_OFF_AXIS_DEG'=>null,
   'RENDEZVOUS_AZIMUTH_DEG'=>null,'RENDEZVOUS_ELEVATION_DEG'=>null];
  if($valid){
   $dx=(float)$by[$other]['x']-(float)$by[$craft]['x'];
   $dy=(float)$by[$other]['y']-(float)$by[$craft]['y'];
   $dz=(float)$by[$other]['z']-(float)$by[$craft]['z'];
   $range=sqrt($dx*$dx+$dy*$dy+$dz*$dz);
   $valid=$range>0.001 && $range<=740000.;
   if($valid){
    // Closing-positive signed range rate: negative derivative of relative distance.
    $rvx=(float)$by[$other]['delta_x']-(float)$by[$craft]['delta_x'];
    $rvy=(float)$by[$other]['delta_y']-(float)$by[$craft]['delta_y'];
    $rvz=(float)$by[$other]['delta_z']-(float)$by[$craft]['delta_z'];
    $closingRate=-($dx*$rvx+$dy*$rvy+$dz*$rvz)/$range;
    $p=deg2rad((float)$by[$craft]['pitch']);$y=deg2rad((float)$by[$craft]['yaw']);$roll=deg2rad((float)$by[$craft]['roll']);
    // Forward (+X), lateral-right and up, consistent with simulator pitch/yaw convention.
    $forward=[cos($p)*cos($y),cos($p)*sin($y),sin($p)];
    $right=[-sin($y),cos($y),0.];
    $up=[-sin($p)*cos($y),-sin($p)*sin($y),cos($p)];
    $dir=[$dx/$range,$dy/$range,$dz/$range];
    $f=$dir[0]*$forward[0]+$dir[1]*$forward[1]+$dir[2]*$forward[2];
    $l=$dir[0]*$right[0]+$dir[1]*$right[1]+$dir[2]*$right[2];
    $u=$dir[0]*$up[0]+$dir[1]*$up[1]+$dir[2]*$up[2];
    $l2=$l*cos($roll)+$u*sin($roll);$u2=-$l*sin($roll)+$u*cos($roll);
    $metrics=['RENDEZVOUS_RANGE_M'=>$range,
       'RENDEZVOUS_RANGE_RATE_MPS'=>$closingRate,
       'RENDEZVOUS_OFF_AXIS_DEG'=>rad2deg(acos(max(-1.,min(1.,$f)))),
       'RENDEZVOUS_AZIMUTH_DEG'=>rad2deg(atan2($l2,$f)),
       'RENDEZVOUS_ELEVATION_DEG'=>rad2deg(atan2($u2,hypot($f,$l2)))];
   }
  }
  $flag=$valid?1:0;
  foreach($metrics as $name=>$value){$q->bind_param('diiis',$value,$flag,$tick,$craft,$name);$q->execute();}
 }
}
