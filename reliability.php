<?php
declare(strict_types=1);
/**
 * Every component has exactly six failure numbers based on its database ID.
 * Permanent: id. Temporary: 10000*n + id, n=1..5.
 * A single global random integer 1..70000000 is drawn per simulation second.
 */
function moonReliabilityFailureKind(int $id,int $roll):?string {
 if($id<1 || $id>=10000)throw new RuntimeException('Component ID must be 1..9999 for ID-based reliability');
 if($roll===$id)return 'permanent';
 for($i=1;$i<=5;$i++)if($roll===$i*10000+$id)return 'temporary';
 return null;
}
function moonReliabilityTick(mysqli $db, int $tick):void {
    $roll=random_int(1,70000000);
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
    // Every registered ON component participates once per tick, regardless of subsystem.
    $components=$db->query('SELECT id,component_key,state FROM reliability_components ORDER BY id FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
    $fail=$db->prepare('INSERT INTO reliability_failures (component_id,component_key,failure_type,description,roll,sim_tick,occurred_at,restore_at,acknowledged) VALUES (?,?,?,?,?,?,NOW(),?,0)');
    $update=$db->prepare('UPDATE reliability_components SET state=?,state_since=NOW(),restore_at=? WHERE id=?');
    foreach($components as $component){
        if((int)$component['state']!==1)continue;
        $id=(int)$component['id'];
        $kind=moonReliabilityFailureKind($id,$roll);
        if($kind===null)continue;
        $state=$kind==='permanent'?3:2;
        $restore=$kind==='temporary'?date('Y-m-d H:i:s',time()+random_int(60,3600)):null;
        $key=(string)$component['component_key'];
        $description=$kind==='permanent'?'Component permanently unavailable':'Component temporarily unavailable';
        $update->bind_param('isi',$state,$restore,$id);$update->execute();
        $fail->bind_param('isssiis',$id,$key,$kind,$description,$roll,$tick,$restore);$fail->execute();
    }
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
    // The failure roll is applied to ALL ON components by moonReliabilityTick,
    // not a second time each time a crew button is pressed.
    $last=$db->prepare('UPDATE reliability_components SET last_command_at=NOW() WHERE id=?');
    $last->bind_param('i',$id);$last->execute();
    return ['allowed'=>true,'reason'=>'ok','state'=>1,'roll'=>null];

}

/** Future master-alarm button: acknowledge an event, not repair the hardware. */
function moonReliabilityAcknowledge(mysqli $db,int $failureId):bool {
    $q=$db->prepare('UPDATE reliability_failures SET acknowledged=1,acknowledged_at=NOW() WHERE id=? AND acknowledged=0');
    $q->bind_param('i',$failureId);$q->execute();return $q->affected_rows===1;
}
