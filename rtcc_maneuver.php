<?php
declare(strict_types=1);
/** Pure RTCC display and fuel-budget helpers, never touches objects. */
function rtccMetLabel(int $tick):string {
 if($tick<0)throw new InvalidArgumentException('MET negative');
 return sprintf('%03d:%02d:%02d',intdiv($tick,3600),intdiv($tick%3600,60),$tick%60);
}
function rtccMetHhmm(int $tick):int {
 if($tick<0 || $tick%60!==0)throw new InvalidArgumentException('Only whole-minute ignition can be encoded');
 return (intdiv($tick,3600)%100)*100+intdiv($tick%3600,60);
}
function rtccAngleDsky(?array $attitude,string $axis):?int {
 if(!$attitude||!isset($attitude[$axis.'_deg']))return null;
 $angle=fmod(fmod((float)$attitude[$axis.'_deg'],360.)+360.,360.);
 return (int)round($angle*10.)%3600;
}
function rtccDvDsky(float $dv):?int {
 // Four digits: tenths of one m/s, positive, 0.1 to 999.9 inclusive.
 if(!is_finite($dv)||$dv<.1||$dv>=1000.)return null;
 return (int)floor($dv*10.+1e-9);
}
/** Nominal rocket-equation fuel only; caller supplies independently estimated dry mass.
 * Does not claim to model attached LM, staging, thrust tails, engine failure or reserves.
 */
function rtccFuelBudget(float $dryMassKg,float $availableFuelKg,float $exhaustMps,array $deltas):array {
 if($dryMassKg<=0||$availableFuelKg<0||$exhaustMps<=0)throw new InvalidArgumentException('Invalid SPS estimate');
 $wet=$dryMassKg+$availableFuelKg;$result=[];
 foreach($deltas as $dv){
  if(!is_finite($dv)||$dv<0)throw new InvalidArgumentException('Invalid maneuver delta-V');
  $consumed=$wet*(1-exp(-$dv/$exhaustMps));
  $remaining=$availableFuelKg-$consumed;
  $result[]=['fuel_kg'=>$consumed,'remaining_kg'=>$remaining,'available'=>$remaining>=0.];
  $wet-=$consumed;$availableFuelKg=$remaining;
 }
 return $result;
}
