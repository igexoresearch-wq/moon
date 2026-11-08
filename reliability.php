<?php
declare(strict_types=1);
/**
 * Apollo reliability engine. Independent of hardware controls and UI.
 * Calls must occur inside an existing DB transaction when checking a command.
 *
 * Number per simulator second: 1..100000, shared by actions in that tick.
 * For each complete 1,000-number block:
 *   001..010 => missed activation, component remains operational.
 *   011..015 => temporary offline, from 1 to 60 minutes.
 * For the FIRST FIVE blocks only:
 *   016      => permanent offline.
 * Otherwise => operation permitted.
 *
 * These rates are user-selected game mechanics, not historical NASA data.
 */
function moonReliabilityTick(mysqli $db, int $tick):void {
    $roll=random_int(1,100000);
    $q=$db->prepare('UPDATE reliability_random SET roll=?,sim_tick=?,generated_at=NOW() WHERE id=1');
    $q->bind_param('ii',$roll,$tick);
    $q->execute();
    if($q->affected_rows===0){
        // The seed is supplied by reliability_schema.sql. Preserve clarity if not installed.
        $check=$db->query('SELECT id FROM reliability_random WHERE id=1')->fetch_assoc();
        if(!$check)throw new RuntimeException('Import reliability_schema.sql before executing reliability ticks');
    }
    // Restore temporary failures whose time has elapsed; alarm records persist.
    $db->query('UPDATE reliability_components SET state=1,state_since=NOW(),restore_at=NULL WHERE state=2 AND restore_at<=NOW()');
}

/**
 * On a button/ignition: use this method BEFORE changing any hardware state.
 * Returns ['allowed'=>bool,'reason'=>string,'state'=>1|2|3|4,'roll'=>int|null].
 * Unknown components fail closed rather than silently bypassing reliability.
 */
function moonReliabilityCommand(mysqli $db,string $key,string $operation='activate'):array {
    if(!preg_match('/^[a-z0-9_]{1,90}$/',$key))throw new InvalidArgumentException('Invalid component key');
    $q=$db->prepare('SELECT id,component_key,component_name,state,restore_at,failure_rule FROM reliability_components WHERE component_key=? FOR UPDATE');
    $q->bind_param('s',$key);$q->execute();$c=$q->get_result()->fetch_assoc();
    if(!$c)throw new RuntimeException('Component not registered: '.$key);
    $id=(int)$c['id'];
    if((int)$c['state']===2 && $c['restore_at']!==null && strtotime($c['restore_at'])<=time()){
        $reset=$db->prepare('UPDATE reliability_components SET state=1,state_since=NOW(),restore_at=NULL WHERE id=?');
        $reset->bind_param('i',$id);$reset->execute();$c['state']=1;
    }
    if((int)$c['state']!==1){
        return ['allowed'=>false,'reason'=>(int)$c['state']===4?'off':'unavailable','state'=>(int)$c['state'],'roll'=>null];
    }
    $random=$db->query('SELECT roll,sim_tick FROM reliability_random WHERE id=1 FOR UPDATE')->fetch_assoc();
    if(!$random)throw new RuntimeException('Reliability random value not initialized');
    $roll=(int)$random['roll'];$tick=(int)$random['sim_tick'];
    $block=intdiv($roll-1,1000);$slot=(($roll-1)%1000)+1;
    $type=null;$duration=null;$nextState=1;
    $rule=(string)$c['failure_rule'];
    if($rule==='legacy_rcs'){
        if($slot<=10)$type='missed';
        elseif($slot<=15){$type='temporary';$duration=random_int(60,3600);$nextState=2;}
        elseif($slot===16 && $block<5){$type='permanent';$nextState=3;}
    }elseif($rule==='imu_suffix_under_100' && $roll<100){
        if($roll%10===3){$type='temporary';$duration=random_int(60,3600);$nextState=2;}
        elseif($roll%10===4){$type='permanent';$nextState=3;}
    }

    if($type!==null){
        $desc=match($type){
            'missed'=>"$operation command did not respond",
            'temporary'=>"$operation unavailable; temporary component fault",
            'permanent'=>"$operation unavailable; component failed permanently"
        };
        $restore=$duration===null?null:date('Y-m-d H:i:s',time()+$duration);
        $insert=$db->prepare('INSERT INTO reliability_failures (component_id,component_key,failure_type,description,roll,sim_tick,occurred_at,restore_at,acknowledged) VALUES (?,?,?,?,?,?,NOW(),?,0)');
        $insert->bind_param('isssiis',$id,$key,$type,$desc,$roll,$tick,$restore);
        $insert->execute();
        if($nextState!==1){
            $update=$db->prepare('UPDATE reliability_components SET state=?,state_since=NOW(),restore_at=?,last_command_at=NOW() WHERE id=?');
            $update->bind_param('isi',$nextState,$restore,$id);$update->execute();
        }
    }
    if($nextState===1){
        $last=$db->prepare('UPDATE reliability_components SET last_command_at=NOW() WHERE id=?');
        $last->bind_param('i',$id);$last->execute();
    }
    return ['allowed'=>$type===null,'reason'=>$type??'ok','state'=>$nextState,'roll'=>$roll,
            'retry_after'=>$duration,'failure_id'=>$type!==null?$db->insert_id:null];
}

/** Future master-alarm button: acknowledge an event, not repair the hardware. */
function moonReliabilityAcknowledge(mysqli $db,int $failureId):bool {
    $q=$db->prepare('UPDATE reliability_failures SET acknowledged=1,acknowledged_at=NOW() WHERE id=? AND acknowledged=0');
    $q->bind_param('i',$failureId);$q->execute();return $q->affected_rows===1;
}
