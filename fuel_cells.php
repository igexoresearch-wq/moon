<?php
declare(strict_types=1);
/** SM fuel cell reliability. Run after moonReliabilityTick in the same transaction. */
function moonFuelCellsTick(mysqli $db,int $tick):void {
 $v=$db->query('SELECT roll FROM reliability_random WHERE id=1 FOR UPDATE')->fetch_assoc();
 if(!$v)throw new RuntimeException('Fuel cell random unavailable');
 $roll=(int)$v['roll'];
 $sm=$db->query('SELECT dependency,dependent,active FROM objects WHERE id=4 FOR UPDATE')->fetch_assoc();
 if(!$sm)throw new RuntimeException('Service module missing');
 $mated=(int)$sm['dependent']===1 && (int)$sm['dependency']===3 && (int)$sm['active']===1;
 $rows=$db->query("SELECT id,component_key,state FROM reliability_components
 WHERE component_key IN ('sm_fuel_cell_1','sm_fuel_cell_2','sm_fuel_cell_3')
 ORDER BY component_key FOR UPDATE")->fetch_all(MYSQLI_ASSOC);
 if(count($rows)!==3)throw new RuntimeException('Install fuel_cells_schema.sql first');
 foreach($rows as $row){
  $n=(int)substr($row['component_key'],-1);
  $state=(int)$row['state'];
  if($state===3 || $state===4)continue; // permanently failed or intentionally OFF
  $kind=null;$duration=null;
  if(!$mated)$kind='permanent';
  elseif($state===1){
   // Shared roll. Overlapping endpoints take priority in numeric order.
   // 10-20 / 21-40 / 41-50 represent requested 10-20 / 20-40 / 40-50.
   if($roll===500+$n)$kind='permanent';
   elseif(($n===1 && $roll>=10 && $roll<=20) ||
          ($n===2 && $roll>=21 && $roll<=40) ||
          ($n===3 && $roll>=41 && $roll<=50)){
    $kind='temporary';$duration=random_int(60,3600);
   }
  }
  if($kind===null)continue;
  $newState=$kind==='permanent'?3:2;
  $restore=$duration===null?null:date('Y-m-d H:i:s',time()+$duration);
  $id=(int)$row['id'];$key=$row['component_key'];
  $description=!$mated?'CM/SM separation while fuel cell powered':($kind==='temporary'?'Fuel cell temporary outage':'Fuel cell permanent outage');
  $q=$db->prepare("UPDATE reliability_components SET state=?,state_since=NOW(),restore_at=? WHERE id=?");
  $q->bind_param('isi',$newState,$restore,$id);$q->execute();
  $q=$db->prepare("INSERT INTO reliability_failures
  (component_id,component_key,failure_type,description,roll,sim_tick,occurred_at,restore_at,acknowledged)
  VALUES (?,?,?,?,?,?,NOW(),?,0)");
  $q->bind_param('isssiis',$id,$key,$kind,$description,$roll,$tick,$restore);
  $q->execute();
 }
}
