<?php
declare(strict_types=1);
// The worker's status log lives in the hosting account HOME, outside public_html.
function moonWorkerDiagnostic(string $message): void {
 $path=dirname(__DIR__,4).'/moon-worker-debug.log';
 $line=gmdate('Y-m-d H:i:s').' UTC | '.$message."\n";
 if(@file_put_contents($path,$line,FILE_APPEND|LOCK_EX)===false)error_log('Moon worker diagnostic: '.$message);
}
moonWorkerDiagnostic('START execute.php; PID='.getmypid().'; PHP='.PHP_VERSION.'; SAPI='.PHP_SAPI.'; max_execution_time='.ini_get('max_execution_time'));
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
// Cron starts on minute boundaries, while a 60-tick batch can legitimately
// still be committing its last tick. Wait briefly rather than DROP a whole
// batch due to a millisecond-scale lock collision. Never open MySQL while
// waiting, and never wait indefinitely behind a hung worker.
$moonLockWaitStarted=microtime(true);
$moonLockAcquired=false;
do{
 if(flock($moonWorkerLockFile,LOCK_EX|LOCK_NB)){$moonLockAcquired=true;break;}
 usleep(100000);
}while(microtime(true)-$moonLockWaitStarted<4.0);
$moonLockWaitMs=(int)round((microtime(true)-$moonLockWaitStarted)*1000);
if(!$moonLockAcquired){
 moonWorkerDiagnostic('EXIT process lock occupied after '.$moonLockWaitMs.'ms wait');
 fclose($moonWorkerLockFile);exit;
}
moonWorkerDiagnostic('Process lock acquired; waited_ms='.$moonLockWaitMs);
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
require_once __DIR__.'/navigation.php';
require_once __DIR__.'/fido_navigation.php';
require_once __DIR__.'/dsky_runtime.php';
// Invoke once per minute from cron; runs up to 60 simulated one-second ticks.
// Prevent concurrent workers via a MySQL advisory lock.
$lock=$conn->query("SELECT GET_LOCK('moon_v2_execute',0) AS locked")->fetch_assoc();
if((int)$lock['locked']!==1){moonWorkerDiagnostic('EXIT MySQL lock unavailable');exit;}
moonWorkerDiagnostic('MySQL lock acquired; starting physics');
try {
 $started=microtime(true);
 for($tick=0;$tick<60;$tick++){
  $tickStarted=microtime(true);
  $tickSections=[];
  $sectionStart=$tickStarted;
  $conn->begin_transaction();
  $tickSections['begin_transaction_ms']=(int)round((microtime(true)-$sectionStart)*1000);
  $sectionStart=microtime(true);
  try{
   // Apply programmed actuator commands before the one-second physics update.
   // Both actions and motion share the same transaction and roll back together.
   if(moonDskyInstalled($conn)){
    $nextClock=$conn->query('SELECT tick FROM sim_clock WHERE id=1 FOR UPDATE')->fetch_assoc();
    if(!$nextClock)throw new RuntimeException('Simulation clock missing');
    moonDskyBeforePhysics($conn,(int)$nextClock['tick']+1);
   }
   $tickSections['dsky_before_ms']=(int)round((microtime(true)-$sectionStart)*1000);
   $sectionStart=microtime(true);
   moonV2Physics($conn,1.0);
   $tickSections['physics_ms']=(int)round((microtime(true)-$sectionStart)*1000);
   $sectionStart=microtime(true);
   if(moonDskyInstalled($conn))moonDskyAfterPhysics($conn);
   $clock=$conn->query('SELECT tick FROM sim_clock WHERE id=1 FOR UPDATE')->fetch_assoc();
   if(!$clock)throw new RuntimeException('Simulation clock missing');
   $tickSections['dsky_after_ms']=(int)round((microtime(true)-$sectionStart)*1000);
   $sectionStart=microtime(true);
   moonReliabilityTick($conn,(int)$clock['tick']+1);
   moonFuelCellsTick($conn,(int)$clock['tick']+1);
   moonImuTick($conn,1.0,(int)$clock['tick']+1);
   moonElectricalTick($conn,(int)$clock['tick']+1);
   moonRendezvousRadarTick($conn,(int)$clock['tick']+1);
   // A failed optional gauge must never roll back the core flight physics.
   // A savepoint removes partial gauge writes before committing the tick.
   $tickSections['systems_ms']=(int)round((microtime(true)-$sectionStart)*1000);
   $sectionStart=microtime(true);
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
   // Cut off P32 SPS burns based on fresh G-meter delta-V samples.
   // This check must run OUTSIDE the optional gauge savepoint: if the gauge
   // fails, the maneuver fails safe with the main engine commanded OFF.
   $tickSections['g_meter_ms']=(int)round((microtime(true)-$sectionStart)*1000);
   $sectionStart=microtime(true);
   if(moonDskyInstalled($conn))moonDvAfterSensors($conn,(int)$clock['tick']+1);
   $tickSections['dsky_cutoff_ms']=(int)round((microtime(true)-$sectionStart)*1000);
   $sectionStart=microtime(true);
   // The navigation model is optional; never permit it to roll back flight physics.
   if(moonNavigationInstalled($conn)){
    $conn->query('SAVEPOINT moon_navigation');
    try{
     moonNavigationTick($conn,(int)$clock['tick']+1);
     $conn->query('RELEASE SAVEPOINT moon_navigation');
    }catch(Throwable $navigationError){
     $conn->query('ROLLBACK TO SAVEPOINT moon_navigation');
     $conn->query('RELEASE SAVEPOINT moon_navigation');
     if(!isset($navigationFailureLogged)){
      error_log('Moon optional navigation failed: '.$navigationError->getMessage());
      $navigationFailureLogged=true;
     }
    }
   }
   // Independent ground tracking, separately fault-contained from onboard navigation.
   $tickSections['navigation_ms']=(int)round((microtime(true)-$sectionStart)*1000);
   $sectionStart=microtime(true);
   if(moonFidoNavigationInstalled($conn)){
    $conn->query('SAVEPOINT moon_fido_navigation');
    try{
     moonFidoNavigationTick($conn,(int)$clock['tick']+1);
     $conn->query('RELEASE SAVEPOINT moon_fido_navigation');
    }catch(Throwable $groundError){
     $conn->query('ROLLBACK TO SAVEPOINT moon_fido_navigation');
     $conn->query('RELEASE SAVEPOINT moon_fido_navigation');
     if(!isset($groundNavFailureLogged)){
      error_log('Moon optional FIDO navigation failed: '.$groundError->getMessage());
      $groundNavFailureLogged=true;
     }
    }
   }
   // Future systems: include another one-tick module HERE.
   $tickSections['fido_navigation_ms']=(int)round((microtime(true)-$sectionStart)*1000);
   $sectionStart=microtime(true);
   $conn->query('UPDATE sim_clock SET tick=tick+1,updated_at=NOW() WHERE id=1');
   $conn->commit();
   $tickSections['commit_ms']=(int)round((microtime(true)-$sectionStart)*1000);
   $tickElapsedMs=(int)round((microtime(true)-$tickStarted)*1000);
   // Distinguish a killed or blocked cron worker from a single slow
   // database tick. Low-volume heartbeat every five committed ticks.
   if(($tick+1)%5===0){
    moonWorkerDiagnostic('PROGRESS PID='.getmypid().' batch_tick='.($tick+1).
     '/60 met='.((int)$clock['tick']+1).
     ' batch_elapsed_s='.round(microtime(true)-$started,2).
     ' last_tick_ms='.$tickElapsedMs);
   }
   if($tickElapsedMs>1500){
    arsort($tickSections);
    moonWorkerDiagnostic('SLOW TICK met='.((int)$clock['tick']+1).
     ' total_ms='.$tickElapsedMs.' sections='.json_encode($tickSections));
   }
  }catch(Throwable $e){
   $elapsedMs=(int)round((microtime(true)-$tickStarted)*1000);
   $conn->rollback();
   moonWorkerDiagnostic('FAILED TICK elapsed_ms='.$elapsedMs.
    ' last_section_ms='.(int)round((microtime(true)-$sectionStart)*1000).
    ' completed_sections='.json_encode($tickSections));
   moonWorkerDiagnostic('TICK ERROR '.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine().' — '.$e->getMessage());
   error_log('Moon physics worker failed at sim tick '.((int)($clock['tick']??-1)+1).': '.$e->getMessage());
   throw $e;
  }
  // Each physics step is still exactly 1.0 *simulated* second.
  // A 0.97s wall-time cadence completes 60 ticks in ~58.2s, leaving
  // headroom for PHP startup, cron jitter and lock release. The next
  // minute's batch preserves the average 60 simulated sec / wall minute.
  $delay=($started+($tick+1)*0.97)-microtime(true);
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
