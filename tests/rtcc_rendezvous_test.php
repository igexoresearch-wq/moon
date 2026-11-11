<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/rtcc_rendezvous.php';
function checkRtcc(bool $value,string $why):void {
 if(!$value)throw new RuntimeException('RTCC test failed: '.$why);
}
$earth=['id'=>1,'name'=>'Earth','active'=>1,'dependent'=>0,'dependency'=>null,'land'=>null,'dry_mass'=>5.9723e24,'radius_length'=>6371000,
 'x'=>0.,'y'=>0.,'z'=>0.,'delta_x'=>0.,'delta_y'=>0.,'delta_z'=>0.];
$moon=['id'=>2,'name'=>'Moon','active'=>1,'dependent'=>0,'dependency'=>null,'land'=>null,'dry_mass'=>7.342e22,'radius_length'=>1737400,
 'x'=>384400000.,'y'=>0.,'z'=>0.,'delta_x'=>0.,'delta_y'=>1018.3,'delta_z'=>0.];
$r=7000000.;$v=sqrt(RTCC_G*$earth['dry_mass']/$r);
$cm=['id'=>3,'name'=>'CM','active'=>1,'dependent'=>0,'dependency'=>null,'land'=>null,'dry_mass'=>5560,'radius_length'=>4,
 'x'=>$r,'y'=>0.,'z'=>0.,'delta_x'=>0.,'delta_y'=>$v,'delta_z'=>0.];
$lm=$cm;$lm['id']=6;$lm['name']='LM';
$initial=[1=>$earth,2=>$moon,3=>$cm,6=>$lm];
$original=$initial;
$result=rtccRendezvousSolve($initial,['delays_s'=>[0],'flights_s'=>[1200]]);
checkRtcc($initial===$original,'solver must not mutate provided snapshot');
checkRtcc($result['status']==='CANDIDATES','identical orbital states must have a solution');
checkRtcc($result['solutions'][0]['injection_dv_mps']<.001,'identical states need no injection');
checkRtcc($result['solutions'][0]['matching_dv_mps']<.001,'identical states need no matching burn');
checkRtcc(abs($result['solutions'][0]['miss_m'])<50,'nominal miss threshold');
$offset=$initial;
$offset[6]['y']=2000.;
$offset[6]['delta_x']=-2.;
$result=rtccRendezvousSolve($offset,['delays_s'=>[0],'flights_s'=>[1200,2400]]);
checkRtcc($result['status']==='CANDIDATES','nearby vehicles must yield a candidate');
foreach($result['solutions'] as $s)checkRtcc($s['miss_m']<50&&$s['total_dv_mps']>=0,'candidate conditions');
$bad=$initial;$bad[6]['active']=0;
try{rtccRendezvousSolve($bad);throw new RuntimeException('inactive LM was accepted');}
catch(DomainException $expected){}
$bad=$initial;$bad[3]['x']=6000000.;
try{rtccRendezvousSolve($bad);throw new RuntimeException('subsurface CM was accepted');}
catch(DomainException $expected){}
echo "RTCC rendezvous self-tests passed\n";
