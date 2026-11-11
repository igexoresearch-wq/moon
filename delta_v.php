<?php
declare(strict_types=1);
/**
 * Once per one-second physics tick AFTER moonGMeterTick.
 * Integrates the instrument's signed axial proper acceleration only:
 * delta-v [m/s] += g_forward [g0] * 9.80665 [m/s^2] * 1 [s].
 * No direct reads of object velocities or gravity. No double-integration.
 */
function moonDeltaVTick(mysqli $db,int $tick,float $dt=1.0):void {
 if($dt<=0.||!is_finite($dt))throw new InvalidArgumentException('Invalid delta-V time step');
 $rows=$db->query('SELECT craft_id,accumulated_mps,last_sample_tick,samples_integrated FROM delta_v_accumulators ORDER BY craft_id FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
 $sample=$db->prepare('SELECT sample_tick,g_forward,valid FROM g_meter_samples WHERE craft_id=?');
 $component=$db->prepare('SELECT state FROM reliability_components WHERE component_key=?');
 $apply=$db->prepare('UPDATE delta_v_accumulators SET accumulated_mps=?,last_sample_tick=?,samples_integrated=?,status=? WHERE craft_id=?');
 $hold=$db->prepare('UPDATE delta_v_accumulators SET status=? WHERE craft_id=?');
 foreach($rows as $r){
  $craft=(int)$r['craft_id'];
  if(!in_array($craft,[3,6],true))continue;
  $key=$craft===3?'cm_g_meter':'lm_g_meter';
  $component->bind_param('s',$key);$component->execute();
  $state=$component->get_result()->fetch_assoc();
  $sample->bind_param('i',$craft);$sample->execute();
  $g=$sample->get_result()->fetch_assoc();
  $currentTick=isset($g['sample_tick'])?(int)$g['sample_tick']:null;
  $lastTick=$r['last_sample_tick']===null?null:(int)$r['last_sample_tick'];
  $reason=null;
  if(!$state||(int)$state['state']!==1)$reason='G METER UNAVAILABLE';
  elseif(!$g||(int)$g['valid']!==1||$g['g_forward']===null)$reason='WAITING FOR G SAMPLE';
  elseif($currentTick!==$tick)$reason='G SAMPLE STALE';
  elseif($lastTick!==null&&$currentTick<=$lastTick)$reason='ALREADY INTEGRATED';
  if($reason!==null){
   $hold->bind_param('si',$reason,$craft);$hold->execute();
   continue;
  }
  // If the sensor was off for any period, the next fresh measurement
  // has already re-established its baseline; never fill missed ticks.
  $acceleration=(float)$g['g_forward']*9.80665;
  if(!is_finite($acceleration))throw new RuntimeException('Nonfinite G-meter acceleration');
  $total=(float)$r['accumulated_mps']+$acceleration*$dt;
  $samples=(int)$r['samples_integrated']+1;
  $status='MEASURING';
  $apply->bind_param('diisi',$total,$currentTick,$samples,$status,$craft);$apply->execute();
 }
}
