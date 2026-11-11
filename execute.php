<?php
declare(strict_types=1);
// The worker's status log lives in the hosting account HOME, outside public_html.
function moonWorkerDiagnostic(string $message): void {
 $path=dirname(__DIR__,4).'/moon-worker-debug.log';
 $line=gmdate('Y-m-d H:i:s').' UTC | '.$message."\n";
 if(@file_put_contents($path,$line,FILE_APPEND|LOCK_EX)===false)error_log('Moon worker diagnostic: '.$message);
}
moonWorkerDiagnostic('START execute.php; PHP='.PHP_VERSION.'; SAPI='.PHP_SAPI);
register_shutdown_function(static function():void {
 $error=error_get_last();
 if($error!==null&&in_array($error['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR],true)){
  moonWorkerDiagnostic('FATAL '.$error['message'].' at '.$error['file'].':'.$error['line']);
 }
});
// Acquire an OS-level process lock BEFORE opening MySQL. Multiple cron jobs
// must not each consume one of the hosting account's limited connections.
$moonWorkerLockFile=fopen(sys_get_temp_dir().'/moon_program_execute_'.md5(__DIR__).'.lock','c');
if($moonWorkerLockFile===false)throw new RuntimeException('Cannot create Moon worker process lock');
if(!flock($moonWorkerLockFile,LOCK_EX|LOCK_NB)){moonWorkerDiagnostic('EXIT process lock occupied');fclose($moonWorkerLockFile);exit;}
moonWorkerDiagnostic('Process lock acquired');
try{
 require_once __DIR__.'/common.php';
 moonWorkerDiagnostic('Database connection established');
if(PHP_SAPI!=='cli')moonSimRequireWorker();
require_once __DIR__.'/physics.php';
require_once __DIR__.'/reliability.php';
require_once __DIR__.'/imu.php';
require_once __DIR__.'/fuel_cells.php';
require_once __DIR__.'/electrical.php';
require_once __DIR__.'/rendezvous_radar.php';
require_once __DIR__.'/g_meter.php';
require_once __DIR__.'/delta_v.php';
require_once __DIR__.'/dsky_runtime.php';
// Invoke once per minute from cron; runs up to 60 simulated one-second ticks.
// Prevent concurrent workers via a MySQL advisory lock.
$lock=$conn->query("SELECT GET_LOCK('moon_v2_execute',0) AS locked")->fetch_assoc();
if((int)$lock['locked']!==1){moonWorkerDiagnostic('EXIT MySQL lock unavailable');exit;}
moonWorkerDiagnostic('MySQL lock acquired; starting physics');
try {
 $started=microtime(true);
 for($tick=0;$tick<60;$tick++){
  $conn->begin_transaction();
  try{
   // Apply programmed actuator commands before the one-second physics update.
   // Both actions and motion share the same transaction and roll back together.
   if(moonDskyInstalled($conn)){
    $nextClock=$conn->query('SELECT tick FROM sim_clock WHERE id=1 FOR UPDATE')->fetch_assoc();
    if(!$nextClock)throw new RuntimeException('Simulation clock missing');
    moonDskyBeforePhysics($conn,(int)$nextClock['tick']+1);
   }
   moonV2Physics($conn,1.0);
   if(moonDskyInstalled($conn))moonDskyAfterPhysics($conn);
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
    // Accumulate only a successfully sampled one-second G-meter reading.
    // Keep this inside the optional instrument savepoint.
    if(moonDeltaVInstalled($conn))moonDeltaVTick($conn,(int)$clock['tick']+1,1.0);
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
   moonWorkerDiagnostic('TICK ERROR '.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine().' — '.$e->getMessage());
   error_log('Moon physics worker failed at sim tick '.((int)($clock['tick']??-1)+1).': '.$e->getMessage());
   throw $e;
  }
  $delay=($started+$tick+1)-microtime(true);
  if($delay>0)usleep((int)round($delay*1000000));
 }
 moonWorkerDiagnostic('SUCCESS 60 ticks completed');
}finally{$conn->query("SELECT RELEASE_LOCK('moon_v2_execute')");}

}catch(Throwable $workerFailure){
 moonWorkerDiagnostic('WORKER ERROR '.get_class($workerFailure).' at '.basename($workerFailure->getFile()).':'.$workerFailure->getLine().' — '.$workerFailure->getMessage());
 throw $workerFailure;
}finally{
 flock($moonWorkerLockFile,LOCK_UN);
 fclose($moonWorkerLockFile);
}
