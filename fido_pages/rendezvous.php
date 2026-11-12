<?php
$cm=$estimates[3]??null;$lm=$estimates[6]??null;
?>
<h2>004 / RENDEZVOUS FLIGHT DATA</h2>
<?php if(!$cm||!$lm||(int)$cm['active']!==1||(int)$lm['active']!==1):?>
<p>RENDEZVOUS TRAJECTORY DATA UNAVAILABLE</p>
<?php else:
$separation=[];$relativeVelocity=[];
foreach(['x','y','z'] as $axis)$separation[]=(float)$lm[$axis]-(float)$cm[$axis];
foreach(['delta_x','delta_y','delta_z'] as $axis)$relativeVelocity[]=(float)$lm[$axis]-(float)$cm[$axis];
$range=fidoNorm($separation);$relSpeed=fidoNorm($relativeVelocity);
$rangeRate=$range>0?-array_sum(array_map(static fn($a,$b)=>$a*$b,$separation,$relativeVelocity))/$range:null;
$lateral=$rangeRate===null?null:sqrt(max(0.,$relSpeed*$relSpeed-$rangeRate*$rangeRate));
?>
<div class="fd-grid">
<section><h3>RELATIVE MOTION</h3><?php fidoRows(['RANGE KM'=>fidoN($range/1000,4),'RANGE RATE M/S'=>fidoN($rangeRate===null?null:$rangeRate,2),'REL SPEED M/S'=>fidoN($relSpeed,2),'TRANSVERSE SPEED M/S'=>fidoN($lateral===null?null:$lateral,2)]);?></section>
<section><h3>RENDEZVOUS CONDITIONS</h3><?php fidoRows(['CLOSURE'=>$rangeRate===null?'---':($rangeRate>0?'CLOSING':($rangeRate<0?'OPENING':'NO RANGE CHANGE')),'PRIMARY VEHICLE'=>'CSM','SECONDARY VEHICLE'=>'LM','INTERCEPT SOLUTION'=>'GROUND RTCC TARGETING']);?></section>
</div>
<div class="fd-note">RTCC TARGETING USES ONLY THE FIDO ESTIMATED STATE. <a href="rtcc_solution.php" target="_blank" rel="noopener" style="color:#b4e4cf">CALCULATE RENDEZVOUS SOLUTION</a>. DOES NOT COMMAND ENGINES.</div>
<?php endif;?>
