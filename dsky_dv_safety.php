<?php
declare(strict_types=1);
/** Pure fail-safe helpers for the CSM SPS, all in SI units. */
function moonDvSpsExpectedAcceleration(float $thrustN,float $massKg):float {
 if(!is_finite($thrustN)||$thrustN<=0||!is_finite($massKg)||$massKg<=0)
  throw new DomainException('INVALID SPS THRUST / STACK MASS');
 return $thrustN/$massKg;
}
/**
 * Conservative upper bound for a commanded SPS burn: approx 125% of the
 * velocity impulse expected with the current (decreasing) stack mass.
 * This is deliberately INDEPENDENT of the G-meter.
 */
function moonDvSpsWatchdogExceeded(float $targetDv,float $spsThrustN,float $stackMassKg,int $firedTicks):bool {
 if(!is_finite($targetDv)||$targetDv<=0||$firedTicks<0)
  throw new DomainException('INVALID SPS TARGET / DURATION');
 $rate=moonDvSpsExpectedAcceleration($spsThrustN,$stackMassKg);
 return $firedTicks*$rate>max($targetDv*1.25,$targetDv+2.0);
}
function moonDvSpsSamplePlausible(float $axialAcceleration,float $expectedAcceleration):bool {
 if(!is_finite($axialAcceleration)||$axialAcceleration<0)return false;
 // The quantized G-meter, attitude and numerical integration may differ.
 // Detect near-zero readings or grossly wrong projection, not small noise.
 return $axialAcceleration>=$expectedAcceleration*.5
  && $axialAcceleration<=$expectedAcceleration*1.5+0.1;
}
/** Exact root stack mass for current physics acceleration, derived from the
 * same object mass + attached tank propellant as moonPropulsionTick.
 * No navigation velocity/position is read.
 */
function moonDvSpsMassModel(mysqli $db):array {
 $objects=[];
 foreach($db->query('SELECT id,active,dependent,dependency,dry_mass FROM objects ORDER BY id')->fetch_all(MYSQLI_ASSOC) as $row)
  $objects[(int)$row['id']]=$row;
 $tanks=[];
 foreach($db->query('SELECT vehicle_id,remaining_kg FROM propulsion_tanks ORDER BY vehicle_id')->fetch_all(MYSQLI_ASSOC) as $row)
  $tanks[(int)$row['vehicle_id']]=(float)$row['remaining_kg'];
 if(!isset($objects[3],$objects[4])||!moonPropulsionAllowed($objects,4))
  throw new DomainException('SPS STAGE / ROOT UNAVAILABLE');
 $root=moonPropulsionRoot($objects,4);
 $mass=0.;
 foreach($objects as $id=>$row)if((int)$row['active']===1&&moonPropulsionRoot($objects,(int)$id)===$root)
  $mass+=(float)$row['dry_mass']+max(0.,$tanks[$id]??0.);
 $engine=$db->query('SELECT thrust_n,mass_flow_kg_s FROM propulsion_engines WHERE id=401')->fetch_assoc();
 if(!$engine)throw new DomainException('SPS ENGINE MODEL MISSING');
 return ['mass_kg'=>$mass,'thrust_n'=>(float)$engine['thrust_n'],
  'flow_kg_s'=>(float)$engine['mass_flow_kg_s']];
}
