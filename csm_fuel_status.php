<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try{
 // CSM SPS propellant belongs to the service module, object 4.
 $q=$conn->query('SELECT capacity_kg,remaining_kg FROM propulsion_tanks WHERE vehicle_id=4');
 $tank=$q->fetch_assoc();
 if(!$tank)throw new RuntimeException('CSM SPS tank missing');
 $capacity=(float)$tank['capacity_kg'];
 $remaining=(float)$tank['remaining_kg'];
 if(!is_finite($capacity)||$capacity<=0||!is_finite($remaining))
  throw new RuntimeException('Invalid SPS tank data');
 echo json_encode([
  'capacity_kg'=>$capacity,
  'remaining_kg'=>$remaining,
  'used_kg'=>max(0.,$capacity-$remaining),
  'remaining_pct'=>min(100.,max(0.,100.*$remaining/$capacity))
 ],JSON_THROW_ON_ERROR);
}catch(Throwable $e){
 error_log('Moon CSM fuel gauge: '.$e->getMessage());
 http_response_code(503);
 echo json_encode(['error'=>'SPS FUEL DATA UNAVAILABLE']);
}
