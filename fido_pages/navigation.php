<?php
// 005 displays FIDO's own estimated state only; never reads true physics
// or the spacecraft's separate onboard navigation.
?>
<h2>005 / GROUND NAVIGATION SOLUTION</h2>
<p class="fd-note">RTCC GROUND ESTIMATES / 60-SECOND SIMULATED TRACKING FIXES</p>
<div class="fd-grid">
<?php foreach([3,6] as $craft):
 $g=$estimates[$craft]??null;
 if(!$g)continue;
 $body=fidoBody($g,$estimates[1],$estimates[2]);
 $orb=fidoOrbit($g,$body);
?>
<section>
<h3><?= $craft===3?'COMMAND MODULE':'LUNAR MODULE' ?></h3>
<?php fidoRows([
 'SOLUTION TICK'=>(string)$groundTick,
 'STATUS'=>(string)$g['status'],
 'LAST TRACKING FIX'=>$g['last_tracking_tick']===null?'AWAITING':(string)$g['last_tracking_tick'],
 'REFERENCE BODY'=>(string)$body['name'],
 'ALTITUDE KM'=>fidoN($orb['alt'],3),
 'X KM'=>fidoN((float)$g['x']/1000,4),
 'Y KM'=>fidoN((float)$g['y']/1000,4),
 'Z KM'=>fidoN((float)$g['z']/1000,4),
 'VX M/S'=>fidoN((float)$g['delta_x'],3),
 'VY M/S'=>fidoN((float)$g['delta_y'],3),
 'VZ M/S'=>fidoN((float)$g['delta_z'],3),
]);?>
</section>
<?php endforeach;?>
</div>
<p class="fd-note">GROUND ESTIMATES ARE IN THE EARTH-ORIGIN INERTIAL FRAME. TRACKING FIXES CURRENTLY USE A QUANTIZED TEST MEASUREMENT ADAPTER, NOT A PHYSICAL RADIO RANGE/DOPPLER ORBIT DETERMINATION MODEL. NO UPDATE IS SENT TO THE CREW COMPUTER.</p>
