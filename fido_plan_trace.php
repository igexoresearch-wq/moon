<?php
declare(strict_types=1);
require_once __DIR__.'/rtcc_rendezvous.php';
/** Replays an immutable RTCC option using the same idealized impulsive
 *  assumptions as the plan calculation. Each point is FIDO PREDICTED,
 *  never real craft truth or DSKY execution telemetry.
 */
function fidoPlanTrace(array $snapshot,array $plan,int $planningTick):array {
 foreach([1,2,3,6] as $id)if(!isset($snapshot[$id]))throw new DomainException('Incomplete FIDO plan snapshot');
 foreach(['dv1','dv2','start_seconds','intercept_seconds'] as $key)if(!isset($plan[$key]))
  throw new DomainException('Incomplete RTCC maneuver');
 $em=(float)$snapshot[1]['dry_mass'];$mm=(float)$snapshot[2]['dry_mass'];
 $moon=rtccState($snapshot[2]);$cm=rtccState($snapshot[3]);$lm=rtccState($snapshot[6]);
 $start=(int)$plan['start_seconds'];$end=(int)$plan['intercept_seconds'];
 if($start<0||$end<=$start||$end>21600||$start%10||$end%10)
  throw new DomainException('Invalid RTCC maneuver timeline');
 $horizon=min(21600,$end+1800);
 $cmPath=[];$lmPath=[];$events=[];
 for($t=0;$t<=$horizon;$t+=10){
  if($t===$start){$cm['v']=rtccVec($cm['v'],$plan['dv1']);$events[]=['t'=>$planningTick+$t,'name'=>'FIRST IDEAL BURN'];}
  if($t===$end){$cm['v']=rtccVec($cm['v'],$plan['dv2']);$events[]=['t'=>$planningTick+$t,'name'=>'SECOND IDEAL BURN'];}
  if($t%60===0||$t===$start||$t===$end||$t===$horizon){
   $cmPath[]=['t'=>$planningTick+$t,'p'=>$cm['p'],'moon'=>$moon['p']];
   $lmPath[]=['t'=>$planningTick+$t,'p'=>$lm['p'],'moon'=>$moon['p']];
  }
  if($t===$horizon)break;
  $moonNext=rtccStep($moon,$moon['p'],$moon['p'],$em,$mm,10.,true);
  $cm=rtccStep($cm,$moon['p'],$moonNext['p'],$em,$mm,10.);
  $lm=rtccStep($lm,$moon['p'],$moonNext['p'],$em,$mm,10.);
  $moon=$moonNext;
 }
 return ['cm'=>$cmPath,'lm'=>$lmPath,'events'=>$events,
  'horizon_tick'=>$planningTick+$horizon,'origin_tick'=>$planningTick];
}
