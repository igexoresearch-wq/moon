<?php ?>
<h2>003 / CURRENT ORBITS</h2>
<p class="fd-note">INSTANTANEOUS TWO-BODY ORBITAL ELEMENTS. NOT A FUTURE TRAJECTORY PREDICTION.</p>
<?php foreach([3,6] as $id):if(!isset($objects[$id]))continue;$o=$objects[$id];$body=fidoBody($o,$objects[1],$objects[2]);$orb=fidoOrbit($o,$body); ?>
<section><h3><?=htmlspecialchars($o['name'])?> / <?=htmlspecialchars($body['name'])?></h3>
<div class="fd-grid"><?php
 fidoRows(['ALTITUDE KM'=>fidoN($orb['alt'],3),'SPEED KM/S'=>fidoN($orb['speed'],5),'APOAPSIS KM'=>fidoN($orb['apo'],3),'PERIAPSIS KM'=>fidoN($orb['peri'],3),'ECCENTRICITY'=>fidoN($orb['ecc'],6),'INCLINATION DEG'=>fidoN($orb['inc'],3),'PERIOD MIN'=>fidoN($orb['period'],3),'RADIAL M/S'=>fidoN($orb['radial'],3)]);
?></div></section>
<?php endforeach;?>
