<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$endpoint=file_get_contents($root.'/csm_fuel_status.php');
$gauge=file_get_contents($root.'/instruments/csm_fuel.php');
$migration=file_get_contents($root.'/csm_fuel_gauge_schema.sql');
function checkCsmFuel(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
checkCsmFuel(str_contains($endpoint,'WHERE vehicle_id=4'),'CSM gauge reads service module tank');
checkCsmFuel(str_contains($endpoint,'remaining_kg'),'Remaining propellant exposed');
checkCsmFuel(str_contains($endpoint,'capacity_kg'),'Tank capacity exposed');
checkCsmFuel(!str_contains($endpoint,'UPDATE propulsion_tanks'),'Gauge must remain read only');
checkCsmFuel(str_contains($gauge,"fetch('csm_fuel_status.php'"),'Gauge polls read-only endpoint');
checkCsmFuel(str_contains($migration,"'cmc'")&&str_contains($migration,"'cmp'"),'Both CSM consoles receive gauge');
echo "CSM fuel gauge source checks passed\n";
