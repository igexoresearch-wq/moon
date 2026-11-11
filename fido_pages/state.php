<?php
$target=$objects[3]??null;
if(!$target){echo '<p>CSM FLIGHT DATA UNAVAILABLE</p>';return;}
$body=fidoBody($target,$objects[1],$objects[2]);$o=fidoOrbit($target,$body);
?>
<h2>003 / POWERED FLIGHT PARAMETERS</h2>
<div class="fd-grid">
<section><h3>TRAJECTORY</h3><?php fidoRows(['ALTITUDE KM'=>fidoN($o['alt']===null?null:$o['alt'],3),'VELOCITY M/S'=>fidoN($o['speed']===null?null:$o['speed']*1000,1),'FLIGHT PATH ANG DEG'=>fidoN($o['fpa'],3),'VERTICAL VEL M/S'=>fidoN($o['radial']===null?null:$o['radial'],1)]);?></section>
<section><h3>INSERTION CONDITIONS</h3><?php fidoRows(['APOGEE KM'=>fidoN($o['apo']===null?null:$o['apo'],3),'PERIGEE KM'=>fidoN($o['peri']===null?null:$o['peri'],3),'INCLINATION DEG'=>fidoN($o['inc'],3),'ORBIT PERIOD SEC'=>fidoN($o['period']===null?null:$o['period']*60,3)]);?></section>
</div>
<p class="fd-note">NOMINAL TRAJECTORY, ABORT LIMITS, ENGINE CUTOFF TARGET AND GUIDANCE REFERENCE ARE NOT YET AVAILABLE. FLIGHT-PATH-ANGLE/V-VELOCITY COMPARISON WILL REQUIRE A PLANNED PROFILE.</p>
