<?php
declare(strict_types=1);
/**
 * One-second estimated-attitude integration only. No writes to objects.
 * User-selected five decimal place rate quantization.
 * Current simple Euler approximation is not a full Apollo gimbaled IMU model.
 */
function moonImuTick(mysqli $conn,float $dt,int $tick):void {
 $q=$conn->query("SELECT i.object_id,i.pitch_deg,i.yaw_deg,i.roll_deg,
 o.pitch_rate,o.yaw_rate,o.roll_rate,c.state AS component_state,c.component_key
 FROM imu i JOIN objects o ON o.id=i.object_id JOIN reliability_components c
 ON c.component_key=IF(i.object_id=3,'cm_imu','lm_imu')
 WHERE i.object_id IN (3,6) FOR UPDATE");
 $update=$conn->prepare("UPDATE imu SET pitch_deg=?,yaw_deg=?,roll_deg=?,last_tick=? WHERE object_id=?");
 while($r=$q->fetch_assoc()){
  if((int)$r['component_state']!==1)continue;
  // Continuous IMU sampling is subject to its own per-tick reliability rule.
  $check=moonReliabilityCommand($conn,(string)$r['component_key'],'IMU read');
  if(!$check['allowed'])continue;
  $values=[];
  foreach(['pitch','yaw','roll'] as $axis){
   $rate=round((float)$r[$axis.'_rate'],5);
   $angle=(float)$r[$axis.'_deg']+$rate*$dt;
   $values[$axis]=fmod(fmod($angle,360.0)+360.0,360.0);
  }
  $id=(int)$r['object_id'];
  $update->bind_param('dddii',$values['pitch'],$values['yaw'],$values['roll'],$tick,$id);
  $update->execute();
 }
}
