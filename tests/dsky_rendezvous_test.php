<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/dsky_rendezvous.php';
require_once dirname(__DIR__).'/rtcc_maneuver.php';
function checkManeuver(bool $condition,string $label):void{
 if(!$condition)throw new RuntimeException($label);
}
// 120:00 mission hours, HHMM omits the hundred-hour digit.
checkManeuver(moonDvMissionTick(120*3600,2012)===120*3600+12*60,'120h12m mission rollup');
checkManeuver(moonDvMissionTick(120*3600+12*60,2012)===220*3600+12*60,'Always choose a future occurrence');
checkManeuver(moonDvMissionTick(99*3600+59*60,0)===100*3600,'MET hundred-hour wrap');
try{moonDvMissionTick(0,1260);throw new RuntimeException('Invalid minute accepted');}catch(DomainException $expected){}
checkManeuver(rtccMetHhmm(120*3600+12*60)===2012,'RTCC four-digit HHMM wrap');
checkManeuver(rtccMetLabel(120*3600+12*60)==='120:12:00','Full mission MET label');
checkManeuver(rtccDvDsky(199.88)===1998,'Positive delta-V truncated to 0.1 m/s');
checkManeuver(rtccDvDsky(1000.)===null,'Reject out-of-range delta-V');
checkManeuver(rtccAngleDsky(['pitch_deg'=>-12.5],'pitch')===3475,'Signed pitch wraps to 0-359.9');
checkManeuver(rtccAngleDsky(['yaw_deg'=>360.],'yaw')===0,'Yaw wraps at 360');
$budget=rtccFuelBudget(11660.,18500.,314*9.80665,[25.,30.]);
checkManeuver(count($budget)===2 && $budget[0]['fuel_kg']>0 && $budget[1]['fuel_kg']>0,'Both burns use propellant');
checkManeuver($budget[1]['remaining_kg']<$budget[0]['remaining_kg'],'Sequential fuel decreases');
echo "DSKY P32 mission time, encoding and SPS budget tests passed\n";
