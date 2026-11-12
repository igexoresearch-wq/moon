<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/met_clock.php';
function metAssert(bool $good,string $description):void {
 if(!$good)throw new RuntimeException($description);
}
metAssert(moonMetFormat(0)==='000:00:00','Start of mission');
metAssert(moonMetFormat(59)==='000:00:59','One minute boundary');
metAssert(moonMetFormat(60)==='000:01:00','One minute');
metAssert(moonMetFormat(3661)==='001:01:01','Hours minutes and seconds');
metAssert(moonMetFormat(360000)==='100:00:00','100 hour rollover must NOT reset clock');
metAssert(moonMetFormat(432045)==='120:00:45','Mission hour 120');
try{moonMetFormat(-1);throw new RuntimeException('Negative MET accepted');}
catch(InvalidArgumentException $expected){}
$root=dirname(__DIR__);
$clock=file_get_contents($root.'/met_status.php');
metAssert(str_contains($clock,'SELECT tick FROM sim_clock WHERE id=1'),'Read real simulation clock');
metAssert(!str_contains($clock,'time()'),'Never use wall clock');
$instrument=file_get_contents($root.'/instruments/met_clock.php');
metAssert(str_contains($instrument,"fetch('met_status.php'"),'All consoles pull one clock');
metAssert(str_contains($instrument,'SELECT tick FROM sim_clock WHERE id=1'),'Server-rendered MET fallback');
metAssert(str_contains($instrument,'LOGIN REQUIRED / RELOAD'),'Identify authentication redirects');
metAssert(str_contains($instrument,'MET HTTP '),'Report HTTP response failures');
metAssert(!str_contains($instrument,"status.textContent='MET SIGNAL LOST'"),'Do not mask errors with generic message');
echo "Mission elapsed clock formatting and source checks passed\n";
