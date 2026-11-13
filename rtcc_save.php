<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
require_once __DIR__.'/fido_plan_trace.php';
header('Cache-Control: no-store');
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('SAVE REQUIRES POST');}
$csrf=(string)($_POST['csrf']??'');
if(empty($_SESSION['moon_rtcc_save_csrf'])||!hash_equals($_SESSION['moon_rtcc_save_csrf'],$csrf)){
 http_response_code(403);exit('INVALID SAVE TOKEN');
}
$data=$_SESSION['moon_rtcc_candidates']??null;
if(!is_array($data)||time()-(int)($data['saved_at']??0)>1800){
 http_response_code(409);exit('RTCC candidate expired. Calculate a new solution first.');
}
$option=filter_var($_POST['option']??null,FILTER_VALIDATE_INT);
if($option===false||$option<0||!isset($data['plans'][$option])){
 http_response_code(422);exit('INVALID RTCC OPTION');
}
$plan=$data['plans'][$option];
if(!isset($plan['burn1'],$plan['burn2'],$plan['dv1'],$plan['dv2'])){
 http_response_code(422);exit('INVALID SAVED MANEUVER');
}
try{
 // Initialize saved-plan storage on first use, independent of whether
 // the optional SQL install script was applied in phpMyAdmin.
 $conn->query("CREATE TABLE IF NOT EXISTS fido_saved_solutions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  saved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estimate_tick BIGINT UNSIGNED NOT NULL,
  planning_tick BIGINT UNSIGNED NOT NULL,
  label VARCHAR(90) NOT NULL,
  snapshot_json LONGTEXT NOT NULL,
  plan_json LONGTEXT NOT NULL,
  prediction_json LONGTEXT NOT NULL,
  INDEX fido_saved_solution_epoch(estimate_tick)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $column=$conn->query("SHOW COLUMNS FROM fido_saved_solutions LIKE 'prediction_json'")->fetch_row();
 if(!$column)$conn->query("ALTER TABLE fido_saved_solutions ADD COLUMN prediction_json LONGTEXT NULL");
 $epoch=(int)$data['estimate_tick'];$planning=(int)$data['planning_tick'];
 $label=sprintf('RTCC option %d / MET %03d:%02d',($option+1),intdiv($epoch,3600),intdiv($epoch%3600,60));
 $snapshot=json_encode($data['snapshot'],JSON_THROW_ON_ERROR);
 $planJson=json_encode($plan,JSON_THROW_ON_ERROR);
 $predicted=json_encode(fidoPlanTrace($data['snapshot'],$plan,$planning),JSON_THROW_ON_ERROR);
 $q=$conn->prepare('INSERT INTO fido_saved_solutions(estimate_tick,planning_tick,label,snapshot_json,plan_json,prediction_json) VALUES(?,?,?,?,?,?)');
 $q->bind_param('iissss',$epoch,$planning,$label,$snapshot,$planJson,$predicted);$q->execute();
 $id=(int)$conn->insert_id;
 if($id<1)throw new RuntimeException('Saved maneuver returned invalid ID');
 unset($_SESSION['moon_rtcc_candidates']);
 session_write_close();
 header('Location: fido_console.php?display=flight_compare&solution='.$id,true,303);
 exit;
}catch(Throwable $e){
 error_log('FIDO saved plan: '.$e->getMessage());
 http_response_code($e instanceof DomainException?422:503);
 header('Content-Type: text/html; charset=utf-8');
 $message=$e instanceof DomainException?$e->getMessage():'SOLUTION SAVE FAILED. CHECK PHP ERROR LOG FOR FIDO saved plan.';
 echo '<!doctype html><meta charset="utf-8"><title>RTCC Save Failure</title><style>body{background:#111b20;color:#e1e9de;font:16px monospace;padding:30px}a{color:#a5d8da}</style>';
 echo '<h1>RTCC / SAVE FAILED</h1><p>'.htmlspecialchars($message,ENT_QUOTES).'</p>';
 echo '<p><a href="rtcc_solution.php">RETURN TO RTCC</a></p>';
}
