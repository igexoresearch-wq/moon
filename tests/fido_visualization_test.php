<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fido_projection.php';
require_once dirname(__DIR__).'/fido_plan_trace.php';
function fidoMapCheck(bool $ok,string $what):void {if(!$ok)throw new RuntimeException($what);}
$earth=[0.,0.,0.];$moon=[384400000.,0.,0.];
fidoMapCheck(fidoMoonOccults($earth,$moon,[390000000.,0.,0.]),'Far side blocks Earth signal');
fidoMapCheck(!fidoMoonOccults($earth,$moon,[300000000.,0.,0.]),'Earthward craft not blocked');
fidoMapCheck(!fidoMoonOccults($earth,$moon,[390000000.,5000000.,0.]),'Off-axis craft visible');
$state=function(int $id,array $p,array $v,float $mass,float $radius):array {
 return ['id'=>$id,'name'=>match($id){1=>'Earth',2=>'Moon',3=>'CSM',6=>'LM'},
  'dry_mass'=>$mass,'radius_length'=>$radius,'active'=>1,'dependent'=>0,'dependency'=>null,
  'x'=>$p[0],'y'=>$p[1],'z'=>$p[2],
  'delta_x'=>$v[0],'delta_y'=>$v[1],'delta_z'=>$v[2]];
};
$estimate=[
 1=>$state(1,[0.,0.,0.],[0.,0.,0.],5.9722e24,6371000.),
 2=>$state(2,[384400000.,0.,0.],[0.,1022.,0.],7.3477e22,1737400.),
 3=>$state(3,[7371000.,0.,0.],[0.,7350.,0.],5560.,3.9),
 6=>$state(6,[7411000.,0.,0.],[0.,7330.,0.],2150.,3.)
];
$csm=fidoMapCoast($estimate,3);$lm=fidoMapCoast($estimate,6);
fidoMapCheck($csm['bound'],'Near-earth CSM bound');
fidoMapCheck($csm['body']===1,'Earth as correct gravitational reference');
fidoMapCheck(count($csm['path'])>=20,'Full orbit path contains samples');
$svg=fidoMapSvg($estimate,[3=>$csm,6=>$lm],1);
fidoMapCheck(str_contains($svg,'stroke-dasharray="12 5"'),'CSM dashed trace');
fidoMapCheck(str_contains($svg,'stroke-dasharray="2 7"'),'LM dotted trace');
$plan=['start_seconds'=>60,'intercept_seconds'=>600,'dv1'=>[1.,0.,0.],'dv2'=>[-1.,0.,0.]];
$trace=fidoPlanTrace($estimate,$plan,1800);
fidoMapCheck(count($trace['events'])===2,'Two saved burn events');
fidoMapCheck(count($trace['cm'])>10,'Frozen predicted trajectory');
$navigation=file_get_contents(dirname(__DIR__).'/fido_navigation.php');
fidoMapCheck(str_contains($navigation,'if($occulted)'),'Ground fixes must be withheld during occultation');
fidoMapCheck(str_contains($navigation,'moonFidoTrackingObservation('),'Ground tracking available after LOS return');
foreach(['trajectory_map.php','proximity_map.php','flight_compare.php'] as $p)
 fidoMapCheck(is_file(dirname(__DIR__).'/fido_pages/'.$p),'FIDO visual screen '.$p);
echo "FIDO projection, lunar occultation, saved maneuver trace tests passed\n";
