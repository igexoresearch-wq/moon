<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/docking_sensor.php';
function checkDock(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
function dockObject(int $id,array $p,array $v,float $yaw,float $radius):array {
 return ['id'=>$id,'x'=>$p[0],'y'=>$p[1],'z'=>$p[2],
  'delta_x'=>$v[0],'delta_y'=>$v[1],'delta_z'=>$v[2],
  'pitch'=>0.,'yaw'=>$yaw,'radius_length'=>$radius];
}
$cm=dockObject(3,[0.,0.,0.],[0.,0.,0.],0.,3.9);
$lm=dockObject(6,[7.4,0.,0.],[-.5,0.,0.],180.,3.);
$aligned=moonDockGeometry($cm,$lm);
checkDock($aligned['dockable'],'Slow forward-facing approach within 1m should dock');
checkDock(abs($aligned['relative_speed_mps']-.5)<1e-8,'Real relative speed used for docking');
$side=dockObject(6,[7.4,0.,0.],[-.5,0.,0.],90.,3.);
checkDock(!moonDockGeometry($cm,$side)['dockable'],'Sideways LM must not dock');
$misaligned=dockObject(6,[0.,7.4,0.],[0.,-.5,0.],270.,3.);
checkDock(!moonDockGeometry($cm,$misaligned)['dockable'],'Side approach must not dock');
$fast=dockObject(6,[7.4,0.,0.],[-1.01,0.,0.],180.,3.);
checkDock(!moonDockGeometry($cm,$fast)['dockable'],'Relative speed >1m/s never docks');
foreach([[0.,0],[1.,0],[1.001,1],[5.,1],[5.001,2],[10.,2],[10.001,3],[20.,3]] as [$speed,$n])
 checkDock(moonDockDamageCount($speed)===$n,'Incorrect collision failure band at '.$speed);
$cm2=dockObject(3,[0.,0.,0.],[0.,0.,0.],0.,3.9);
$lm2=dockObject(6,[20.,0.,0.],[-100.,0.,0.],180.,3.);
$end=dockObject(6,[-80.,0.,0.],[-100.,0.,0.],180.,3.);
checkDock(moonDockSweptDistance($cm2,$lm2,$cm2,$end)<1e-9,'Swept collision detects 1-second tunnel-through');
checkDock(moonDockSweptDistance($cm2,$lm2,$cm2,$lm2)>7.,'Separated craft do not collide');
$worker=file_get_contents(dirname(__DIR__).'/execute.php');
checkDock(str_contains($worker,'moonDockInstall($conn);'),'Docking schema setup outside tick');
checkDock(str_contains($worker,'moonDockingTick($conn,$beforeDock'),'Docking executed each physics step');
$physics=file_get_contents(dirname(__DIR__).'/physics.php');
checkDock(str_contains($physics,'$id===6&&$parent===3'),'Docked LM faces CM');
echo "Docking geometry, relative speed, swept collision and failure bands passed\n";
