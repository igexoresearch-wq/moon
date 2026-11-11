<?php
declare(strict_types=1);
/**
 * Apollo-inspired FIDO / RTCC numerical rendezvous targeting.
 *
 * Read-only computational model: Earth fixed at origin, Moon propagated under
 * Earth's gravity; CM and LM coast under Earth+Moon gravity using velocity-Verlet.
 * The caller supplies a single snapshot of real simulated object states.
 * Maneuvers are ideal instantaneous velocity impulses; neither burn is executed.
 * No SQL writes, ignition commands, AI guesses, or cockpit controls.
 *
 * Units: meters, seconds, kg, m/s, radians internally; degrees in output.
 */
const RTCC_G=6.67430e-11;
const RTCC_STEP_SECONDS=10;
function rtccVec(array $a,array $b):array {return [$a[0]+$b[0],$a[1]+$b[1],$a[2]+$b[2]];}
function rtccSub(array $a,array $b):array {return [$a[0]-$b[0],$a[1]-$b[1],$a[2]-$b[2]];}
function rtccScale(array $a,float $s):array {return [$a[0]*$s,$a[1]*$s,$a[2]*$s];}
function rtccDot(array $a,array $b):float {return $a[0]*$b[0]+$a[1]*$b[1]+$a[2]*$b[2];}
function rtccNorm(array $a):float {return sqrt(rtccDot($a,$a));}
function rtccState(array $o):array {
 return [
  'p'=>[(float)$o['x'],(float)$o['y'],(float)$o['z']],
  'v'=>[(float)$o['delta_x'],(float)$o['delta_y'],(float)$o['delta_z']]
 ];
}
function rtccGravity(array $p,array $moon,float $earthMass,float $moonMass,bool $isMoon=false):array {
 $out=[0.,0.,0.];
 $centers=[[[0.,0.,0.],$earthMass]];
 if(!$isMoon)$centers[]=[$moon,$moonMass];
 foreach($centers as [$center,$mass]){
  $r=rtccSub($center,$p);$d2=rtccDot($r,$r);
  if($d2<1.)throw new DomainException('Trajectory singularity inside a gravity source');
  $factor=RTCC_G*$mass/($d2*sqrt($d2));
  $out=rtccVec($out,rtccScale($r,$factor));
 }
 return $out;
}
function rtccStep(array $s,array $moonBefore,array $moonAfter,float $earthMass,float $moonMass,float $dt,bool $isMoon=false):array {
 $a0=rtccGravity($s['p'],$moonBefore,$earthMass,$moonMass,$isMoon);
 $p=rtccVec(rtccVec($s['p'],rtccScale($s['v'],$dt)),rtccScale($a0,.5*$dt*$dt));
 $a1=rtccGravity($p,$moonAfter,$earthMass,$moonMass,$isMoon);
 return ['p'=>$p,'v'=>rtccVec($s['v'],rtccScale(rtccVec($a0,$a1),.5*$dt))];
}
function rtccOutsideBodies(array $p,array $moon,array $earthRadius,float $moonRadius,float $craftRadius=0.):bool {
 return rtccNorm($p)>$earthRadius+$craftRadius && rtccNorm(rtccSub($p,$moon))>$moonRadius+$craftRadius;
}
/**
 * Deterministic ephemeris for the complete search horizon; never writes to DB.
 * Index N corresponds to N*10 seconds from the snapshot epoch.
 */
