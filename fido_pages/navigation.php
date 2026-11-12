<?php
// 005 Ground-only orbit determination. NEVER display truth position or velocity here.
$required=['fido_navigation_states','fido_navigation_bodies','fido_tracking_observations'];
foreach($required as $table){
 if(!$conn->query("SHOW TABLES LIKE '".$table."'")->fetch_row()){
  echo '<h2>005 / GROUND NAVIGATION</h2><p>GROUND NAVIGATION NOT INSTALLED</p><p>IMPORT FIDO_NAVIGATION_SCHEMA.SQL</p>';return;
 }
}
$ground=$conn->query('SELECT * FROM fido_navigation_states ORDER BY craft_id')->fetch_all(MYSQLI_ASSOC);
$bodyRows=$conn->query('SELECT * FROM fido_navigation_bodies ORDER BY body_id')->fetch_all(MYSQLI_ASSOC);
$bodies=[];foreach($bodyRows as $r)$bodies[(int)$r['body_id']]=$r;
$onboard=[];
if($conn->query("SHOW TABLES LIKE 'navigation_states'")->fetch_row()){
 $q=$conn->query('SELECT * FROM navigation_states WHERE craft_id IN (3,6)');
 while($r=$q->fetch_assoc())$onboard[(int)$r['craft_id']]=$r;
}
echo '<h2>005 / GROUND NAVIGATION SOLUTION</h2>';
echo '<p class="fd-note">INDEPENDENT FIDO PREDICTION · TRACKING RECONSTRUCTION TEST MODEL · 60 SEC OBSERVATION INTERVAL</p>';
if(!$ground){echo '<p>AWAITING FIRST GROUND NAVIGATION TICK</p>';return;}
echo '<div class="fd-grid">';
foreach($ground as $g){
 $craft=(int)$g['craft_id'];if(!in_array($craft,[3,6],true))continue;
 $position=[(float)$g['x'],(float)$g['y'],(float)$g['z']];
 $ref=isset($bodies[1])?$bodies[1]:null;
 if(isset($bodies[2])&&$ref){
  $distEarth=sqrt(($position[0]-(float)$bodies[1]['x'])**2+($position[1]-(float)$bodies[1]['y'])**2+($position[2]-(float)$bodies[1]['z'])**2);
  $distMoon=sqrt(($position[0]-(float)$bodies[2]['x'])**2+($position[1]-(float)$bodies[2]['y'])**2+($position[2]-(float)$bodies[2]['z'])**2);
  $earthRadius=(float)($objects[1]['radius_length']??6371000);
  $moonRadius=(float)($objects[2]['radius_length']??1737400);
  if($distMoon-$moonRadius<$distEarth-$earthRadius)$ref=$bodies[2];
 }
 $alt=null;
 if($ref){
  $distance=sqrt(($position[0]-(float)$ref['x'])**2+($position[1]-(float)$ref['y'])**2+($position[2]-(float)$ref['z'])**2);
  $radius=(float)($objects[(int)$ref['body_id']]['radius_length']??0);
  if($radius>0)$alt=($distance-$radius)/1000;
 }
 $difference=null;
 if(isset($onboard[$craft])){
  $on=$onboard[$craft];
  $difference=sqrt(((float)$g['x']-(float)$on['x'])**2+((float)$g['y']-(float)$on['y'])**2+((float)$g['z']-(float)$on['z'])**2);
 }
 echo '<section><h3>'.($craft===3?'COMMAND MODULE':'LUNAR MODULE').'</h3>';
 fidoRows([
  'GROUND SOLUTION TICK'=>(string)(int)$g['last_tick'],
  'SOLUTION STATUS'=>(string)$g['status'],
  'LAST TRACK FIX'=>$g['last_tracking_tick']===null?'AWAITING':(string)(int)$g['last_tracking_tick'],
  'REFERENCE BODY'=>!$ref?'---':((int)$ref['body_id']===2?'MOON':'EARTH'),
  'ALTITUDE KM'=>fidoN($alt,3),
  'X KM'=>fidoN((float)$g['x']/1000,4),
  'Y KM'=>fidoN((float)$g['y']/1000,4),
  'Z KM'=>fidoN((float)$g['z']/1000,4),
  'VX M/S'=>fidoN((float)$g['vx'],3),
  'VY M/S'=>fidoN((float)$g['vy'],3),
  'VZ M/S'=>fidoN((float)$g['vz'],3),
  'GROUND / ONBOARD SEP M'=>fidoN($difference,2),
 ]);
 echo '</section>';
}
echo '</div>';
echo '<p class="fd-note">GROUND / ONBOARD SEPARATION IS A DIFFERENCE BETWEEN TWO ESTIMATES, NOT A TRUE POSITION ERROR. THIS TRAINING MODEL SIMULATES HIGH-RESOLUTION GROUND TRACKING FIXES; IT DOES NOT YET SOLVE REAL RADIO RANGE/DOPPLER STATION GEOMETRY. NO AUTOMATIC UPDATES ARE SENT TO THE SPACECRAFT.</p>';
