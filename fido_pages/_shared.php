<?php
declare(strict_types=1);
function fidoNorm(array $v):float {return sqrt(array_sum(array_map(static fn($x)=>$x*$x,$v)));}
function fidoN(?float $n,int $digits=2):string {
 return $n===null||!is_finite($n)?'---':number_format($n,$digits,'.',',');
}
function fidoValue(float $n,int $digits=2):string{return fidoN($n,$digits);}
function fidoRows(array $rows):void {
 foreach($rows as $label=>$value){
  echo '<div class="fd-row"><span>'.htmlspecialchars((string)$label,ENT_QUOTES).'</span><b>'.htmlspecialchars((string)$value,ENT_QUOTES).'</b></div>';
 }
}
function fidoBody(array $craft,array $earth,array $moon):array {
 $body=$earth;
 $dist=function($o,$b):float{return sqrt(((float)$o['x']-(float)$b['x'])**2+((float)$o['y']-(float)$b['y'])**2+((float)$o['z']-(float)$b['z'])**2);};
 if($dist($craft,$moon)-(float)$moon['radius_length'] < $dist($craft,$earth)-(float)$earth['radius_length'])$body=$moon;
 return $body;
}
function fidoOrbit(array $craft,array $body):array {
 $r=[(float)$craft['x']-(float)$body['x'],(float)$craft['y']-(float)$body['y'],(float)$craft['z']-(float)$body['z']];
 $v=[(float)$craft['delta_x']-(float)$body['delta_x'],(float)$craft['delta_y']-(float)$body['delta_y'],(float)$craft['delta_z']-(float)$body['delta_z']];
 $radius=fidoNorm($r);$speed=fidoNorm($v);$mu=6.67430e-11*(float)$body['dry_mass'];
 if($radius<=0||$mu<=0)return ['alt'=>null,'speed'=>null,'apo'=>null,'peri'=>null,'period'=>null,'ecc'=>null,'inc'=>null,'radial'=>null,'status'=>'NO SOLUTION'];
 $h=[$r[1]*$v[2]-$r[2]*$v[1],$r[2]*$v[0]-$r[0]*$v[2],$r[0]*$v[1]-$r[1]*$v[0]];
 $hn=fidoNorm($h);$rv=($r[0]*$v[0]+$r[1]*$v[1]+$r[2]*$v[2]);
 $ev=[($v[1]*$h[2]-$v[2]*$h[1])/$mu-$r[0]/$radius,($v[2]*$h[0]-$v[0]*$h[2])/$mu-$r[1]/$radius,($v[0]*$h[1]-$v[1]*$h[0])/$mu-$r[2]/$radius];
 $ecc=fidoNorm($ev);$energy=$speed*$speed/2-$mu/$radius;
 $a=abs($energy)>1e-10?-$mu/(2*$energy):null;
 $peri=$a!==null?$a*(1-$ecc)-(float)$body['radius_length']:null;
 $apo=$a!==null&&$energy<0?$a*(1+$ecc)-(float)$body['radius_length']:null;
 $period=$a!==null&&$energy<0?2*M_PI*sqrt($a**3/$mu):null;
 return ['alt'=>($radius-(float)$body['radius_length'])/1000,'speed'=>$speed/1000,'apo'=>$apo===null?null:$apo/1000,'peri'=>$peri===null?null:$peri/1000,'period'=>$period===null?null:$period/60,'ecc'=>$ecc,'inc'=>$hn>0?rad2deg(acos(max(-1,min(1,$h[2]/$hn)))):null,'radial'=>$rv/$radius,'status'=>$energy<0?'BOUND / OSCULATING':'ESCAPE / UNBOUND'];
}
