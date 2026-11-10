<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$craft=(int)($_GET['craft']??0);
if(!in_array($craft,[3,6],true)){http_response_code(400);echo json_encode(['error'=>'Invalid craft']);exit;}
try{
 $q=$conn->prepare('SELECT sample_tick,sampled_at,g_magnitude,g_forward,valid FROM g_meter_samples WHERE craft_id=?');
 $q->bind_param('i',$craft);$q->execute();$sample=$q->get_result()->fetch_assoc();
 if(!$sample){http_response_code(503);echo json_encode(['error'=>'G meter sample row missing. Run g_meter_schema.sql.']);exit;}
 $key=$craft===3?'cm_g_meter':'lm_g_meter';
 $q=$conn->prepare('SELECT state FROM reliability_components WHERE component_key=?');
 $q->bind_param('s',$key);$q->execute();$component=$q->get_result()->fetch_assoc();
 if(!$component){http_response_code(503);echo json_encode(['error'=>'G meter component missing. Run g_meter_schema.sql.']);exit;}
 $clock=$conn->query('SELECT tick,updated_at FROM sim_clock WHERE id=1')->fetch_assoc();
 $state=(int)$component['state'];$tick=(int)($clock['tick']??0);
 $workerAge=null;
 if(!empty($clock['updated_at']))$workerAge=max(0,time()-strtotime($clock['updated_at']));
 $sampleTick=$sample['sample_tick']===null?null:(int)$sample['sample_tick'];
 $valid=(bool)$sample['valid']&&$state===1&&$sampleTick!==null&&($tick-$sampleTick)<=2;
 $status='SAMPLED';
 if($state===4)$status='POWER OFF';
 elseif($state===2)$status='TEMPORARY FAILURE';
 elseif($state===3)$status='PERMANENT FAILURE';
 elseif($workerAge!==null && $workerAge>120)$status='SIMULATION WORKER NOT ADVANCING';
 elseif($sampleTick===null)$status='NO SAMPLE — CHECK SIMULATION WORKER';
 elseif($tick-$sampleTick>2)$status='STALE SAMPLE — CHECK SIMULATION WORKER';
 elseif(!$valid)$status='WAITING FOR SECOND SAMPLE';
 echo json_encode([
  'valid'=>$valid,
  'status'=>$status,
  'component_state'=>$state,
  'g_magnitude'=>$valid?(float)$sample['g_magnitude']:null,
  'g_forward'=>$valid?(float)$sample['g_forward']:null,
  'sample_tick'=>$sampleTick,
  'sim_tick'=>$tick,
  'sampled_at'=>$sample['sampled_at'],
  'clock_updated_at'=>$clock['updated_at']??null,
  'worker_age_seconds'=>$workerAge
 ],JSON_THROW_ON_ERROR);
}catch(Throwable $e){
 error_log('Moon G-meter status: '.$e->getMessage());
 http_response_code(503);
 echo json_encode(['error'=>'G meter data unavailable; check server PHP error log and installed schema.']);
}
