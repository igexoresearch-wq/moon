<?php
declare(strict_types=1);
/**
 * Read-only FIDO estimated-state adapter shared by ALL ground displays
 * and RTCC. No objects table, onboard navigation, or G-meter reads.
 * Earth is translated to the origin to match the existing RTCC integrator.
 */
function fidoGroundSnapshot(mysqli $db):array {
 $tables=['fido_navigation_states','fido_navigation_bodies'];
 foreach($tables as $table){
  if(!$db->query("SHOW TABLES LIKE '".$table."'")->fetch_row())
   throw new DomainException('FIDO NAVIGATION NOT INSTALLED');
 }
 $states=$db->query('SELECT * FROM fido_navigation_states WHERE craft_id IN (3,6) ORDER BY craft_id')->fetch_all(MYSQLI_ASSOC);
 $bodies=$db->query('SELECT * FROM fido_navigation_bodies WHERE body_id IN (1,2) ORDER BY body_id')->fetch_all(MYSQLI_ASSOC);
 $state=[];$body=[];
 foreach($states as $row)$state[(int)$row['craft_id']]=$row;
 foreach($bodies as $row)$body[(int)$row['body_id']]=$row;
 foreach([3,6] as $id)if(!isset($state[$id]))throw new DomainException('FIDO SPACECRAFT ESTIMATE NOT INITIALIZED');
 foreach([1,2] as $id)if(!isset($body[$id]))throw new DomainException('FIDO CELESTIAL ESTIMATE NOT INITIALIZED');
 $tick=(int)$state[3]['last_tick'];
 if($tick!==(int)$state[6]['last_tick'] || $tick!==(int)$body[2]['last_tick']
   || $tick!==(int)$body[1]['last_tick'] && (int)$body[1]['last_tick']>(int)$body[2]['last_tick'])
   throw new DomainException('FIDO ESTIMATES NOT AT MATCHING EPOCH');
 // Earth is stationary in this project's physical model. The stored Earth
 // reference epoch may be the initial seed tick; its position remains fixed.
 $origin=$body[1];
 $out=[];
 foreach([1,2,3,6] as $id){
  $isBody=$id<=2;$r=$isBody?$body[$id]:$state[$id];
  $name=match($id){1=>'Earth',2=>'Moon',3=>'Command Module',6=>'Lunar Module'};
  $out[$id]=[
   'id'=>$id,'name'=>$name,'active'=>1,'dependent'=>0,'dependency'=>null,'land'=>null,
   'dry_mass'=>$isBody?(float)$r['mass_kg']:($id===3?5560.:2150.),
   'radius_length'=>match($id){1=>6371000.,2=>1737400.,3=>3.9,6=>3.},
  ];
  foreach(['x','y','z'] as $axis){
   $out[$id][$axis]=(float)$r[$axis]-(float)$origin[$axis];
   $out[$id]['delta_'.$axis]=(float)$r['v'.$axis]-(float)$origin['v'.$axis];
  }
  if(!$isBody){$out[$id]['status']=$r['status'];$out[$id]['last_tick']=$tick;$out[$id]['last_tracking_tick']=$r['last_tracking_tick'];}
 }
 return ['objects'=>$out,'tick'=>$tick];
}
