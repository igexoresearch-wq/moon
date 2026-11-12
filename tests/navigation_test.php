<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/navigation.php';
function near(float $actual,float $expected,float $tol,string $label):void {
 if(!is_finite($actual)||abs($actual-$expected)>$tol)throw new RuntimeException($label.': '.$actual.' expected '.$expected);
}
$earth=['x'=>0.,'y'=>0.,'z'=>0.,'mass_kg'=>5.9722e24];
$moon=['x'=>384400000.,'y'=>0.,'z'=>0.,'mass_kg'=>7.34767309e22];
$atSurface=moonNavigationGravity([6371000.,0.,0.],[$earth]);
near($atSurface[0],-9.82,0.1,'Earth surface gravity');
near($atSurface[1],0.,1e-12,'Earth Y gravity');
near($atSurface[2],0.,1e-12,'Earth Z gravity');
$inSpace=moonNavigationGravity([200000000.,0.,0.],[$earth,$moon]);
if($inSpace[0]>=0.)throw new RuntimeException('Expected Earth gravity dominance at 200 Mm');
$atMoon=moonNavigationGravity([384400000.+1737400.,0.,0.],[$moon]);
near($atMoon[0],-1.625,0.03,'Moon surface gravity');
echo "navigation gravity tests passed\n";
