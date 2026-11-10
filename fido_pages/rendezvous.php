<?php
$cm=$objects[3]??null;$lm=$objects[6]??null;
?>
<h2>004 / CM-LM RELATIVE MOTION</h2>
<?php if(!$cm||!$lm):?><p>CRAFT RECORDS UNAVAILABLE</p><?php else:
$delta=[];$velocity=[];foreach(['x','y','z'] as $k)$delta[]=(float)$lm[$k]-(float)$cm[$k];
foreach(['delta_x','delta_y','delta_z'] as $k)$velocity[]=(float)$lm[$k]-(float)$cm[$k];
$range=fidoNorm($delta);$relative=fidoNorm($velocity);$closing=$range>0?-array_sum(array_map(fn($a,$b)=>$a*$b,$delta,$velocity))/$range:null;
?>
<div class="fd-grid">
<section><h3>RELATIVE POSITION · KM</h3><?php fidoRows(['DX'=>fidoN($delta[0]/1000,3),'DY'=>fidoN($delta[1]/1000,3),'DZ'=>fidoN($delta[2]/1000,3),'RANGE'=>fidoN($range/1000,4)]);?></section>
<section><h3>RELATIVE VELOCITY · M/S</h3><?php fidoRows(['DVX'=>fidoN($velocity[0],3),'DVY'=>fidoN($velocity[1],3),'DVZ'=>fidoN($velocity[2],3),'SPEED'=>fidoN($relative,3)]);?></section>
<section><h3>LINE-OF-SIGHT RATE</h3><?php fidoRows(['CLOSING (+) M/S'=>fidoN($closing,3),'RATE SIGN'=>$closing===null?'---':($closing>0?'CLOSING':($closing<0?'OPENING':'STATIONARY')),'CRAFT 1'=>'COMMAND MODULE','CRAFT 2'=>'LM ASCENT']);?></section>
</div>
<div class="fd-note">RELATIVE MOTION CALCULATED FROM SIMULATION STATE. NOT A RADAR LOCK OR A COMPUTED RENDEZVOUS MANEUVER.</div>
<?php endif;?>
