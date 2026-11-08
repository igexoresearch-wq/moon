<?php
declare(strict_types=1);
/** Signed current: positive = demand, negative = generation. One simulation second. */
function moonElectricalTick(mysqli $db,int $tick):void {
 $sm=$db->query('SELECT active,dependent,dependency FROM objects WHERE id=4 FOR UPDATE')->fetch_assoc();
 if(!$sm)throw new RuntimeException('SM missing');
 $mated=(int)$sm['active']===1&&(int)$sm['dependent']===1&&(int)$sm['dependency']===3;
 $b=$db->query('SELECT * FROM batteries WHERE craft_id=3 ORDER BY id FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
 if(count($b)!==2)throw new RuntimeException('Install batteries_schema.sql');
 $c=$db->query("SELECT id,component_key,component_name,spacecraft_id,amps,state,auto_shed FROM reliability_components
 WHERE spacecraft_id IN (3,4) ORDER BY id FOR UPDATE")->fetch_all(MYSQLI_ASSOC);
 $net=function()use(&$c,$mated):float{$total=0.;
  foreach($c as $row)if((int)$row['state']===1 && ($mated || (int)$row['spacecraft_id']!==4))$total+=(float)$row['amps'];
  return $total;
 };
 $current=$net();
 $active=array_values(array_filter($b,fn($x)=>(int)$x['enabled']===1));
 // Positive demand even when fuel cells are operating means insufficient generation.
 $undervolt=$mated && $current>1e-9;
 $available=0.;foreach($active as $v)$available+=(float)$v['charge_ah'];
 $required=max(0.,$current)/3600.;
 $shed=false;
 if($current>0 && $required>$available+1e-12){
  // Remove the smallest positive working demand first. No randomness and no permanent damage.
  $candidates=array_keys(array_filter($c,fn($x)=>(int)$x['state']===1 && (float)$x['amps']>0 && (int)$x['auto_shed']===0));
  usort($candidates,fn($a,$b)=>((float)$c[$a]['amps']<=>(float)$c[$b]['amps'])?:strcmp($c[$a]['component_key'],$c[$b]['component_key']));
  foreach($candidates as $idx){
   $id=(int)$c[$idx]['id'];
   $q=$db->prepare('UPDATE reliability_components SET state=4,auto_shed=1,state_since=NOW() WHERE id=?');
   $q->bind_param('i',$id);$q->execute();$c[$idx]['state']=4;$c[$idx]['auto_shed']=1;
   $shed=true;$current=$net();
   if($current<=0 || $current/3600.<=$available+1e-12)break;
  }
 }
 // Share current evenly as far as possible, then share any remainder among batteries
 // with charge/headroom. Uses Ah and never crosses 0 or nameplate capacity.
 $delta=-$current/3600.;
 if($active && abs($delta)>1e-12){
  $remaining=abs($delta);
  $charging=$delta>0;
  $levels=[];foreach($active as $x)$levels[(int)$x['id']]=(float)$x['charge_ah'];
  for($pass=0;$pass<4 && $remaining>1e-12;$pass++){
   $eligible=[];
   foreach($active as $x){$id=(int)$x['id'];$space=$charging?(float)$x['capacity_ah']-$levels[$id]:$levels[$id];if($space>1e-12)$eligible[$id]=$space;}
   if(!$eligible)break;
   $share=$remaining/count($eligible);$applied=0.;
   foreach($eligible as $id=>$space){$quantity=min($share,$space);$levels[$id]+=$charging?$quantity:-$quantity;$applied+=$quantity;}
   $remaining-=$applied;
  }
  $q=$db->prepare('UPDATE batteries SET charge_ah=? WHERE id=?');
  foreach($levels as $id=>$level){$q->bind_param('di',$level,$id);$q->execute();}
 }
 // When a battery is empty, no further deficit is removed from below zero.
 // Alarm remains active until electrical conditions recover, even after acknowledgement.
 $anyShed=$shed;
 foreach($c as $row)if((int)$row['auto_shed']===1)$anyShed=true;
 $update=$db->prepare('UPDATE electrical_alarms SET acknowledged=IF(active=0 AND ?=1,0,acknowledged),active=?,updated_tick=? WHERE craft_id=3 AND alarm_key=?');
 foreach(['BUS UNDERVOLT'=>$undervolt,'LOAD SHED'=>$anyShed||($current>0 && !$active)] as $key=>$flag){
  $val=$flag?1:0;
  $update->bind_param('iiis',$val,$val,$tick,$key);$update->execute();
 }
}
