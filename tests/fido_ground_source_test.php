<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/fido_ground.php';
require_once dirname(__DIR__).'/rtcc_rendezvous.php';
function assertGround(bool $valid,string $reason):void{
 if(!$valid)throw new RuntimeException('FIDO ground source regression: '.$reason);
}
$root=dirname(__DIR__);
foreach(['fido_page.php','fido_ground.php','rtcc_solution.php',
 'fido_pages/summary.php','fido_pages/orbit.php','fido_pages/state.php',
 'fido_pages/rendezvous.php','fido_pages/navigation.php'] as $name){
 $php=file_get_contents($root.'/'.$name);
 assertGround(is_string($php),'Missing '.$name);
 assertGround(!preg_match('/\\b(?:FROM|JOIN)\\s+`?objects`?\\b/i',$php),'Direct objects read in '.$name);
 assertGround(!preg_match('/\\b(?:FROM|JOIN)\\s+`?navigation_states`?\\b/i',$php),'Onboard navigation read in '.$name);
}
$solver=file_get_contents($root.'/rtcc_solution.php');
assertGround(str_contains($solver,'fidoGroundSnapshot($conn)'),'RTCC must read FIDO estimate adapter');
assertGround(str_contains($solver,'rtccRendezvousSolve($estimates,$options)'),'RTCC must solve FIDO states');
$source=file_get_contents($root.'/rtcc_rendezvous.php');
assertGround(str_contains($source,'float $earthRadius'),'Collision radii should have numeric types');
// Standard RTCC test runs separately. This guards against source-of-truth regression.
echo "FIDO estimate input isolation checks passed\n";
