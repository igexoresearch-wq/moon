<?php
declare(strict_types=1);
// Shared MET source for all crew and Mission Control instruments.
// Only sim_clock.tick advances mission elapsed time; wall clock never does.
require_once __DIR__.'/common.php';
require_once __DIR__.'/met_clock.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
try{
 $row=$conn->query('SELECT tick FROM sim_clock WHERE id=1')->fetch_assoc();
 if(!$row)throw new RuntimeException('Mission clock not initialized');
 $tick=(int)$row['tick'];
 if($tick<0)throw new RuntimeException('Invalid mission clock');
 $hours=intdiv($tick,3600);
 $minutes=intdiv($tick%3600,60);
 $seconds=$tick%60;
 echo json_encode([
  'tick'=>$tick,
  'met'=>moonMetFormat($tick),
  'hours'=>$hours,'minutes'=>$minutes,'seconds'=>$seconds
 ],JSON_THROW_ON_ERROR);
}catch(Throwable $e){
 error_log('Moon MET clock: '.$e->getMessage());
 http_response_code(503);
 echo json_encode(['error'=>'MISSION CLOCK UNAVAILABLE']);
}
