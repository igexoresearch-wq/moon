<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fido_navigation.php';
function verifyClose(float $actual,float $expected,float $tolerance,string $label):void{
 if(!is_finite($actual)||abs($actual-$expected)>$tolerance)throw new RuntimeException($label.' actual '.$actual.' expected '.$expected);
}
$earth=['x'=>0.,'y'=>0.,'z'=>0.,'mass_kg'=>5.9722e24];
$g=moonFidoGravity([6371000.,0.,0.],[$earth]);
verifyClose($g[0],-9.82,0.1,'Earth gravity x');
verifyClose($g[1],0.,1e-15,'Earth gravity y');
[$p,$v]=moonFidoPropagate([6371000.,0.,0.],[0.,0.,0.],[$earth],1.);
verifyClose($p[0],6371000.+$g[0]/2,0.0001,'One second position');
verifyClose($v[0],$g[0],0.0001,'One second velocity');
[$p,$v]=moonFidoPropagate([10.,20.,30.],[1.,2.,3.],[],1.);
verifyClose($p[0],11.,1e-12,'Zero gravity coast position');
verifyClose($v[2],3.,1e-12,'Zero gravity coast velocity');
echo "FIDO ground navigation math passed\n";