function rtccEphemeris(array $objects,int $maxSeconds):array {
 foreach([1,2,3,6] as $id)if(!isset($objects[$id]))throw new DomainException('Missing Earth, Moon, CM, or LM');
 foreach([3,6] as $id){
  if((int)$objects[$id]['active']!==1)throw new DomainException('Both CM and LM must be active');
  if(($objects[$id]['land']??null)!==null)throw new DomainException('Cannot target rendezvous from a landed vehicle');
  if((int)($objects[$id]['dependent']??0)===1)throw new DomainException('Detached/free-flight CM and LM required');
 }
 $earth=$objects[1];$moon=$objects[2];
 $em=(float)$earth['dry_mass'];$mm=(float)$moon['dry_mass'];
 if($em<=0||$mm<=0)throw new DomainException('Invalid gravitational masses');
 $radii=[(float)$earth['radius_length'],(float)$moon['radius_length']];
 $moonNow=rtccState($moon);$cm=rtccState($objects[3]);$lm=rtccState($objects[6]);
 $moonPath=[$moonNow['p']];$cmPath=[$cm];$lmPath=[$lm];
 $n=intdiv($maxSeconds,RTCC_STEP_SECONDS);
 for($i=0;$i<$n;$i++){
  $nextMoon=rtccStep($moonNow,$moonNow['p'],$moonNow['p'],$em,$mm,RTCC_STEP_SECONDS,true);
  $cm=rtccStep($cm,$moonNow['p'],$nextMoon['p'],$em,$mm,RTCC_STEP_SECONDS);
  $lm=rtccStep($lm,$moonNow['p'],$nextMoon['p'],$em,$mm,RTCC_STEP_SECONDS);
  $moonNow=$nextMoon;
  $moonPath[]=$moonNow['p'];$cmPath[]=$cm;$lmPath[]=$lm;
  if(!rtccOutsideBodies($cm['p'],$moonNow['p'],$radii[0],$radii[1],(float)$objects[3]['radius_length'])
     ||!rtccOutsideBodies($lm['p'],$moonNow['p'],$radii[0],$radii[1],(float)$objects[6]['radius_length']))
   throw new DomainException('Coasting reference trajectory contacts Earth or Moon within the search horizon');
 }
 return ['moon'=>$moonPath,'cm'=>$cmPath,'lm'=>$lmPath,'em'=>$em,'mm'=>$mm,'radii'=>$radii];
}
/** Returns an actual post-injection terminal state, or null for surface impact. */
function rtccFly(array $e,int $startIndex,int $endIndex,array $dv,float $radius):?array {
 $s=$e['cm'][$startIndex];
 $s['v']=rtccVec($s['v'],$dv);
 for($i=$startIndex;$i<$endIndex;$i++){
  $s=rtccStep($s,$e['moon'][$i],$e['moon'][$i+1],$e['em'],$e['mm'],RTCC_STEP_SECONDS);
  if(!rtccOutsideBodies($s['p'],$e['moon'][$i+1],$e['radii'][0],$e['radii'][1],$radius))return null;
 }
 return $s;
}
/** Solve J*delta = residual, with partial-pivot Gaussian elimination. */
function rtccLinear3(array $j,array $b):?array {
 $a=[];
 for($i=0;$i<3;$i++)$a[$i]=[$j[$i][0],$j[$i][1],$j[$i][2],$b[$i]];
 for($i=0;$i<3;$i++){
  $pivot=$i;
  for($k=$i+1;$k<3;$k++)if(abs($a[$k][$i])>abs($a[$pivot][$i]))$pivot=$k;
  if(abs($a[$pivot][$i])<1e-10)return null;
  [$a[$i],$a[$pivot]]=[$a[$pivot],$a[$i]];
  $d=$a[$i][$i];for($c=$i;$c<4;$c++)$a[$i][$c]/=$d;
  for($k=0;$k<3;$k++)if($k!==$i){
   $factor=$a[$k][$i];
   for($c=$i;$c<4;$c++)$a[$k][$c]-=$factor*$a[$i][$c];
  }
 }
 return [$a[0][3],$a[1][3],$a[2][3]];
}
function rtccAim(array $e,int $start,int $end,float $craftRadius,float $maxInjectionMps):?array {
 $target=$e['lm'][$end];
 $flightSeconds=($end-$start)*RTCC_STEP_SECONDS;
 $unburned=$e['cm'][$end]['p'];
 $dv=rtccScale(rtccSub($target['p'],$unburned),1/$flightSeconds);
 if(rtccNorm($dv)>$maxInjectionMps*2.)return null;
 $best=null;
 for($iter=0;$iter<9;$iter++){
  $s=rtccFly($e,$start,$end,$dv,$craftRadius);
  if($s===null)return null;
  $residual=rtccSub($target['p'],$s['p']);$miss=rtccNorm($residual);
  if($miss<50.){
   $arrival=rtccSub($target['v'],$s['v']);
   return ['dv1'=>$dv,'dv2'=>$arrival,'miss_m'=>$miss,'start_seconds'=>$start*RTCC_STEP_SECONDS,
    'intercept_seconds'=>$end*RTCC_STEP_SECONDS,
    'transfer_seconds'=>$flightSeconds,
    'total_dv_mps'=>rtccNorm($dv)+rtccNorm($arrival),
    'predicted_postburn_relative_speed_mps'=>0.];
  }
  $jac=[[0.,0.,0.],[0.,0.,0.],[0.,0.,0.]];
  for($k=0;$k<3;$k++){
   $trial=$dv;$trial[$k]+=.1;
   $shifted=rtccFly($e,$start,$end,$trial,$craftRadius);
   if($shifted===null)return null;
   for($row=0;$row<3;$row++)$jac[$row][$k]=($shifted['p'][$row]-$s['p'][$row])/.1;
  }
  $correction=rtccLinear3($jac,$residual);
  if($correction===null)return null;
  $step=rtccNorm($correction);
  if($step>$maxInjectionMps)$correction=rtccScale($correction,$maxInjectionMps/$step);
  $accepted=false;
  for($factor=1.;$factor>=.0625;$factor*=.5){
   $trial=rtccVec($dv,rtccScale($correction,$factor));
   if(rtccNorm($trial)>$maxInjectionMps*1.2)continue;
   $check=rtccFly($e,$start,$end,$trial,$craftRadius);
   if($check!==null && rtccNorm(rtccSub($target['p'],$check['p']))<$miss){
    $dv=$trial;$accepted=true;break;
   }
  }
  if(!$accepted)return null;
 }
 return $best;
}
function rtccAttitude(array $dv):?array {
 $m=rtccNorm($dv);if($m<1e-9)return null;
 return ['pitch_deg'=>rad2deg(atan2($dv[2],hypot($dv[0],$dv[1]))),
         'yaw_deg'=>fmod(rad2deg(atan2($dv[1],$dv[0]))+360.,360.),
         'roll_deg'=>null];
}
/**
 * Generates a finite list of numerically checked, idealized two-impulse plans.
 * Parameters are deliberately bounded for a predictable interactive workload.
 * A solution is advisory; real burns require independently verified execution.
 */
