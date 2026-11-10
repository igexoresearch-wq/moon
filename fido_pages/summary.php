<?php
$target=$objects[3]??null;
if(!$target){echo '<p>COMMAND MODULE STATE UNAVAILABLE</p>';return;}
$body=fidoBody($target,$objects[1],$objects[2]);$orb=fidoOrbit($target,$body);
?>
<h2>001 / FLIGHT DYNAMICS SUMMARY</h2>
<div class="fd-grid">
<section><h3>MISSION CLOCK</h3><?php fidoRows(['SIM TICK'=>number_format($tick),'LAST UPDATE'=>$updated,'REFERENCE BODY'=>$body['name']]);?></section>
<section><h3>TRACKED SPACECRAFT</h3><?php fidoRows(['VEHICLE'=>$target['name'],'STATUS'=>(int)$target['active']===1?'ACTIVE':'INACTIVE','FRAME'=>'SIMULATION XYZ']);?></section>
<section><h3>CURRENT POSITION · KM</h3><?php fidoRows(['X'=>fidoN((float)$target['x']/1000,3),'Y'=>fidoN((float)$target['y']/1000,3),'Z'=>fidoN((float)$target['z']/1000,3)]);?></section>
<section><h3>CURRENT VELOCITY · KM/S</h3><?php fidoRows(['VX'=>fidoN((float)$target['delta_x']/1000,5),'VY'=>fidoN((float)$target['delta_y']/1000,5),'VZ'=>fidoN((float)$target['delta_z']/1000,5)]);?></section>
<section><h3>OSCULATION / ORBIT</h3><?php fidoRows(['ALTITUDE KM'=>fidoN($orb['alt'],2),'SPEED KM/S'=>fidoN($orb['speed'],5),'APOAPSIS KM'=>fidoN($orb['apo'],2),'PERIAPSIS KM'=>fidoN($orb['peri'],2)]);?></section>
<section><h3>ORBIT STATUS</h3><?php fidoRows(['CLASS'=>$orb['status'],'ECCENTRICITY'=>fidoN($orb['ecc'],6),'PERIOD MIN'=>fidoN($orb['period'],2),'INCLINATION DEG'=>fidoN($orb['inc'],3)]);?></section>
</div>
<div class="fd-note">TWO-BODY INSTANTANEOUS ELEMENTS. NEXT MANEUVER: NOT COMPUTED. NO BURN PLAN LOADED.</div>
