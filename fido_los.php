<?php
declare(strict_types=1);
/**
 * Earth-to-spacecraft straight-line lunar occultation test. Locations are
 * supplied by independent navigation estimates, not live objects.
 * Returns true if the Moon blocks the line segment. Units: meters.
 */
function fidoMoonOccults(array $earth,array $moon,array $craft,float $moonRadiusM=1737400.):bool {
 if($moonRadiusM<=0||!is_finite($moonRadiusM))throw new InvalidArgumentException('Invalid lunar radius');
 $d=[$craft[0]-$earth[0],$craft[1]-$earth[1],$craft[2]-$earth[2]];
 $l2=$d[0]*$d[0]+$d[1]*$d[1]+$d[2]*$d[2];
 if($l2<1.)return false;
 $u=[$moon[0]-$earth[0],$moon[1]-$earth[1],$moon[2]-$earth[2]];
 $t=($u[0]*$d[0]+$u[1]*$d[1]+$u[2]*$d[2])/$l2;
 // Exclude beyond spacecraft and beyond Earth.
 if($t<=0.||$t>=1.)return false;
 $closest=[$earth[0]+$t*$d[0],$earth[1]+$t*$d[1],$earth[2]+$t*$d[2]];
 $dsq=($closest[0]-$moon[0])**2+($closest[1]-$moon[1])**2+($closest[2]-$moon[2])**2;
 return $dsq<$moonRadiusM*$moonRadiusM;
}
function fidoEstimatedLunarOccultation(array $craft,array $earth,array $moon):bool {
 $pos=static fn(array $o):array=>[(float)$o['x'],(float)$o['y'],(float)$o['z']];
 return fidoMoonOccults($pos($earth),$pos($moon),$pos($craft),(float)$moon['radius_length']);
}
