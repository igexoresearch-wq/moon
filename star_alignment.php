<?php
declare(strict_types=1);
/**
 * Fixed inertial star catalog shared with viewport and sextant.
 * Coordinates are ideal directions, not nearby luminous bodies.
 * Three distinct labeled reference stars form an easy-to-learn corner
 * in the reference XY tangent plane about the simulation +X direction.
 */
function moonStarNormalize(array $v):array {
 $n=sqrt($v[0]**2+$v[1]**2+$v[2]**2);
 if($n<1e-12)throw new DomainException('Invalid star vector');
 return [$v[0]/$n,$v[1]/$n,$v[2]/$n];
}
function moonStarDirection(float $xDeg,float $yDeg):array {
 $x=tan(deg2rad($xDeg));$y=tan(deg2rad($yDeg));
 return moonStarNormalize([1.,$x,$y]);
}
function moonStarCatalog():array {
 $bright=[
  ['id'=>'A','name'=>'CORNER','x'=>-8.,'y'=>-2.,'mag'=>.1,'group'=>'TRIAD'],
  ['id'=>'B','name'=>'ARROW','x'=>4.,'y'=>-2.,'mag'=>.5,'group'=>'TRIAD'],
  ['id'=>'C','name'=>'CROWN','x'=>-8.,'y'=>10.,'mag'=>.8,'group'=>'TRIAD'],
  ['id'=>'D','name'=>'BEACON','x'=>13.,'y'=>14.,'mag'=>1.2,'group'=>'KEY'],
  ['id'=>'E','name'=>'ANCHOR','x'=>-16.,'y'=>-13.,'mag'=>1.0,'group'=>'KEY'],
  ['id'=>'F','name'=>'LANTERN','x'=>-14.,'y'=>5.,'mag'=>1.6,'group'=>'KEY'],
 ];
 $stars=[];
 foreach($bright as $s)$stars[]=['id'=>$s['id'],'name'=>$s['name'],'vector'=>moonStarDirection($s['x'],$s['y']),'magnitude'=>$s['mag'],'group'=>$s['group']];
 // Deterministic, faint synthetic training star field, persistent in inertial space.
 for($i=0;$i<240;$i++){
  $u=fmod(sin(($i+1)*127.1)*43758.5453,1.);if($u<0)$u+=1.;
  $v=fmod(sin(($i+1)*311.7)*25318.1742,1.);if($v<0)$v+=1.;
  $az=2*M_PI*$u;$z=2*$v-1.;$h=sqrt(max(0.,1-$z*$z));
  $stars[]=['id'=>'S'.($i+1),'name'=>'','vector'=>[$h*cos($az),$h*sin($az),$z],
   'magnitude'=>3.0+2.6*fmod(($i*0.61803398875),1.),'group'=>'FIELD'];
 }
 return $stars;
}
/** Sim camera basis, matching instruments/window3d.js. */
function moonStarBasis(float $pitch,float $yaw,float $roll):array {
 $p=deg2rad($pitch);$y=deg2rad($yaw);$r=deg2rad($roll);
 $f=[cos($p)*cos($y),cos($p)*sin($y),sin($p)];
 $r0=[-sin($y),cos($y),0.];
 $u0=[-sin($p)*cos($y),-sin($p)*sin($y),cos($p)];
 $right=[];$up=[];
 for($i=0;$i<3;$i++){
  $right[$i]=$r0[$i]*cos($r)+$u0[$i]*sin($r);
  $up[$i]=$u0[$i]*cos($r)-$r0[$i]*sin($r);
 }
 return [$f,$right,$up];
}
function moonStarDot(array $a,array $b):float{return $a[0]*$b[0]+$a[1]*$b[1]+$a[2]*$b[2];}
function moonStarMeasured(float $x,float $y):array{return moonStarNormalize([1.,tan(deg2rad($x)),tan(deg2rad($y))]);}
function moonStarAngle(array $a,array $b):float {
 return rad2deg(acos(max(-1.,min(1.,moonStarDot($a,$b)))));
}
/** Four decimal digits: XXYY, 50 = 0°, each count = 0.5°, range ±25°. */
function moonStarUnpack(int $code):array {
 if($code<0||$code>9999)throw new DomainException('Invalid packed sextant reading');
 $x=intdiv($code,100);$y=$code%100;
 return [($x-50)*.5,($y-50)*.5];
}
function moonStarWrap(float $x):float{return fmod(fmod($x,360.)+360.,360.);}
/**
 * Determine full body->inertial attitude from three optical sightings.
 * Uses the orthogonal TRIAD basis of the two longest-separated stars and
 * checks the third star, rather than secretly reading objects attitude.
 */
