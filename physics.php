<?php
declare(strict_types=1);
require_once __DIR__.'/propulsion.php';
/** One second of gravity plus translation and Euler attitude integration.
 * Earth (id=1) remains fixed. Attached children inherit parent motion.
 * Impact resolves at the spherical surface crossing and stores local XYZ.
 */
function moonV2Physics(mysqli $db,float $dt=1.0):void {
 $rows=$db->query('SELECT * FROM objects ORDER BY id FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
 $objects=[];foreach($rows as $row)$objects[(int)$row['id']]=$row;
 if(!isset($objects[1],$objects[2]))throw new RuntimeException('Earth and Moon missing');
 $earth=$objects[1];$moon=$objects[2];
 // Returns zero acceleration when propulsion tables are not installed or all
 // engines are off. Fuel changes share this tick's database transaction.
 $engineAcceleration=moonPropulsionTick($db,$objects,$dt);

 $updates=[];
 foreach($objects as $id=>$o){
  if($id===1||(int)$o['active']!==1)continue;
  $parent=(int)($o['dependency']??0);
  if((int)$o['dependent']===1 && isset($objects[$parent]))continue;
  $pos=[(float)$o['x'],(float)$o['y'],(float)$o['z']];
  $vel=[(float)$o['delta_x'],(float)$o['delta_y'],(float)$o['delta_z']];
  $thrustAcceleration=$engineAcceleration[$id]??[0.,0.,0.];
  $acc=function(array $p)use($earth,$moon,$id,$thrustAcceleration):array {
   $a=[0.,0.,0.];
   foreach([$earth,$moon] as $body){
    if((int)$body['id']===$id)continue;
    $dx=[(float)$body['x']-$p[0],(float)$body['y']-$p[1],(float)$body['z']-$p[2]];
    $r2=$dx[0]**2+$dx[1]**2+$dx[2]**2;
    if($r2<=1.)continue;
    $g=6.67430e-11*(float)$body['dry_mass']/($r2*sqrt($r2));
    for($k=0;$k<3;$k++)$a[$k]+=$g*$dx[$k];
   }
   for($k=0;$k<3;$k++)$a[$k]+=$thrustAcceleration[$k];
   return $a;
  };
  $a0=$acc($pos);$next=[];for($k=0;$k<3;$k++)$next[$k]=$pos[$k]+$vel[$k]*$dt+0.5*$a0[$k]*$dt*$dt;
  $a1=$acc($next);$v=[];for($k=0;$k<3;$k++)$v[$k]=$vel[$k]+0.5*($a0[$k]+$a1[$k])*$dt;
  $landId=$o['land']===null?null:(int)$o['land'];$landOffset=null;
  if($landId!==null&&isset($objects[$landId])){
   $body=$objects[$landId];$landOffset=[(float)$o['land_x'],(float)$o['land_y'],(float)$o['land_z']];
   $next=[(float)$body['x']+$landOffset[0],(float)$body['y']+$landOffset[1],(float)$body['z']+$landOffset[2]];
   $v=[(float)$body['delta_x'],(float)$body['delta_y'],(float)$body['delta_z']];
  }else{
   foreach([$earth,$moon] as $body){
    if((int)$body['id']===$id)continue;
    $center=[(float)$body['x'],(float)$body['y'],(float)$body['z']];
    $radius=(float)$body['radius_length']+(float)$o['radius_length'];
    $d0=0.;$d1=0.;
    for($k=0;$k<3;$k++){$d0+=($pos[$k]-$center[$k])**2;$d1+=($next[$k]-$center[$k])**2;}
    if($d0>$radius*$radius && $d1<=$radius*$radius){
     // Binary search the segment for the first spherical surface crossing.
     $lo=0.;$hi=1.;
     for($i=0;$i<48;$i++){$m=($lo+$hi)/2;$d2=0.;for($k=0;$k<3;$k++)$d2+=($pos[$k]+($next[$k]-$pos[$k])*$m-$center[$k])**2;
      if($d2>$radius*$radius)$lo=$m;else $hi=$m;}
     $landId=(int)$body['id'];$landOffset=[];
     for($k=0;$k<3;$k++){$landOffset[$k]=$pos[$k]+($next[$k]-$pos[$k])*$hi-$center[$k];$next[$k]=$center[$k]+$landOffset[$k];$v[$k]=(float)$body[['delta_x','delta_y','delta_z'][$k]];}
     break;
    }
   }
  }
  $updates[$id]=['p'=>$next,'v'=>$v,'land'=>$landId,'landOffset'=>$landOffset,
   'att'=>[(float)$o['pitch']+(float)$o['pitch_rate']*$dt,(float)$o['roll']+(float)$o['roll_rate']*$dt,(float)$o['yaw']+(float)$o['yaw_rate']*$dt]];
 }
 // Resolve attached objects after free bodies, including chains.
 for($pass=0;$pass<count($objects);$pass++){
  $changed=false;
  foreach($objects as $id=>$o){
   if($id===1||(int)$o['active']!==1||isset($updates[$id])||(int)$o['dependent']!==1)continue;
   $parent=(int)($o['dependency']??0);if(!isset($updates[$parent]))continue;
   $up=$updates[$parent];
   // A dependent object's offset is measured rearward along its parent's
   // forward (+X) attitude axis. Rotated parents carry the child with them.
   $pitch=deg2rad($up['att'][0]);$yaw=deg2rad($up['att'][2]);
   $distance=(float)$o['offset_distance'];
   $forward=[cos($pitch)*cos($yaw),cos($pitch)*sin($yaw),sin($pitch)];
   $childPosition=[];
   for($k=0;$k<3;$k++)$childPosition[$k]=$up['p'][$k]-$distance*$forward[$k];
   $updates[$id]=['p'=>$childPosition,'v'=>$up['v'],'land'=>$up['land'],'landOffset'=>$up['landOffset'],'att'=>$up['att']];$changed=true;
  }
  if(!$changed)break;
 }
 $stmt=$db->prepare('UPDATE objects SET x=?,y=?,z=?,delta_x=?,delta_y=?,delta_z=?,pitch=?,roll=?,yaw=?,land=?,land_x=?,land_y=?,land_z=? WHERE id=?');
 foreach($updates as $id=>$u){[$x,$y,$z]=$u['p'];[$vx,$vy,$vz]=$u['v'];[$pitch,$roll,$yaw]=$u['att'];
  $land=$u['land'];$lx=$u['landOffset'][0]??null;$ly=$u['landOffset'][1]??null;$lz=$u['landOffset'][2]??null;
  $stmt->bind_param('dddddddddidddi',$x,$y,$z,$vx,$vy,$vz,$pitch,$roll,$yaw,$land,$lx,$ly,$lz,$id);
  $stmt->execute();
 }
}
