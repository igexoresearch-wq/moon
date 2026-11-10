<?php
$target=$objects[3]??null;
if(!$target){echo '<p>CSM TRAJECTORY UNAVAILABLE</p>';return;}
$body=fidoBody($target,$objects[1],$objects[2]);$orb=fidoOrbit($target,$body);
?>
<h2>001 / FLIGHT TRAJECTORY</h2>
<div class="fd-grid">
<section><h3>VEHICLE / CENTRAL BODY</h3><?php fidoRows(['VEHICLE'=>'CSM','PRIMARY'=>$body['name'],'ALTITUDE N MI'=>fidoN($orb['alt']===null?null:$orb['alt']/1.852,2),'VELOCITY FT/SEC'=>fidoN($orb['speed']===null?null:$orb['speed']*3280.839895,1)]);?></section>
<section><h3>FLIGHT CONDITIONS</h3><?php fidoRows(['FLIGHT PATH ANG DEG'=>fidoN($orb['fpa'],3),'VERTICAL RATE FT/SEC'=>fidoN($orb['radial']===null?null:$orb['radial']*3.280839895,1),'HORIZ SPEED FT/SEC'=>fidoN($orb['horizontal']===null?null:$orb['horizontal']*3.280839895,1),'MOTION'=>$orb['radial']===null?'---':($orb['radial']>0?'ASCENDING':($orb['radial']<0?'DESCENDING':'LEVEL'))]);?></section>
<section><h3>ORBITAL CONDITIONS</h3><?php fidoRows(['APOGEE N MI'=>fidoN($orb['apo']===null?null:$orb['apo']/1.852,2),'PERIGEE N MI'=>fidoN($orb['peri']===null?null:$orb['peri']/1.852,2),'INCLINATION DEG'=>fidoN($orb['inc'],3),'PERIOD MIN'=>fidoN($orb['period'],2)]);?></section>
<section><h3>FLIGHT DYNAMICS</h3><?php fidoRows(['ORBIT TYPE'=>$orb['status'],'ECCENTRICITY'=>fidoN($orb['ecc'],6),'CENTRAL BODY'=>$body['name'],'MANEUVER SOLUTION'=>'NOT AVAILABLE']);?></section>
</div>
<div class="fd-note">TRAJECTORY VALUES ARE CURRENT ESTIMATES. NOMINAL PROFILE COMPARISON NOT AVAILABLE.</div>
