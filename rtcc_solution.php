<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
require_once __DIR__.'/rtcc_rendezvous.php';
require_once __DIR__.'/fido_ground.php';
require_once __DIR__.'/rtcc_maneuver.php';
header('Cache-Control: no-store');
$wantJson=($_GET['format']??'')==='json';
$profile=(string)($_GET['search']??'standard');
$profiles=[
 'near'=>['delays_s'=>[0,300,600],'flights_s'=>[300,600,1200,1800]],
 'standard'=>['delays_s'=>[0,600,1800,3600],'flights_s'=>[1200,2400,3600,5400,7200]],
 'extended'=>['delays_s'=>[0,900,1800,3600],'flights_s'=>[3600,5400,7200,10800,14400]]
];
if(!isset($profiles[$profile]))$profile='standard';
$maxDv=(string)($_GET['dv']??'1200');
if(!in_array($maxDv,['250','500','1200','2500'],true))$maxDv='1200';
$options=$profiles[$profile]+['max_injection_mps'=>(float)$maxDv];
try{
 // Read a single consistent FIDO ground-estimate epoch, never the objects table.
 $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
 $snapshot=fidoGroundSnapshot($conn);
 $estimates=$snapshot['estimates'];
 $epoch=$snapshot['tick'];
 $clock=$conn->query('SELECT tick FROM sim_clock WHERE id=1')->fetch_assoc();
 if(!$clock)throw new DomainException('MISSION CLOCK UNAVAILABLE');
 $now=(int)$clock['tick'];
 if($now<$epoch||$now-$epoch>5)throw new DomainException('FIDO GROUND ESTIMATE STALE - WAIT FOR TRACKING UPDATE');
 $conn->commit();
 // Guarantee absolute MET times at minute resolution: never move an
 // impulsive solver ignition to another time AFTER computing its trajectory.
 $planningTick=intdiv($epoch+179,60)*60; // first burn at least 2 minutes away
 $planning=rtccAdvanceToMet($estimates,$planningTick-$epoch);
 $result=rtccRendezvousSolve($planning,$options);
 $result['estimate_tick']=$epoch;
 $result['planning_epoch_tick']=$planningTick;
 // Read SPS model only. No reading of physical objects or onboard navigation.
 $sps=$conn->query("SELECT thrust_n,mass_flow_kg_s FROM propulsion_engines WHERE id=401")->fetch_assoc();
 $tank=$conn->query("SELECT remaining_kg FROM propulsion_tanks WHERE vehicle_id=4")->fetch_assoc();
 $exhaust=$sps && (float)$sps['mass_flow_kg_s']>0
  ?(float)$sps['thrust_n']/(float)$sps['mass_flow_kg_s']:null;
 $fuel=$tank?(float)$tank['remaining_kg']:null;
 $rcsRow=$conn->query('SELECT fuel_kg,oxidizer_kg,isp_s,thrust_n FROM rcs WHERE craft_id=4')->fetch_assoc();
 $rcsTotal=$rcsRow? min((float)$rcsRow['fuel_kg']/.4,(float)$rcsRow['oxidizer_kg']/.6):null;
 foreach($result['solutions'] as &$plan){
  $first=$planningTick+$plan['start_seconds'];
  $second=$planningTick+$plan['intercept_seconds'];
  $plan['burn1']=[
   'met_tick'=>$first,'met'=>rtccMetLabel($first),'hhmm'=>sprintf('%04d',rtccMetHhmm($first)),
   'dv_mps'=>$plan['injection_dv_mps'],'dv_code'=>rtccDvDsky($plan['injection_dv_mps']),
   'pitch_code'=>rtccAngleDsky($plan['injection_attitude'],'pitch'),
   'yaw_code'=>rtccAngleDsky($plan['injection_attitude'],'yaw')
  ];
  $plan['burn2']=[
   'met_tick'=>$second,'met'=>rtccMetLabel($second),'hhmm'=>sprintf('%04d',rtccMetHhmm($second)),
   'dv_mps'=>$plan['matching_dv_mps'],'dv_code'=>rtccDvDsky($plan['matching_dv_mps']),
   'pitch_code'=>rtccAngleDsky($plan['matching_attitude'],'pitch'),
   'yaw_code'=>rtccAngleDsky($plan['matching_attitude'],'yaw')
  ];
  $plan['fuel_budget']=$fuel!==null&&$exhaust!==null
   ?rtccFuelBudget(11660.,$fuel,$exhaust,[$plan['injection_dv_mps'],$plan['matching_dv_mps']]):null;
  $plan['mixed_budget']=$fuel!==null&&$exhaust!==null&&$rcsTotal!==null&&$rcsRow
   ?rtccMixedBudget(11660.,$fuel,$rcsTotal,$exhaust,
    (float)$rcsRow['isp_s']*9.80665,(float)$rcsRow['thrust_n'],
    [$plan['injection_dv_mps'],$plan['matching_dv_mps']]):null;
  foreach(['burn1','burn2'] as $i=>$burnKey){
   $selection=$plan['mixed_budget'][$i]??null;
   $plan[$burnKey]['program']=$selection['program']??32;
   $plan[$burnKey]['engine']=$selection['engine']??'SPS';
  }
  $plan['dsky_compatible']=$plan['burn1']['dv_code']!==null&&$plan['burn2']['dv_code']!==null
    &&$plan['burn1']['pitch_code']!==null&&$plan['burn2']['pitch_code']!==null;
 }
 unset($plan);
 $result['fuel_assumption']='Nominal bare CM+SM dry mass 11660 kg plus current SPS propellant; excludes attached LM and other stages';
 if($wantJson){
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($result,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);exit;
 }
}catch(Throwable $e){
 try{$conn->rollback();}catch(Throwable $ignored){}
 error_log('Moon RTCC targeting: '.$e->getMessage());
 if($wantJson){http_response_code(422);header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['status'=>'UNAVAILABLE','error'=>$e instanceof DomainException?$e->getMessage():'Targeting calculation unavailable']);exit;}
 $error=$e instanceof DomainException?$e->getMessage():'Targeting calculation unavailable; see PHP error log';
}
function rtccFmt(float $value,int $places=2):string {return number_format($value,$places,'.',',');}
function rtccTime(int $seconds):string {
 $hours=intdiv($seconds,3600);$minutes=intdiv($seconds%3600,60);$seconds%=60;
 return sprintf('%02d:%02d:%02d',$hours,$minutes,$seconds);
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>RTCC / Rendezvous Targeting</title>
<style>
*{box-sizing:border-box}body{background:#111b20;color:#d9e5df;font:15px 'Courier New',monospace;margin:0;padding:22px;max-width:1100px}
h1{font-size:21px;border-bottom:1px solid #62887d;padding-bottom:12px}h2{font-size:16px;color:#a6d5dc}
a{color:#a6d5dc}.note{color:#f0cf87;line-height:1.5}table{width:100%;border-collapse:collapse;font-size:13px}
td,th{padding:10px 6px;border-bottom:1px solid #3e5558;text-align:right}td:first-child,th:first-child{text-align:left}
.panel{border:1px solid #59737b;padding:14px;margin-top:16px}select,button{font:14px monospace;background:#253b40;color:#e4ece8;border:1px solid #73938b;padding:8px}label{display:grid;gap:7px;color:#a9d2cf}b{color:#fff}small{color:#adc7bf}
</style></head><body>
<h1>RTCC / RENDEZVOUS TARGETING — CM TO LM</h1>
<p><a href="fido_console.php?display=rendezvous">RETURN TO FIDO</a></p>
<form method="get" class="panel" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
<label>SEARCH WINDOW
<select name="search">
<option value="near" <?= $profile==='near'?'selected':'' ?>>NEAR / UP TO 40 MIN</option>
<option value="standard" <?= $profile==='standard'?'selected':'' ?>>STANDARD / UP TO 3 HOURS</option>
<option value="extended" <?= $profile==='extended'?'selected':'' ?>>EXTENDED / UP TO 5 HOURS</option>
</select></label>
<label>MAX DELTA-V PER IMPULSE
<select name="dv">
<?php foreach(['250','500','1200','2500'] as $limit): ?>
<option value="<?=$limit?>" <?= $maxDv===$limit?'selected':'' ?>><?=$limit?> M/S</option>
<?php endforeach; ?>
</select></label>
<button type="submit">CALCULATE MANEUVERS</button>
</form>
<p class="note">Solutions use FIDO's estimated states. Calculation is advisory and assumes impulsive burns, not finite engine firings.</p>
<?php if(isset($error)): ?>
<p class="note"><?=htmlspecialchars($error,ENT_QUOTES)?></p>
<?php else: ?>
<div class="panel"><h2>TRACKING SNAPSHOT</h2>
<p>RANGE <b><?=rtccFmt($result['range_m']/1000,3)?> KM</b> /
CLOSING RATE <b><?=rtccFmt((float)$result['closing_rate_mps'])?> M/S</b> /
RELATIVE SPEED <b><?=rtccFmt($result['relative_speed_mps'])?> M/S</b></p>
<p>STATUS <b><?=htmlspecialchars($result['status'])?></b> / 2-IMPULSE NUMERICAL TARGETING / ESTIMATE TICK <b><?=(int)$epoch?></b></p>
</div>
<?php if(!$result['solutions']): ?>
<p class="note">No safe candidate within the current search limits. No burn instructions issued. This does not prove rendezvous is impossible.</p>
<?php else: ?>
<h2>LOWEST TOTAL DELTA-V CANDIDATES</h2>
<p>ESTIMATE MET <?=rtccMetLabel($epoch)?> / CALCULATION EPOCH <?=rtccMetLabel($planningTick)?></p>
<table><thead><tr><th>OPTION</th><th>IGNITION IN</th><th>INTERCEPT IN</th><th>FIRST DV M/S</th><th>MATCH DV M/S</th><th>TOTAL DV M/S</th><th>MISS M</th></tr></thead><tbody>
<?php foreach($result['solutions'] as $idx=>$plan): ?>
<tr><td><?=($idx+1)?></td><td><?=rtccTime($plan['start_seconds'])?></td>
<td><?=rtccTime($plan['intercept_seconds'])?></td>
<td><?=rtccFmt($plan['injection_dv_mps'])?></td>
<td><?=rtccFmt($plan['matching_dv_mps'])?></td>
<td><?=rtccFmt($plan['total_dv_mps'])?></td>
<td><?=rtccFmt($plan['miss_m'])?></td></tr>
<?php endforeach; ?></tbody></table>
<?php $top=$result['solutions'][0];?>
<div class="panel"><h2>OPTION 1 / IDEAL MANEUVER VECTOR</h2>
<p>FIRST BURN — AT +<?=rtccTime($top['start_seconds'])?>:
<?php if($top['injection_attitude']): ?>
PITCH <?=rtccFmt($top['injection_attitude']['pitch_deg'],3)?> DEG /
YAW <?=rtccFmt($top['injection_attitude']['yaw_deg'],3)?> DEG;
<?php else: ?>NONE;
<?php endif; ?>
DELTA-V <?=rtccFmt($top['injection_dv_mps'],3)?> M/S</p>
<p>SECOND BURN — AT +<?=rtccTime($top['intercept_seconds'])?>:
<?php if($top['matching_attitude']): ?>
PITCH <?=rtccFmt($top['matching_attitude']['pitch_deg'],3)?> DEG /
YAW <?=rtccFmt($top['matching_attitude']['yaw_deg'],3)?> DEG;
<?php else: ?>NONE;
<?php endif; ?>
DELTA-V <?=rtccFmt($top['matching_dv_mps'],3)?> M/S</p>
<p>FIRST IMPULSE XYZ M/S:
<?=rtccFmt($top['dv1'][0],3)?> /
<?=rtccFmt($top['dv1'][1],3)?> /
<?=rtccFmt($top['dv1'][2],3)?></p>
<p>MATCH IMPULSE XYZ M/S:
<?=rtccFmt($top['dv2'][0],3)?> /
<?=rtccFmt($top['dv2'][1],3)?> /
<?=rtccFmt($top['dv2'][2],3)?></p>
<p>TRANSFER COAST <?=rtccTime($top['transfer_seconds'])?> /
PREDICTED NOMINAL POSITION MISS <?=rtccFmt($top['miss_m'],2)?> M</p>
</div>
<?php endif;?>
<?php if(!empty($result['solutions'])): ?>
<h2>DSKY P32 / MANEUVER ENTRY CARDS</h2>
<?php foreach($result['solutions'] as $index=>$plan): ?>
<div class="panel">
<h2>OPTION <?=($index+1)?> / TWO MANEUVERS</h2>
<?php foreach(['burn1'=>'FIRST BURN','burn2'=>'SECOND BURN'] as $key=>$label):
 $burn=$plan[$key];?>
<h2><?=$label?> / MET <?=htmlspecialchars($burn['met'])?> / PROGRAM <?=$burn['program']?> (<?=$burn['engine']?>)</h2>
<table><thead><tr><th>REGISTER</th><th>VALUE TO ENTER</th><th>MEANING</th></tr></thead><tbody>
<tr><td>R1</td><td><?=htmlspecialchars($burn['hhmm'])?></td><td>MISSION HHMM (100-HOUR DIGIT INFERRED)</td></tr>
<tr><td>R2</td><td><?=$burn['dv_code']===null?'NOT ENCODABLE':sprintf('%04d',$burn['dv_code'])?></td><td>DELTA-V <?=rtccFmt($burn['dv_mps'],2)?> M/S; ENTRY 0.1 M/S</td></tr>
<tr><td>R3</td><td><?=$burn['pitch_code']===null?'NOT ENCODABLE':sprintf('%04d',$burn['pitch_code'])?></td><td>INERTIAL PITCH / TENTHS DEG</td></tr>
<tr><td>R4</td><td><?=$burn['yaw_code']===null?'NOT ENCODABLE':sprintf('%04d',$burn['yaw_code'])?></td><td>INERTIAL YAW / TENTHS DEG</td></tr>
</tbody></table>
<?php endforeach;?>
<?php if($plan['mixed_budget']!==null):
 $firstMixed=$plan['mixed_budget'][0];$secondMixed=$plan['mixed_budget'][1]; ?>
<p>RECOMMENDED PROPULSION: FIRST <?=$firstMixed['engine']?> (P<?=$firstMixed['program']?>) /
SECOND <?=$secondMixed['engine']?> (P<?=$secondMixed['program']?>).</p>
<p>MIXED FUEL ESTIMATE: FIRST <?=rtccFmt($firstMixed['fuel_kg'],2)?> KG <?=$firstMixed['engine']?> /
SECOND <?=rtccFmt($secondMixed['fuel_kg'],2)?> KG <?=$secondMixed['engine']?> /
REMAINING SPS <?=rtccFmt($secondMixed['remaining_sps_kg'],1)?> KG /
REMAINING RCS <?=rtccFmt($secondMixed['remaining_rcs_kg'],1)?> KG.</p>
<p><?= $firstMixed['available']&&$secondMixed['available']?'NOMINAL PROPELLANT BUDGET AVAILABLE':'WARNING: NOMINAL PROPELLANT INSUFFICIENT' ?></p>
<?php endif;?>
<?php if($plan['fuel_budget']!==null):
 $firstFuel=$plan['fuel_budget'][0];$secondFuel=$plan['fuel_budget'][1]; ?>
<p>SPS-ONLY ALTERNATIVE ESTIMATE: FIRST <?=rtccFmt($firstFuel['fuel_kg'],1)?> KG /
SECOND <?=rtccFmt($secondFuel['fuel_kg'],1)?> KG /
TOTAL <?=rtccFmt($firstFuel['fuel_kg']+$secondFuel['fuel_kg'],1)?> KG /
AFTER BOTH <?=rtccFmt($secondFuel['remaining_kg'],1)?> KG</p>
<p><?= $firstFuel['available']&&$secondFuel['available']?'NOMINAL PROPELLANT BUDGET AVAILABLE':'WARNING: NOMINAL PROPELLANT INSUFFICIENT' ?></p>
<?php else: ?><p>SPS FUEL ESTIMATE UNAVAILABLE: INSTALL PROPULSION TABLES</p><?php endif;?>
<p class="note"><?= $plan['dsky_compatible']?'P32 / P33 ENTRY CODES AVAILABLE':'ONE OR MORE BURNS CANNOT BE ENTERED WITH FOUR-DIGIT P32; REPLAN OR CHANGE PROGRAM RANGE.' ?>
Enter the listed P32 or P33 program for each burn. Each burn must be entered and EXECUTED separately; do not queue the second before the first completes. Recalculate after the first physical burn.</p>
</div>
<?php endforeach;?>
<?php endif;?>
<div class="panel"><h2>FLIGHT DYNAMICS LIMITATIONS</h2>
<p class="note">ADVISORY OPTIMIZATION ONLY. P32/P33 ACCEPT THE ENCODED INPUTS AND EXECUTE SIMULATED BURNS, BUT IDEAL IMPULSIVE TRAJECTORY CANDIDATES ARE NOT FINITE-BURN-VALIDATED.
These are instantaneous inertial impulses, not executable finite engine burns.
Attitudes are NOT IMU-referenced; roll is unspecified. No hardware, fuel,
ignition, separation, docking, or final approach clearance has been certified.</p>
<p>TRACKING INPUT: INDEPENDENT FIDO GROUND-ESTIMATED CM/LM STATE. NOMINAL FUEL ASSUMES BARE CM+SM DRY MASS 11660 KG; ATTACHED LM OR OTHER MASS IS NOT INCLUDED. CALCULATION MODEL: EARTH + MOVING MOON GRAVITY.
TARGET LM IS ASSUMED TO COAST WITHOUT MANEUVERS DURING THE PLAN.</p></div>
<?php endif;?></body></html>
