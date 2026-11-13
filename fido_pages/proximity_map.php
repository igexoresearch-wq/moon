<?php
// Display 007 intentionally uses FIDO's estimated relative positions, but
// only renders the markers when the real radar/responder power and observed
// range/range-rate registers indicate a valid linked ping.
$cm=$estimates[3]??null;$lm=$estimates[6]??null;
$power=[];
foreach($conn->query("SELECT component_key,state FROM reliability_components WHERE component_key IN
 ('cm_rendezvous_radar','lm_radar_responder','lm_rendezvous_radar','cm_radar_responder')")->fetch_all(MYSQLI_ASSOC) as $p)
 $power[$p['component_key']]=(int)$p['state']===1;
$validRadar=false;$reportedRange=null;$reportedRate=null;
foreach([[3,'cm_rendezvous_radar','lm_radar_responder'],[6,'lm_rendezvous_radar','cm_radar_responder']] as [$craft,$radar,$responder]){
 if(!($power[$radar]??false)||!($power[$responder]??false))continue;
 $q=$conn->prepare("SELECT register_key,numeric_value,valid,updated_tick FROM computer_registers
 WHERE craft_id=? AND register_key IN ('RENDEZVOUS_RANGE_M','RENDEZVOUS_RANGE_RATE_MPS')");
 $q->bind_param('i',$craft);$q->execute();
 $regs=[];foreach($q->get_result()->fetch_all(MYSQLI_ASSOC) as $row)$regs[$row['register_key']]=$row;
 $r=$regs['RENDEZVOUS_RANGE_M']??null;$v=$regs['RENDEZVOUS_RANGE_RATE_MPS']??null;
 if($r&&$v&&(int)$r['valid']===1&&(int)$v['valid']===1&&
  $r['updated_tick']!==null&&(int)$r['updated_tick']>=$groundTick-5){
  $validRadar=true;$reportedRange=(float)$r['numeric_value'];$reportedRate=(float)$v['numeric_value'];break;
 }
}
function fidoProximityScale(float $r):float {
 $v=max(10.,$r*1.35);
 $order=pow(10.,floor(log10($v)));
 foreach([1.,2.,5.,10.] as $n)if($n*$order>=$v)return $n*$order;
 return 10.*$order;
}
?>
<h2>007 / RENDEZVOUS PROXIMITY PLOT</h2>
<?php if(!$cm||!$lm||!$validRadar):?>
<p class="fd-note">NO VALID RADAR/RESPONDER PAIR. ENABLE A RENDEZVOUS RADAR AND THE OPPOSING SPACECRAFT RESPONDER; WAIT FOR A CURRENT RANGE/RANGE-RATE PING. The display does not fabricate a visual fix from FIDO estimates alone.</p>
<?php else:
$relative=rtccSub(rtccState($lm)['p'],rtccState($cm)['p']);
$estimateDistance=rtccNorm($relative);
$extent=fidoProximityScale(max($reportedRange,$estimateDistance));
$scale=940./$extent;$x1=170.;$y1=260.;
$x2=$x1+($relative[0]/max($estimateDistance,1.))*min(940.,$estimateDistance*$scale);
$y2=$y1-($relative[1]/max($estimateDistance,1.))*min(160.,abs($relative[1])*$scale);
$distanceShown=hypot($x2-$x1,$y2-$y1);
?>
<svg viewBox="0 0 1240 500" width="100%" role="img" aria-label="Estimated distance between CSM and LM scaled to current radar">
<rect width="1240" height="500" fill="#0c1723"/>
<?php for($n=0;$n<=10;$n++):$x=170+$n*94;?>
<line x1="<?=$x?>" y1="72" x2="<?=$x?>" y2="420" stroke="#243746" stroke-width="1"/>
<text x="<?=$x?>" y="449" text-anchor="middle" fill="#b1c3cf" font-size="12"><?=number_format($n*$extent/10./1000.,$extent<1000?3:1)?> km</text>
<?php endfor;?>
<line x1="170" y1="260" x2="<?=round($x2,2)?>" y2="<?=round($y2,2)?>" stroke="#d1c8aa" stroke-dasharray="4 6" stroke-width="1"/>
<circle cx="170" cy="260" r="8" fill="#eac88b"/>
<text x="184" y="244" fill="#eac88b" font-size="17">CSM</text>
<circle cx="<?=round($x2,2)?>" cy="<?=round($y2,2)?>" r="8" fill="#84d9cc"/>
<text x="<?=round($x2+12,2)?>" y="<?=round($y2-12,2)?>" fill="#84d9cc" font-size="17">LM</text>
<text x="27" y="37" fill="#d1e2e6" font-size="15">DYNAMIC SCALE / ESTIMATED PLANAR SEPARATION</text>
<text x="27" y="480" fill="#bacbd2" font-size="13">1 PIXEL ≈ <?=number_format($extent/940.,2)?> m / resolution limited by estimate and radar sample</text>
</svg>
<div class="fd-grid"><section><h3>OBSERVED RADAR</h3>
<?php fidoRows(['RANGE M'=>fidoN($reportedRange,1),'CLOSING RATE M/S'=>fidoN($reportedRate,2),'RADAR PAIR'=>'ACQUIRED']);?>
</section><section><h3>GROUND ESTIMATE</h3><?php fidoRows([
 'ESTIMATED RANGE M'=>fidoN($estimateDistance,1),
 'LINEAR DISPLAY SCALE M'=>fidoN($extent,0),
 'ESTIMATE MET TICK'=>(string)$groundTick]);?></section></div>
<p class="fd-note">Screen positions derive from FIDO estimates; radar only authorizes the displayed contact and supplies observed range/range-rate. A projected XY separation is not a full 3D bearing. When closer, the distance scale automatically tightens.</p>
<?php endif;?>
