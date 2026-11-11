<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
require_once __DIR__.'/rtcc_rendezvous.php';
header('Cache-Control: no-store');
$wantJson=($_GET['format']??'')==='json';
try{
 // Single consistent read-only snapshot. No changes to physics, tanks, or commands.
 $conn->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
 $rows=$conn->query('SELECT id,name,active,dependent,dependency,land,dry_mass,radius_length,x,y,z,delta_x,delta_y,delta_z FROM objects WHERE id IN (1,2,3,6)')->fetch_all(MYSQLI_ASSOC);
 $objects=[];foreach($rows as $row)$objects[(int)$row['id']]=$row;
 $clock=$conn->query('SELECT tick FROM sim_clock WHERE id=1')->fetch_assoc();
 $conn->commit();
 $result=rtccRendezvousSolve($objects);
 if($wantJson){
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($result,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT);exit;
 }
}catch(Throwable $e){
 if($conn->errno===0){} // no outward disclosure of database details
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
.panel{border:1px solid #59737b;padding:14px;margin-top:16px}b{color:#fff}small{color:#adc7bf}
</style></head><body>
<h1>RTCC / RENDEZVOUS TARGETING — CM TO LM</h1>
<p><a href="fido_console.php?display=rendezvous">RETURN TO FIDO</a></p>
<?php if(isset($error)): ?>
<p class="note"><?=htmlspecialchars($error,ENT_QUOTES)?></p>
<?php else: ?>
<div class="panel"><h2>TRACKING SNAPSHOT</h2>
<p>RANGE <b><?=rtccFmt($result['range_m']/1000,3)?> KM</b> /
CLOSING RATE <b><?=rtccFmt((float)$result['closing_rate_mps'])?> M/S</b> /
RELATIVE SPEED <b><?=rtccFmt($result['relative_speed_mps'])?> M/S</b></p>
<p>STATUS <b><?=htmlspecialchars($result['status'])?></b> / 2-IMPULSE NUMERICAL TARGETING</p>
</div>
<?php if(!$result['solutions']): ?>
<p class="note">No safe candidate within the current search limits. No burn instructions issued. This does not prove rendezvous is impossible.</p>
<?php else: ?>
<h2>LOWEST TOTAL DELTA-V CANDIDATES</h2>
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
<p>TRANSFER COAST <?=rtccTime($top['transfer_seconds'])?> /
PREDICTED NOMINAL POSITION MISS <?=rtccFmt($top['miss_m'],2)?> M</p>
</div>
<?php endif;?>
<div class="panel"><h2>FLIGHT DYNAMICS LIMITATIONS</h2>
<p class="note">ADVISORY SOLUTION ONLY. DO NOT ENTER AS A DSKY BURN PROGRAM YET.
These are instantaneous inertial impulses, not executable finite engine burns.
Attitudes are NOT IMU-referenced; roll is unspecified. No hardware, fuel,
ignition, separation, docking, or final approach clearance has been certified.</p>
<p>TRACKING INPUT: TRUE SIMULATED CM/LM STATE. CALCULATION MODEL: EARTH + MOVING MOON GRAVITY.
TARGET LM IS ASSUMED TO COAST WITHOUT MANEUVERS DURING THE PLAN.</p></div>
<?php endif;?></body></html>
