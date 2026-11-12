<?php
declare(strict_types=1);
require_once __DIR__.'/reliability.php';
/**
 * One second of simulated SM RCS forward thrust for CM P33.
 * Uses the existing translation fuel, thrust, Isp and reliability model.
 * Updates ONLY physical velocity; never sets position or attitude.
 * The G-meter subsequently measures resulting delta-V for closed-loop cutoff.
 */
function moonDvRcsTranslate(mysqli $db):bool {
 $cm=$db->query('SELECT active,dependent,dependency,dry_mass,pitch,yaw FROM objects WHERE id=3 FOR UPDATE')->fetch_assoc();
 $sm=$db->query('SELECT active,dependent,dependency,dry_mass FROM objects WHERE id=4 FOR UPDATE')->fetch_assoc();
 if(!$cm||!$sm||(int)$cm['active']!==1||(int)$sm['active']!==1||
   (int)$sm['dependent']!==1||(int)$sm['dependency']!==3)return false;
 // Translation burn requires a free-flying CSM and no main-engine command.
 $assembly=[];
 $rows=$db->query('SELECT id,active,dependent,dependency FROM objects ORDER BY id FOR UPDATE');
 while($row=$rows->fetch_assoc())$assembly[(int)$row['id']]=$row;
 if(!moonPropulsionAllowed($assembly,4))return false;
 $engaged=$db->query('SELECT id FROM propulsion_engines WHERE command_on=1 OR firing=1 LIMIT 1 FOR UPDATE')->fetch_assoc();
 if($engaged)return false;
 $q=$db->prepare('SELECT * FROM rcs WHERE craft_id=4 FOR UPDATE');$q->execute();$rcs=$q->get_result()->fetch_assoc();
 if(!$rcs||(int)$rcs['enabled']!==1)return false;
 $power=$db->query("SELECT state FROM reliability_components WHERE component_key='cm_rcs_cluster' FOR UPDATE")->fetch_assoc();
 if(!$power||(int)$power['state']!==1)return false;
 if(!moonReliabilityCommand($db,'cm_rcs_translate_forward','DSKY P33 translation')['allowed'])return false;
 $thrust=(float)$rcs['thrust_n'];$isp=(float)$rcs['isp_s'];$dt=1.;
 if($thrust<=0||$isp<=0)throw new RuntimeException('RCS forward thrust invalid');
 $used=$thrust*$dt/($isp*9.80665);
 if((float)$rcs['fuel_kg']<$used*.4||(float)$rcs['oxidizer_kg']<$used*.6)return false;
 $tank=$db->query('SELECT remaining_kg FROM propulsion_tanks WHERE vehicle_id=4 FOR UPDATE')->fetch_assoc();
 $spsFuel=$tank?max(0.,(float)$tank['remaining_kg']):0.;
 $mass=(float)$cm['dry_mass']+(float)$sm['dry_mass']+$spsFuel+(float)$rcs['fuel_kg']+(float)$rcs['oxidizer_kg'];
 if($mass<=0.)throw new RuntimeException('Invalid RCS stack mass');
 $pitch=deg2rad((float)$cm['pitch']);$yaw=deg2rad((float)$cm['yaw']);
 $direction=[cos($pitch)*cos($yaw),cos($pitch)*sin($yaw),sin($pitch)];
 $dv=$thrust*$dt/$mass;
 $q=$db->prepare('SELECT delta_x,delta_y,delta_z FROM objects WHERE id=3 FOR UPDATE');
 $q->execute();$velocity=$q->get_result()->fetch_assoc();
 $v=[(float)$velocity['delta_x']+$dv*$direction[0],
     (float)$velocity['delta_y']+$dv*$direction[1],
     (float)$velocity['delta_z']+$dv*$direction[2]];
 $q=$db->prepare('UPDATE objects SET delta_x=?,delta_y=?,delta_z=? WHERE id=3');
 $q->bind_param('ddd',$v[0],$v[1],$v[2]);$q->execute();
 $fuel=(float)$rcs['fuel_kg']-$used*.4;$ox=(float)$rcs['oxidizer_kg']-$used*.6;
 $id=(int)$rcs['id'];
 $q=$db->prepare('UPDATE rcs SET fuel_kg=?,oxidizer_kg=? WHERE id=?');
 $q->bind_param('ddi',$fuel,$ox,$id);$q->execute();
 return true;
}
