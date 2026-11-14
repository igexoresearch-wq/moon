<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/terminal_rendezvous_tracker.php';
require_once dirname(__DIR__).'/terminal_rendezvous_control.php';
function checkTerminal(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
function approxTerminal(float $a,float $b,float $epsilon=.0001):bool{return abs($a-$b)<$epsilon;}
$basis=moonTerminalBasis(0.,90.,0.);
$r=moonTerminalRadarVector(200.,0.,0.,$basis);
checkTerminal(approxTerminal($r[0],0.)&&approxTerminal($r[1],200.),'Forward radar range converts through IMU yaw');
$rotated=moonTerminalRadarVector(100.,90.,0.,moonTerminalBasis(0.,0.,0.));
checkTerminal(approxTerminal($rotated[1],100.),'Azimuth reconstructs lateral displacement');
$prior=['sample_tick'=>100,'sample_count'=>4,'rx'=>200.,'ry'=>0.,'rz'=>0.,
 'vx'=>-1.,'vy'=>0.,'vz'=>0.];
$estimate=moonTerminalFilter($prior,[199.,0.,0.],1.,101);
checkTerminal(approxTerminal($estimate['v'][0],-1.),'Range rate constrains radial velocity');
checkTerminal($estimate['status']==='TRACKING','Active filter reports tracking');
$demand=moonTerminalDemand([200.,0.,0.],[-2.,0.,0.],50.);
checkTerminal($demand[0]<0.,'Closing-too-fast demands opposite RCS');
$demand=moonTerminalDemand([200.,0.,0.],[2.,0.,0.],50.);
checkTerminal($demand[0]>0.,'Opening-too-fast demands inward RCS');
$controller=file_get_contents(dirname(__DIR__).'/terminal_rendezvous_control.php');
$tracker=file_get_contents(dirname(__DIR__).'/terminal_rendezvous_tracker.php');
$dsky=file_get_contents(dirname(__DIR__).'/dsky_runtime.php');
$worker=file_get_contents(dirname(__DIR__).'/execute.php');
checkTerminal(!str_contains($tracker,'FROM objects'),'Onboard tracker never reads simulator truth');
checkTerminal(str_contains($tracker,'FROM navigation_states'),'Ownship estimated navigation read');
checkTerminal(str_contains($tracker,'FROM computer_registers'),'Radar observations read');
checkTerminal(str_contains($dsky,"$"."kind==='terminal_match'"),'DSKY dispatches P36');
checkTerminal(str_contains($worker,'moonTerminalTrackingTick('),'Radar tracker runs once each physics tick');
checkTerminal(str_contains($controller,'UPDATE objects SET delta_x'),'Only RCS actuators change physical velocity');
echo "P36 radar geometry, filtering, control direction and isolation passed\n";