function moonStarCross(array $a,array $b):array {
 return [$a[1]*$b[2]-$a[2]*$b[1],$a[2]*$b[0]-$a[0]*$b[2],$a[0]*$b[1]-$a[1]*$b[0]];
}
function moonStarFrame(array $first,array $second):array {
 $e1=moonStarNormalize($first);$cross=moonStarCross($e1,$second);
 if(sqrt(moonStarDot($cross,$cross))<.035)throw new DomainException('Reference stars nearly collinear');
 $e3=moonStarNormalize($cross);$e2=moonStarCross($e3,$e1);
 return [$e1,$e2,$e3];
}
function moonStarSolve(array $codes):array {
 if(count($codes)!==3)throw new DomainException('Three star observations required');
 $catalog=moonStarCatalog();$inertial=[];foreach($catalog as $s)if(in_array($s['id'],['A','B','C'],true))$inertial[]=$s['vector'];
 $body=[];
 foreach($codes as $code){[$x,$y]=moonStarUnpack((int)$code);$body[]=moonStarMeasured($x,$y);}
 // Pairwise angular distances are invariant under rigid spacecraft rotation.
 for($i=0;$i<3;$i++)for($j=$i+1;$j<3;$j++){
  if(abs(moonStarAngle($body[$i],$body[$j])-moonStarAngle($inertial[$i],$inertial[$j]))>1.5)
   throw new DomainException('STAR GEOMETRY MISMATCH - RECHECK SIGHTINGS');
 }
 $b=moonStarFrame($body[0],$body[1]);$w=moonStarFrame($inertial[0],$inertial[1]);
 // Rotation matrix columns in inertial coordinates: R = W * B^T.
 $r=[[0.,0.,0.],[0.,0.,0.],[0.,0.,0.]];
 for($i=0;$i<3;$i++)for($j=0;$j<3;$j++)for($k=0;$k<3;$k++)$r[$i][$j]+=$w[$k][$i]*$b[$k][$j];
 $maxResidual=0.;
 for($s=0;$s<3;$s++){
  $pred=[];for($i=0;$i<3;$i++)$pred[$i]=moonStarDot($r[$i],$body[$s]);
  $maxResidual=max($maxResidual,moonStarAngle($pred,$inertial[$s]));
 }
 if($maxResidual>1.5)throw new DomainException('ALIGNMENT RESIDUAL TOO LARGE');
 $f=[$r[0][0],$r[1][0],$r[2][0]];
 $right=[$r[0][1],$r[1][1],$r[2][1]];
 $pitch=rad2deg(atan2($f[2],hypot($f[0],$f[1])));
 $yaw=rad2deg(atan2($f[1],$f[0]));
 $basis=moonStarBasis($pitch,$yaw,0.);
 $roll=rad2deg(atan2(moonStarDot($right,$basis[2]),moonStarDot($right,$basis[1])));
 return ['pitch_deg'=>moonStarWrap($pitch),'yaw_deg'=>moonStarWrap($yaw),
  'roll_deg'=>moonStarWrap($roll),'max_residual_deg'=>$maxResidual,
  'reference_stars'=>['A','B','C']];
}
