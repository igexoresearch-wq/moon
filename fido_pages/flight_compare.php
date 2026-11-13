<?php
require_once __DIR__.'/../fido_projection.php';
$saved=[];
if($conn->query("SHOW TABLES LIKE 'fido_saved_solutions'")->fetch_row())
 $saved=$conn->query('SELECT id,label,estimate_tick,saved_at FROM fido_saved_solutions ORDER BY id DESC LIMIT 30')->fetch_all(MYSQLI_ASSOC);
$requested=max(0,(int)($_GET['solution']??0));
if(!$requested&&$saved)$requested=(int)$saved[0]['id'];
?>
<h2>008 / SAVED MANEUVER VS GROUND FLIGHT</h2>
<p class="fd-note">SAVED FIDO RTCC IDEAL BURNS VS POST-MANEUVER FIDO GROUND-NAVIGATION HISTORY. NO REAL SIMULATOR XYZ IS READ.</p>
<?php if(!$saved):?><p>NO SAVED RTCC SOLUTIONS. CALCULATE AN RTCC CANDIDATE AND SELECT SAVE THIS SOLUTION.</p><?php
else:
?>
<form method="get" action="fido_page.php">
<input type="hidden" name="display" value="flight_compare">
<label>LOAD SAVED SOLUTION
<select name="solution" onchange="this.form.submit()" style="background:#243842;color:#e2f0ea;padding:6px">
<?php foreach($saved as $entry):?>
<option value="<?=(int)$entry['id']?>" <?=$requested===(int)$entry['id']?'selected':''?>>#<?=(int)$entry['id']?> / <?=htmlspecialchars($entry['label'],ENT_QUOTES)?></option>
<?php endforeach;?>
</select></label>
<button type="submit" style="background:#2b4a4e;color:#eaf8f1">LOAD</button>
</form>
<?php
$q=$conn->prepare('SELECT * FROM fido_saved_solutions WHERE id=?');$q->bind_param('i',$requested);$q->execute();
$row=$q->get_result()->fetch_assoc();
if(!$row){echo '<p>SAVED SOLUTION NOT FOUND</p>';return;}
try{
 $snapshot=json_decode($row['snapshot_json'],true,64,JSON_THROW_ON_ERROR);
 $plan=json_decode($row['plan_json'],true,64,JSON_THROW_ON_ERROR);
 $trace=json_decode($row['prediction_json'],true,64,JSON_THROW_ON_ERROR);
 $predicted=$trace['cm']??[];$lm=$trace['lm']??[];
 if(count($predicted)<2||count($lm)<2)throw new DomainException('Incomplete saved projection');
 $start=(int)$trace['origin_tick'];$end=(int)$trace['horizon_tick'];
 $historyQuery=$conn->prepare("SELECT tick,x,y,z,status FROM fido_navigation_telemetry
  WHERE craft_id=3 AND tick>=? AND tick<=? ORDER BY tick LIMIT 650");
 $historyQuery->bind_param('ii',$start,$end);$historyQuery->execute();
 $recorded=$historyQuery->get_result()->fetch_all(MYSQLI_ASSOC);
 $track=[];
 foreach($recorded as $point){
  $track[]=['t'=>(int)$point['tick'],
   'p'=>[(float)$point['x'],(float)$point['y'],(float)$point['z']],
   'moon'=>$predicted[0]['moon']];
 }
 $plotted=[3=>['path'=>$predicted],6=>['path'=>$lm]];
 if(count($track)>1)$plotted[99]=['path'=>$track];
 $latestDifference=null;$latestMet=null;
 if($track){
  $last=end($track);
  $lastT=(int)$last['t'];$best=null;$gap=PHP_INT_MAX;
  foreach($predicted as $point){
   $d=abs((int)$point['t']-$lastT);
   if($d<$gap){$gap=$d;$best=$point;}
  }
  if($best!==null&&$gap<=30){
   $latestDifference=rtccNorm(rtccSub($last['p'],$best['p']));
   $latestMet=sprintf('%03d:%02d:%02d',intdiv($lastT,3600),intdiv($lastT%3600,60),$lastT%60);
  }
 }
}catch(Throwable $error){
 echo '<p>COMPARE UNAVAILABLE: '.htmlspecialchars($error->getMessage(),ENT_QUOTES).'</p>';return;
}
?>
<p class="fd-note">SAVED #<?=(int)$row['id']?> / ESTIMATE MET TICK <?=(int)$row['estimate_tick']?> / PLANNED START TICK <?=$start?> / END <?=$end?></p>
<?=fidoMapSvg($snapshot,$plotted,1,1240,545)?>
<div class="fd-grid">
<section><h3>PLANNED BURNS</h3>
<?php fidoRows([
 'BURN 1 MET'=>$plan['burn1']['met']??'---',
 'BURN 1 DV M/S'=>fidoN((float)($plan['burn1']['dv_mps']??0),2),
 'BURN 2 MET'=>$plan['burn2']['met']??'---',
 'BURN 2 DV M/S'=>fidoN((float)($plan['burn2']['dv_mps']??0),2),
 'MODEL'=>'IDEAL INSTANTANEOUS IMPULSES'
]);?>
</section>
<section><h3>TRACKED DIFFERENCE</h3><?php fidoRows([
 'GROUND HISTORY SAMPLES'=>(string)count($track),
 'LAST COMPARED MET'=>$latestMet??'AWAITING OBSERVATIONS',
 'POSITION ERROR M'=>$latestDifference===null?'---':fidoN($latestDifference,1),
 'STATUS'=>'GOLD DASH: PLAN CSM / CYAN DOT: PLAN LM / WHITE: GROUND ESTIMATE'
]);?></section>
</div>
<p class="fd-note">The white line shows FIDO ground-navigation history (tracked fixes plus coast estimates), not simulator truth; history updates once per 60 simulated seconds. The comparison is approximate to the nearest saved prediction sample (at most 30 seconds). During lunar blackout, white-line entries are propagated estimates, not new radio fixes. The frozen plan does not include finite-burn thrust, attitude error, or actual ignition delays.</p>
<?php endif;?>
