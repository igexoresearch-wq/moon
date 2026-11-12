<?php ?>
<h2>002 / ORBITAL PARAMETERS</h2>
<?php foreach([3,6] as $id): if(!isset($estimates[$id])||(int)$estimates[$id]['active']!==1)continue;
$o=$estimates[$id];$body=fidoBody($o,$estimates[1],$estimates[2]);$orb=fidoOrbit($o,$body); ?>
<section><h3><?=$id===3?'CSM':'LM'?> / <?=htmlspecialchars(strtoupper((string)$body['name']))?></h3>
<div class="fd-grid">
<?php fidoRows(['ALTITUDE KM'=>fidoN($orb['alt']===null?null:$orb['alt'],3),
'APOGEE KM'=>fidoN($orb['apo']===null?null:$orb['apo'],3),
'PERIGEE KM'=>fidoN($orb['peri']===null?null:$orb['peri'],3),
'ORBIT PERIOD SEC'=>fidoN($orb['period']===null?null:$orb['period']*60,3),
'INCLINATION DEG'=>fidoN($orb['inc'],3),
'ECCENTRICITY'=>fidoN($orb['ecc'],6),
'FLIGHT PATH ANG DEG'=>fidoN($orb['fpa'],3),
'ORBIT TYPE'=>$orb['status']]);?>
</div></section>
<?php endforeach;?>
<div class="fd-note">INSTANTANEOUS ORBITAL ELEMENTS; PERIOD AND APOGEE NOT APPLICABLE TO ESCAPE TRAJECTORIES.</div>
