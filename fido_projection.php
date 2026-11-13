<?php
declare(strict_types=1);
require_once __DIR__.'/rtcc_rendezvous.php';
require_once __DIR__.'/fido_los.php';
/**
 * FIDO-only coasting projection. No queries, DB writes or real object states.
 * Uses the same Earth+moving-Moon velocity-Verlet propagation as RTCC.
 */
function fidoMapCoast(array $estimates,int $craft,int $maxSamples=700):array {
 foreach([1,2,$craft] as $id)if(!isset($estimates[$id]))throw new DomainException('Missing ground estimate '.$id);
 $earth=$estimates[1];$moon=$estimates[2];$vehicle=$estimates[$craft];
 $bodyId=fidoMapReferenceBody($vehicle,$earth,$moon);
 $body=$estimates[$bodyId];
 $orbit=fidoMapElements($vehicle,$body);
 $isBound=$orbit['bound']&&$orbit['period_s']!==null&&$orbit['period_s']<=2e6;
 $horizon=$isBound?(float)$orbit['period_s']:14.*86400.;
 $em=(float)$earth['dry_mass'];$mm=(float)$moon['dry_mass'];
 $moonState=rtccState($moon);$state=rtccState($vehicle);
 $path=[['t'=>0.,'p'=>$state['p'],'moon'=>$moonState['p']]];
 $elapsed=0.;$stopped='HORIZON / MODEL LIMIT';$lunarNear=false;
 for($i=0;$i<$maxSamples && $elapsed<$horizon;$i++){
  $distanceMoon=rtccNorm(rtccSub($state['p'],$moonState['p']));
  if($distanceMoon<75000000.)$lunarNear=true;
  $speed=max(1.,rtccNorm($state['v']));
  $localDistance=min(rtccNorm($state['p']),(float)$distanceMoon);
  $dt=max(10.,min(1200.,$horizon/400.,$localDistance/max($speed,1.)*.06,$horizon-$elapsed));
  $moonNext=rtccStep($moonState,$moonState['p'],$moonState['p'],$em,$mm,$dt,true);
  $next=rtccStep($state,$moonState['p'],$moonNext['p'],$em,$mm,$dt);
  $elapsed+=$dt;$state=$next;$moonState=$moonNext;
  $path[]=['t'=>$elapsed,'p'=>$state['p'],'moon'=>$moonState['p']];
  if(rtccNorm($state['p'])<=(float)$earth['radius_length']){
   $stopped='EARTH INTERSECTION';break;
  }
  if(rtccNorm(rtccSub($state['p'],$moonState['p']))<=(float)$moon['radius_length']){
   $stopped='MOON INTERSECTION';break;
  }
  if(!$isBound&&$lunarNear){
   $r=rtccSub($state['p'],$moonState['p']);
   $outward=rtccDot($r,rtccScale($moonState['p'],1./max(1.,rtccNorm($moonState['p']))));
   if($outward>(float)$moon['radius_length']+1000000.){
    $stopped='LUNAR FAR-SIDE +1000 KM';break;
   }
  }
  if($isBound&&$elapsed>=$horizon-1.){$stopped='ONE OSCULATING ORBIT';break;}
 }
 if(count($path)>=$maxSamples)$stopped='SAMPLE LIMIT / PARTIAL TRAJECTORY';
 return ['craft'=>$craft,'body'=>$bodyId,'bound'=>$isBound,'period_s'=>$orbit['period_s'],
  'termination'=>$stopped,'path'=>$path,'horizon_s'=>$elapsed];
}
function fidoMapReferenceBody(array $craft,array $earth,array $moon):int {
 $earthR=rtccNorm(rtccSub(rtccState($craft)['p'],rtccState($earth)['p']));
 $moonR=rtccNorm(rtccSub(rtccState($craft)['p'],rtccState($moon)['p']));
 // Compare gravitational dominance (sphere of influence, not altitude).
 // Moon SOI: ~66,000 km around the Moon, corresponding to Earth/Moon
 // gravity ratio at orbital distance.
 $earthMoon=rtccNorm(rtccSub(rtccState($moon)['p'],rtccState($earth)['p']));
 $soi=$earthMoon*pow((float)$moon['dry_mass']/(float)$earth['dry_mass'],2./5.);
 return $moonR<$soi?2:1;
}
function fidoMapElements(array $craft,array $body):array {
 $p=rtccSub(rtccState($craft)['p'],rtccState($body)['p']);
 $v=rtccSub(rtccState($craft)['v'],rtccState($body)['v']);
 $r=rtccNorm($p);$mu=RTCC_G*(float)$body['dry_mass'];
 $energy=.5*rtccDot($v,$v)-$mu/max(1.,$r);
 $axis=$energy<0?-$mu/(2.*$energy):null;
 $bound=$energy<0&&$axis!==null&&is_finite($axis);
 return ['bound'=>$bound,'period_s'=>$bound?2.*M_PI*sqrt($axis**3/$mu):null];
}
/** Simplify into projected SVG paths without exposing inertial XYZ as UI. */
function fidoMapSvg(array $estimates,array $paths,int $centerBody=1,int $width=1240,int $height=570):string {
 $center=$estimates[$centerBody];
 $c=rtccState($center)['p'];
 $earth=rtccState($estimates[1])['p'];$moon=rtccState($estimates[2])['p'];
 $xAxis=rtccSub($moon,$earth);
 $len=rtccNorm($xAxis);
 if($len<1.)$xAxis=[1.,0.,0.];else $xAxis=rtccScale($xAxis,1./$len);
 // Plane includes the current CSM position when possible. Pick a stable
 // perpendicular when position is collinear with the Earth-Moon axis.
 $v=rtccSub(rtccState($estimates[3])['p'],$c);
 $projection=rtccScale($xAxis,rtccDot($v,$xAxis));
 $yAxis=rtccSub($v,$projection);$yn=rtccNorm($yAxis);
 if($yn<1.){$base=abs($xAxis[2])<.8?[0.,0.,1.]:[0.,1.,0.];$yAxis=rtccSub($base,rtccScale($xAxis,rtccDot($base,$xAxis)));$yn=rtccNorm($yAxis);}
 $yAxis=rtccScale($yAxis,1./max(1e-9,$yn));
 $project=static fn(array $p):array=>[rtccDot(rtccSub($p,$c),$xAxis),rtccDot(rtccSub($p,$c),$yAxis)];
 $draw=[];
 foreach($paths as $id=>$trajectory){
  foreach($trajectory['path'] as $row){
   $p=$row['p'];
   if($centerBody===2)$p=rtccSub($p,rtccSub($row['moon'],$moon)); // Moon-centric moving frame
   $draw[$id][]=$project($p);
  }
 }
 $points=array_merge(...array_values($draw));
 foreach([1,2] as $id)if($id===$centerBody)$points[]=$project(rtccState($estimates[$id])['p']);
 $minX=min(array_column($points,0));$maxX=max(array_column($points,0));
 $minY=min(array_column($points,1));$maxY=max(array_column($points,1));
 $spanX=max(1.,$maxX-$minX);$spanY=max(1.,$maxY-$minY);
 $radius=(float)$center['radius_length'];$spanX=max($spanX,$radius*2.25);$spanY=max($spanY,$radius*2.25);
 $centerX=($minX+$maxX)/2.;$centerY=($minY+$maxY)/2.;
 $scale=min(($width-110)/$spanX,($height-110)/$spanY);
 $map=static fn(array $q):array=>[$width/2+($q[0]-$centerX)*$scale,$height/2-($q[1]-$centerY)*$scale];
 $esc=static fn($v):string=>htmlspecialchars((string)$v,ENT_QUOTES);
 $svg='<svg viewBox="0 0 '.$width.' '.$height.'" width="100%" role="img" aria-label="Estimated FIDO trajectory projections" style="background:#0b1520;border:1px solid #36505b">';
 $bodyCenter=$map($project(rtccState($center)['p']));
 $rPx=max(1.,$radius*$scale);
 $svg.='<circle cx="'.round($bodyCenter[0],2).'" cy="'.round($bodyCenter[1],2).'" r="'.round($rPx,2).'" fill="#274f73" stroke="#71abc7" stroke-width="1.5"/>';
 $svg.='<text x="'.round($bodyCenter[0]+$rPx+9,1).'" y="'.round($bodyCenter[1],1).'" fill="#bbd8e5" font-family="monospace" font-size="14">'.$esc($center['name']).'</text>';
 foreach($draw as $id=>$list){
  $pts=[];foreach($list as $q){[$x,$y]=$map($q);$pts[]=round($x,2).','.round($y,2);}
  if(count($pts)<2)continue;
  $color=$id===3?'#eac88b':'#84d9cc';$dash=$id===3?'12 5':'2 7';
  $svg.='<polyline points="'.$esc(implode(' ',$pts)).'" fill="none" stroke="'.$color.'" stroke-width="1.5" stroke-dasharray="'.$dash.'"/>';
  [$x,$y]=$map($list[0]);
  $svg.='<circle cx="'.round($x,2).'" cy="'.round($y,2).'" r="4" fill="'.$color.'"/>';
  $svg.='<text x="'.round($x+10,2).'" y="'.round($y-6,2).'" fill="'.$color.'" font-size="12" font-family="monospace">'.($id===3?'CSM':'LM').'</text>';
 }
 $kmPer100px=100./$scale/1000.;
 $svg.='<text x="18" y="'.($height-16).'" fill="#b2c8d3" font-size="13" font-family="monospace">100 px = '.number_format($kmPer100px,1).' km / 2D ESTIMATE PROJECTION</text></svg>';
 return $svg;
}
