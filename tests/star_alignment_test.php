<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/star_alignment.php';
function testStar(bool $pass,string $reason):void {if(!$pass)throw new RuntimeException($reason);}
$zero=moonStarSolve([3446,5846,3470]);
testStar(abs($zero['pitch_deg'])<0.01,'zero pitch');
testStar(min(abs($zero['yaw_deg']),abs($zero['yaw_deg']-360))<0.01,'zero yaw');
testStar(min(abs($zero['roll_deg']),abs($zero['roll_deg']-360))<0.01,'zero roll');
testStar($zero['max_residual_deg']<.001,'zero residual');
try{moonStarSolve([3446,3446,3470]);throw new RuntimeException('bad star pair accepted');}catch(DomainException $expected){}
try{moonStarSolve([3446,5846,9999]);throw new RuntimeException('inconsistent sight accepted');}catch(DomainException $expected){}
// With a known 2.0 degree yaw, predict the reticle readings, quantize,
// and confirm the recovered platform attitude is within optical resolution.
$basis=moonStarBasis(0.,2.,0.),$codes=[];
foreach(array_slice(moonStarCatalog(),0,3) as $s){
 $v=$s['vector'];$f=moonStarDot($v,$basis[0]);
 $x=rad2deg(atan2(moonStarDot($v,$basis[1]),$f));
 $y=rad2deg(atan2(moonStarDot($v,$basis[2]),$f));
 $codes[]=(int)(round($x*2+50)*100+round($y*2+50));
}
$rotated=moonStarSolve($codes);
testStar(abs($rotated['yaw_deg']-2)<.6,'recover nonzero yaw from optical sightings');
echo "Three-star optical alignment tests passed\n";
