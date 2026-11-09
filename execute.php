<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
if(PHP_SAPI!=='cli')moonSimRequireWorker();
require_once __DIR__.'/physics.php';
require_once __DIR__.'/reliability.php';
require_once __DIR__.'/imu.php';
require_once __DIR__.'/fuel_cells.php';
require_once __DIR__.'/electrical.php';
require_once __DIR__.'/rendezvous_radar.php';
// Invoke once per minute from cron; runs up to 60 simulated one-second ticks.
// Prevent concurrent workers via a MySQL advisory lock.
$lock=$conn->query("SELECT GET_LOCK('moon_v2_execute',0) AS locked")->fetch_assoc();
if((int)$lock['locked']!==1)exit;
try {
 $started=microtime(true);
 for($tick=0;$tick<60;$tick++){
  $conn->begin_transaction();
  try{
   moonV2Physics($conn,1.0);
   $clock=$conn->query('SELECT tick FROM sim_clock WHERE id=1 FOR UPDATE')->fetch_assoc();
   if(!$clock)throw new RuntimeException('Simulation clock missing');
   moonReliabilityTick($conn,(int)$clock['tick']+1);
   moonFuelCellsTick($conn,(int)$clock['tick']+1);
   moonImuTick($conn,1.0,(int)$clock['tick']+1);
   moonElectricalTick($conn,(int)$clock['tick']+1);
   moonRendezvousRadarTick($conn,(int)$clock['tick']+1);
   // Future systems: include another one-tick module HERE.
   $conn->query('UPDATE sim_clock SET tick=tick+1,updated_at=NOW() WHERE id=1');
   $conn->commit();
  }catch(Throwable $e){$conn->rollback();throw $e;}
  $delay=($started+$tick+1)-microtime(true);
  if($delay>0)usleep((int)round($delay*1000000));
 }
}finally{$conn->query("SELECT RELEASE_LOCK('moon_v2_execute')");}