function rtccRendezvousSolve(array $objects,array $options=[]):array {
 $delays=$options['delays_s']??[0,600,1800,3600];
 $flights=$options['flights_s']??[1200,2400,3600,5400,7200];
 $maxDv=(float)($options['max_injection_mps']??1200.);
 if(!is_array($delays)||!is_array($flights)||count($delays)>8||count($flights)>12
    ||count($delays)<1||count($flights)<1||$maxDv<=0||$maxDv>5000)
  throw new InvalidArgumentException('Invalid rendezvous search settings');
 foreach(array_merge($delays,$flights) as $t)
  if(!is_int($t)||$t<0||$t>21600||$t%RTCC_STEP_SECONDS!==0)
   throw new InvalidArgumentException('Time settings must be nonnegative whole ten-second steps');
 if(min($flights)<60)throw new InvalidArgumentException('Minimum transfer time is 60 seconds');
 $horizon=max($delays)+max($flights);
 if($horizon>21600)throw new InvalidArgumentException('Search horizon exceeds six hours');
 $e=rtccEphemeris($objects,$horizon);
 $separation=rtccSub($e['lm'][0]['p'],$e['cm'][0]['p']);
 $relative=rtccSub($e['lm'][0]['v'],$e['cm'][0]['v']);
 $range=rtccNorm($separation);$closing=$range>0?-rtccDot($separation,$relative)/$range:null;
 $solutions=[];
 foreach($delays as $delay)foreach($flights as $flight){
  $start=intdiv($delay,RTCC_STEP_SECONDS);$end=intdiv($delay+$flight,RTCC_STEP_SECONDS);
  $plan=rtccAim($e,$start,$end,(float)$objects[3]['radius_length'],$maxDv);
  if($plan===null||rtccNorm($plan['dv1'])>$maxDv)continue;
  // A valid zero-miss intercept still may require an unreasonably large
  // velocity-matching maneuver. Reject outside the configured envelope.
  if(rtccNorm($plan['dv2'])>$maxDv)continue;
  $plan['injection_dv_mps']=rtccNorm($plan['dv1']);
  $plan['matching_dv_mps']=rtccNorm($plan['dv2']);
  $plan['injection_attitude']=rtccAttitude($plan['dv1']);
  $plan['matching_attitude']=rtccAttitude($plan['dv2']);
  $solutions[]=$plan;
 }
 usort($solutions,static fn(array $a,array $b)=>($a['total_dv_mps']<=>$b['total_dv_mps'])
  ?:($a['intercept_seconds']<=>$b['intercept_seconds']));
 return [
  'status'=>$solutions?'CANDIDATES':'NO_VALID_SOLUTION',
  'range_m'=>$range,'closing_rate_mps'=>$closing,'relative_speed_mps'=>rtccNorm($relative),
  'method'=>'Numerical two-impulse shooting, velocity-Verlet, Earth + moving Moon gravity',
  'step_seconds'=>RTCC_STEP_SECONDS,'reference'=>'Instantaneous current simulator state',
  'assumptions'=>['Instantaneous engine impulses; no ignition delay, staging or finite-burn gravity loss',
    'No thrust or other active burns during candidate coasts','CM targets LM; LM coasts',
    'No docking or arrival keep-out corridor; intercept is mathematical coincidence',
    'Earth is fixed; Moon follows the simulator gravity model',
    'Flight attitude angles are inertial ideal directions, NOT IMU-referenced commands',
    'No engine fuel/power or guidance feasibility guarantee'],
  'solutions'=>array_slice($solutions,0,5)
 ];
}
