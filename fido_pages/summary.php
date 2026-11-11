<?php
$target=$objects[3]??null;
if(!$target){echo '<p>CSM TRAJECTORY UNAVAILABLE</p>';return;}
$body=fidoBody($target,$objects[1],$objects[2]);$orb=fidoOrbit($target,$body);
?>
<h2>001 / FLIGHT TRAJECTORY</h2>
<div class="fd-grid">
<section><h3>VEHICLE / CENTRAL BODY</h3><?php fidoRows(['VEHICLE'=>'CSM','PRIMARY'=>$body['name'],'ALTITUDE KM'=>fidoN($orb['alt']===null?null:$orb['alt'],2),'VELOCITY M/S'=>fidoN($orb['speed']===null?null:$orb['speed']*1000,1)]);?></section>
<section><h3>FLIGHT CONDITIONS</h3><?php fidoRows(['FLIGHT PATH ANG DEG'=>fidoN($orb['fpa'],3),'VERTICAL RATE M/S'=>fidoN($orb['radial']===null?null:$orb['radial'],1),'HORIZ SPEED M/S'=>fidoN($orb['horizontal']===null?null:$orb['horizontal'],1),'MOTION'=>$orb['radial']===null?'---':($orb['radial']>0?'ASCENDING':($orb['radial']<0?'DESCENDING':'LEVEL'))]);?></section>
<section><h3>ORBITAL CONDITIONS</h3><?php fidoRows(['APOGEE KM'=>fidoN($orb['apo']===null?null:$orb['apo'],2),'PERIGEE KM'=>fidoN($orb['peri']===null?null:$orb['peri'],2),'INCLINATION DEG'=>fidoN($orb['inc'],3),'PERIOD SEC'=>fidoN($orb['period']===null?null:$orb['period']*60,2)]);?></section>
<section><h3>FLIGHT DYNAMICS</h3><?php fidoRows(['ORBIT TYPE'=>$orb['status'],'ECCENTRICITY'=>fidoN($orb['ecc'],6),'CENTRAL BODY'=>$body['name'],'MANEUVER SOLUTION'=>'NOT AVAILABLE']);?></section>
</div>
<div class="fd-note">TRAJECTORY VALUES ARE CURRENT ESTIMATES. NOMINAL PROFILE COMPARISON NOT AVAILABLE.</div>
