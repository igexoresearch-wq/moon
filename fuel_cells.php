<?php
declare(strict_types=1);
/** SM fuel cell reliability. Run after moonReliabilityTick in the same transaction. */
function moonFuelCellsTick(mysqli $db,int $tick):void {
 $sm=$db->query('SELECT dependency,dependent,active FROM objects WHERE id=4 FOR UPDATE')->fetch_assoc();
 if(!$sm)throw new RuntimeException('Service module missing');
 $mated=(int)$sm['dependent']===1 && (int)$sm['dependency']===3 && (int)$sm['active']===1;
 $rows=$db->query("SELECT id,component_key,state FROM reliability_components
 WHERE component_key IN ('sm_fuel_cell_1','sm_fuel_cell_2','sm_fuel_cell_3')
 ORDER BY component_key FOR UPDATE")->fetch_all(MYSQLI_ASSOC);
 if(count($rows)!==3)throw new RuntimeException('Install fuel_cells_schema.sql first');
 foreach($rows as $row){
  $state=(int)$row['state'];
  if($state===3 || $state===4)continue; // permanently failed or intentionally OFF
  // Random ID-based faults are handled once for every component by
  // moonReliabilityTick. Here only the physical CM/SM separation rule applies.
  $kind=$mated?null:'permanent';
  $duration=null;
  if($kind===null)continue;
  $newState=$kind==='permanent'?3:2;
  $restore=$duration===null?null:date('Y-m-d H:i:s',time()+$duration);
  $id=(int)$row['id'];$key=$row['component_key'];
  $v=$db->query('SELECT roll FROM reliability_random WHERE id=1')->fetch_assoc();
  $roll=(int)$v['roll'];
  $description='CM/SM separation while fuel cell powered';
  $q=$db->prepare("UPDATE reliability_components SET state=?,state_since=NOW(),restore_at=? WHERE id=?");
  $q->bind_param('isi',$newState,$restore,$id);$q->execute();
  $q=$db->prepare("INSERT INTO reliability_failures
  (component_id,component_key,failure_type,description,roll,sim_tick,occurred_at,restore_at,acknowledged)
  VALUES (?,?,?,?,?,?,NOW(),?,0)");
  $q->bind_param('isssiis',$id,$key,$kind,$description,$roll,$tick,$restore);
  $q->execute();
 }
}
