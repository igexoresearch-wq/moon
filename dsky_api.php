<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
require_once __DIR__.'/dsky_rendezvous.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['moon_dsky_csrf']))$_SESSION['moon_dsky_csrf']=bin2hex(random_bytes(24));
$craft=(int)($_GET['craft']??0);
if(!in_array($craft,[3,6],true)){http_response_code(400);echo json_encode(['error'=>'INVALID COMPUTER']);exit;}
function moonDskyJson(mysqli $db,int $craft):array {
 $q=$db->prepare('SELECT * FROM dsky_drafts WHERE craft_id=?');$q->bind_param('i',$craft);$q->execute();
 $draft=$q->get_result()->fetch_assoc();
 $q=$db->prepare('SELECT program_no,title,operation,config_json,field1_label,field2_label,field3_label,field4_label FROM dsky_programs WHERE craft_id=? AND enabled=1 ORDER BY program_no');
 $q->bind_param('i',$craft);$q->execute();$catalog=$q->get_result()->fetch_all(MYSQLI_ASSOC);
 $selected=null;
 foreach($catalog as $p)if((int)$p['program_no']===(int)($draft['program_no']??-1))$selected=$p;
 $q=$db->prepare('SELECT register_no,program_no,state,elapsed_ticks,message FROM dsky_registers WHERE craft_id=? ORDER BY register_no');
 $q->bind_param('i',$craft);$q->execute();$registers=$q->get_result()->fetch_all(MYSQLI_ASSOC);
 return ['draft'=>$draft,'program'=>$selected,'registers'=>$registers,'csrf'=>$_SESSION['moon_dsky_csrf']];
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 $v=json_decode(file_get_contents('php://input'),true);
 if(!is_array($v)||!hash_equals((string)$_SESSION['moon_dsky_csrf'],(string)($v['csrf']??''))){
  http_response_code(403);echo json_encode(['error'=>'COMMAND REJECTED']);exit;
 }
 $action=(string)($v['action']??'');$value=$v['value']??null;
 try{
  $conn->begin_transaction();
  $q=$conn->prepare('SELECT * FROM dsky_drafts WHERE craft_id=? FOR UPDATE');
  $q->bind_param('i',$craft);$q->execute();$d=$q->get_result()->fetch_assoc();
  if(!$d)throw new DomainException('COMPUTER NOT INITIALIZED');
  if($action==='program'){
   if(!is_int($value)||$value<0||$value>9999)throw new DomainException('INVALID PROGRAM');
   $q=$conn->prepare('SELECT * FROM dsky_programs WHERE craft_id=? AND program_no=? AND enabled=1');
   $q->bind_param('ii',$craft,$value);$q->execute();$p=$q->get_result()->fetch_assoc();
   if(!$p)throw new DomainException('PROGRAM NOT INSTALLED');
   $q=$conn->prepare('UPDATE dsky_drafts SET program_no=?,field1=NULL,field2=NULL,field3=NULL,field4=NULL,selected_field=NULL,confirmed=0 WHERE craft_id=?');
   $q->bind_param('ii',$value,$craft);$q->execute();
  }elseif($action==='select_field'){
   if(!is_int($value)||$value<1||$value>4)throw new DomainException('INVALID REGISTER');
   $q=$conn->prepare('SELECT * FROM dsky_programs WHERE craft_id=? AND program_no=? AND enabled=1');
   $program=(int)($d['program_no']??0);$q->bind_param('ii',$craft,$program);$q->execute();$p=$q->get_result()->fetch_assoc();
   if(!$p||$p['field'.$value.'_label']===null)throw new DomainException('REGISTER NOT ASSIGNED');
   $q=$conn->prepare('UPDATE dsky_drafts SET selected_field=?,confirmed=0 WHERE craft_id=?');
   $q->bind_param('ii',$value,$craft);$q->execute();
  }elseif($action==='deselect_field'){
   $q=$conn->prepare('UPDATE dsky_drafts SET selected_field=NULL WHERE craft_id=?');
   $q->bind_param('i',$craft);$q->execute();
  }elseif($action==='enter'){
   if(!is_int($value)||$value<0||$value>9999)throw new DomainException('ENTER FOUR DIGITS');
   $field=(int)($d['selected_field']??0);if($field<1||$field>4)throw new DomainException('SELECT A DATA REGISTER');
   $q=$conn->prepare('SELECT * FROM dsky_programs WHERE craft_id=? AND program_no=? AND enabled=1');
   $program=(int)($d['program_no']??0);$q->bind_param('ii',$craft,$program);$q->execute();$p=$q->get_result()->fetch_assoc();
   if(!$p||$p['field'.$field.'_label']===null)throw new DomainException('REGISTER NOT ASSIGNED');
   if($value<(float)$p['field'.$field.'_min']||$value>(float)$p['field'.$field.'_max'])
    throw new DomainException('VALUE OUT OF RANGE');
   $name='field'.$field;
   $q=$conn->prepare("UPDATE dsky_drafts SET $name=?,selected_field=NULL,confirmed=0 WHERE craft_id=?");
   $q->bind_param('ii',$value,$craft);$q->execute();
  }elseif($action==='execute'){
   $program=(int)($d['program_no']??0);
   $q=$conn->prepare('SELECT * FROM dsky_programs WHERE craft_id=? AND program_no=? AND enabled=1');
   $q->bind_param('ii',$craft,$program);$q->execute();$p=$q->get_result()->fetch_assoc();
   if(!$p)throw new DomainException('NO PROGRAM LOADED');
   for($i=1;$i<=4;$i++)if($p['field'.$i.'_label']!==null &&
     ($d['field'.$i]===null||(float)$d['field'.$i]<(float)$p['field'.$i.'_min']||(float)$d['field'.$i]>(float)$p['field'.$i.'_max']))
      throw new DomainException('REQUIRED DATA MISSING');
   // P32 reserves both engine and attitude control until its scheduled burn finishes.
   // Do not let a simultaneous DSKY program compete for SPS/RCS actuators.
   $busy=$conn->prepare("SELECT p.operation FROM dsky_registers r JOIN dsky_programs p
    ON p.craft_id=r.craft_id AND p.program_no=r.program_no
    WHERE r.craft_id=? AND r.state IN ('queued','running')");
   $busy->bind_param('i',$craft);$busy->execute();
   foreach($busy->get_result()->fetch_all(MYSQLI_ASSOC) as $existing){
    $kind=(string)$existing['operation'];
    if(in_array($kind,['timed_delta_v','timed_rcs_dv'],true) || (in_array($p['operation'],['timed_delta_v','timed_rcs_dv'],true) && in_array($kind,['burn_seconds','attitude_target','platform_align'],true)))
     throw new DomainException('MANEUVER / ACTUATOR ALREADY OCCUPIED');
   }
   // Completed registers are reusable; failed or aborted registers require a manual CLEAR.
   $q=$conn->prepare("SELECT register_no FROM dsky_registers WHERE craft_id=? AND state IN ('empty','complete') ORDER BY register_no LIMIT 1 FOR UPDATE");
   $q->bind_param('i',$craft);$q->execute();$slot=$q->get_result()->fetch_assoc();
   if(!$slot)throw new DomainException('ALL FIVE REGISTERS OCCUPIED');
   $num=(int)$slot['register_no'];
   $q=$conn->prepare("UPDATE dsky_registers SET program_no=?,field1=?,field2=?,field3=?,field4=?,state='queued',started_tick=NULL,elapsed_ticks=0,message=NULL WHERE craft_id=? AND register_no=? AND state IN ('empty','complete')");
   $q->bind_param('iddddii',$program,$d['field1'],$d['field2'],$d['field3'],$d['field4'],$craft,$num);
   $q->execute();if($q->affected_rows!==1)throw new DomainException('REGISTER OCCUPIED');
   if(in_array($p['operation'],['timed_delta_v','timed_rcs_dv'],true)){
    if(!$conn->query("SHOW TABLES LIKE 'dsky_dv_burns'")->fetch_row())
     throw new DomainException('INSTALL P32 SCHEMA FIRST');
    moonDvSchedule($conn,$craft,$num,$d);
    // Assert the schedule is durable in this transaction before an EXECUTE
    // response can claim success. The worker must NEVER derive a fresh time.
    $verify=$conn->prepare('SELECT target_tick FROM dsky_dv_burns WHERE craft_id=? AND register_no=?');
    $verify->bind_param('ii',$craft,$num);$verify->execute();
    $scheduled=$verify->get_result()->fetch_assoc();
    if(!$scheduled||$scheduled['target_tick']===null)
     throw new DomainException('P32/P33 SCHEDULE COULD NOT BE SAVED');
   }
   $q=$conn->prepare('UPDATE dsky_drafts SET confirmed=1 WHERE craft_id=?');$q->bind_param('i',$craft);$q->execute();
  }elseif($action==='clear'){
   if(!is_int($value)||$value<1||$value>5)throw new DomainException('INVALID REGISTER');
   $q=$conn->prepare('SELECT * FROM dsky_registers WHERE craft_id=? AND register_no=? FOR UPDATE');
   $q->bind_param('ii',$craft,$value);$q->execute();$r=$q->get_result()->fetch_assoc();
   if(!$r)throw new DomainException('REGISTER NOT FOUND');
   if(in_array($r['state'],['queued','running'],true)){
    $q=$conn->prepare('SELECT operation,config_json FROM dsky_programs WHERE craft_id=? AND program_no=?');
    $program=(int)$r['program_no'];$q->bind_param('ii',$craft,$program);$q->execute();$p=$q->get_result()->fetch_assoc();
    if($p&&in_array($p['operation'],['timed_delta_v','timed_rcs_dv'],true)){
     if($p['operation']==='timed_delta_v')$conn->query('UPDATE propulsion_engines SET command_on=0,firing=0 WHERE id=401');
     $q=$conn->prepare("UPDATE dsky_dv_burns SET status='ABORTED' WHERE craft_id=? AND register_no=?");
     $q->bind_param('ii',$craft,$value);$q->execute();
    }
    if($p&&$p['operation']==='burn_seconds'){
     $cfg=json_decode($p['config_json'],true);
     if(is_array($cfg)){
      $ids=$cfg['engine_ids']??(isset($cfg['engine_id'])?[$cfg['engine_id']]:[]);
      if(is_array($ids)){
       $q=$conn->prepare('UPDATE propulsion_engines SET command_on=0,firing=0 WHERE id=?');
       foreach($ids as $rawId){$engine=(int)$rawId;if($engine<=0)continue;
        $q->bind_param('i',$engine);$q->execute();
       }
      }
     }
    }
   }
   $q=$conn->prepare("UPDATE dsky_registers SET program_no=NULL,field1=NULL,field2=NULL,field3=NULL,field4=NULL,state='empty',started_tick=NULL,elapsed_ticks=0,message=NULL WHERE craft_id=? AND register_no=?");
   $q->bind_param('ii',$craft,$value);$q->execute();
  }else throw new DomainException('UNKNOWN COMMAND');
  $conn->commit();
 }catch(DomainException $e){$conn->rollback();http_response_code(422);echo json_encode(['error'=>$e->getMessage()]);exit;}
 catch(Throwable $e){$conn->rollback();error_log('DSKY command error: '.$e->getMessage());http_response_code(500);echo json_encode(['error'=>'COMPUTER ERROR']);exit;}
}
echo json_encode(moonDskyJson($conn,$craft),JSON_THROW_ON_ERROR);
