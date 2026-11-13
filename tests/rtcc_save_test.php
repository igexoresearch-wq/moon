<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$save=file_get_contents($root.'/rtcc_save.php');
$view=file_get_contents($root.'/fido_page.php');
$rtcc=file_get_contents($root.'/rtcc_solution.php');
$compare=file_get_contents($root.'/fido_pages/flight_compare.php');
foreach([$save,$view,$rtcc,$compare] as $file)if($file===false)throw new RuntimeException('Required RTCC save file missing');
function assertSave(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
assertSave(str_contains($save,'CREATE TABLE IF NOT EXISTS fido_saved_solutions'),'Saved plan storage auto-initializes');
assertSave(str_contains($save,'ALTER TABLE fido_saved_solutions'),'Legacy save table can upgrade');
assertSave(str_contains($save,'fidoPlanTrace('),'Projected trace is frozen when saving');
assertSave(str_contains($save,'fido_console.php?display=flight_compare&solution='),'Saved solution redirect retains ID');
assertSave(str_contains($view,"\$key!=='flight_compare'"),'Flight compare does not reload every five seconds');
assertSave(str_contains($rtcc,"moon_rtcc_candidates"),'RTCC source option is cached for saving');
assertSave(str_contains($compare,'prediction_json'),'Comparison uses frozen predicted trace');
echo "RTCC save/load source regression checks passed\n";
