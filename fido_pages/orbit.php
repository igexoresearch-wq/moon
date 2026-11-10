<?php ?>
<h2>002 / ORBITAL PARAMETERS</h2>
<?php foreach([3,6] as $id): if(!isset($objects[$id])||(int)$objects[$id]['active']!==1)continue;
$o=$objects[$id];$body=fidoBody($o,$objects[1],$objects[2]);$orb=fidoOrbit($o,$body); ?>
<section><h3><?=$id===3?'CSM':'LM'?> / <?=htmlspecialchars(strtoupper((string)$body['name']))?></h3>
<div class="fd-grid">
<?php fidoRows(['ALTITUDE N MI'=>fidoN($orb['alt']===null?null:$orb['alt']/1.852,3),
'APOGEE N MI'=>fidoN($orb['apo']===null?null:$orb['apo']/1.852,3),
'PERIGEE N MI'=>fidoN($orb['peri']===null?null:$orb['peri']/1.852,3),
'ORBIT PERIOD MIN'=>fidoN($orb['period'],3),
'INCLINATION DEG'=>fidoN($orb['inc'],3),
'ECCENTRICITY'=>fidoN($orb['ecc'],6),
'FLIGHT PATH ANG DEG'=>fidoN($orb['fpa'],3),
'ORBIT TYPE'=>$orb['status']]);?>
</div></section>
<?php endforeach;?>
<div class="fd-note">INSTANTANEOUS ORBITAL ELEMENTS; PERIOD AND APOGEE NOT APPLICABLE TO ESCAPE TRAJECTORIES.</div>
