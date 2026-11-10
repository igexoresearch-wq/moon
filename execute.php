<?php
declare(strict_types=1);
// Acquire an OS-level process lock BEFORE opening MySQL. Multiple cron jobs
// must not each consume one of the hosting account's limited connections.
$moonWorkerLockFile=fopen(sys_get_temp_dir().'/moon_program_execute_'.md5(__DIR__).'.lock','c');
if($moonWorkerLockFile===false)throw new RuntimeException('Cannot create Moon worker process lock');
if(!flock($moonWorkerLockFile,LOCK_EX|LOCK_NB)){fclose($moonWorkerLockFile);exit;}
try{
 require_once __DIR__.'/common.php';
if(PHP_SAPI!=='cli')moonSimRequireWorker();
require_once __DIR__.'/physics.php';
require_once __DIR__.'/reliability.php';
require_once __DIR__.'/imu.php';
require_once __DIR__.'/fuel_cells.php';
require_once __DIR__.'/electrical.php';
require_once __DIR__.'/rendezvous_radar.php';
require_once __DIR__.'/g_meter.php';
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
   // A failed optional gauge must never roll back the core flight physics.
   // A savepoint removes partial gauge writes before committing the tick.
   $conn->query('SAVEPOINT moon_g_meter');
   try{
    moonGMeterTick($conn,(int)$clock['tick']+1);
    $conn->query('RELEASE SAVEPOINT moon_g_meter');
   }catch(Throwable $gError){
    $conn->query('ROLLBACK TO SAVEPOINT moon_g_meter');
    $conn->query('RELEASE SAVEPOINT moon_g_meter');
    // Log at most once per worker invocation to avoid 60 identical errors.
    if(!isset($gMeterFailureLogged)){
     error_log('Moon optional G-meter failed (flight physics continues): '.$gError->getMessage());
     $gMeterFailureLogged=true;
    }
   }
   // Future systems: include another one-tick module HERE.
   $conn->query('UPDATE sim_clock SET tick=tick+1,updated_at=NOW() WHERE id=1');
   $conn->commit();
  }catch(Throwable $e){
   $conn->rollback();
   error_log('Moon physics worker failed at sim tick '.((int)($clock['tick']??-1)+1).': '.$e->getMessage());
   throw $e;
  }
  $delay=($started+$tick+1)-microtime(true);
  if($delay>0)usleep((int)round($delay*1000000));
 }
}finally{$conn->query("SELECT RELEASE_LOCK('moon_v2_execute')");}

}finally{
 flock($moonWorkerLockFile,LOCK_UN);
 fclose($moonWorkerLockFile);
}
