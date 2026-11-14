<?php
declare(strict_types=1);
/**
 * Install optional P36 schema before any worker per-second transaction.
 * All DDL runs outside a mission tick to preserve atomic physics.
 */
function moonTerminalInstall(mysqli $db):void {
 $db->query("CREATE TABLE IF NOT EXISTS terminal_rendezvous (
  craft_id INT UNSIGNED PRIMARY KEY,
  sample_tick BIGINT UNSIGNED NULL,
  sample_count INT UNSIGNED NOT NULL DEFAULT 0,
  rx DOUBLE NULL,ry DOUBLE NULL,rz DOUBLE NULL,
  vx DOUBLE NULL,vy DOUBLE NULL,vz DOUBLE NULL,
  target_x DOUBLE NULL,target_y DOUBLE NULL,target_z DOUBLE NULL,
  last_range_m DOUBLE NULL,
  status VARCHAR(60) NOT NULL DEFAULT 'ACQUIRING',
  FOREIGN KEY(craft_id) REFERENCES objects(id)
 ) ENGINE=InnoDB");
 $db->query('INSERT IGNORE INTO terminal_rendezvous(craft_id) VALUES(3),(6)');
 $db->query("INSERT IGNORE INTO console_instruments(console_id,file_name,pos_x,pos_y)
 VALUES ('cmc','terminal_rendezvous.php',2020,270),
 ('cmp','terminal_rendezvous.php',2020,270),
 ('lmc','terminal_rendezvous.php',2020,270),
 ('lmp','terminal_rendezvous.php',2020,270)");
 $column=$db->query("SHOW COLUMNS FROM dsky_programs LIKE 'operation'")->fetch_assoc();
 if(!$column)throw new RuntimeException('DSKY programs not installed for P36');
 if(!str_contains((string)$column['Type'],"'terminal_match'")){
  $db->query("ALTER TABLE dsky_programs MODIFY operation ENUM(
   'burn_seconds','attitude_target','platform_align',
   'timed_delta_v','timed_rcs_dv','terminal_match') NOT NULL");
 }
 $db->query("INSERT INTO dsky_programs
 (craft_id,program_no,title,operation,config_json,
 field1_label,field1_min,field1_max)
 VALUES
 (3,36,'RCS TERMINAL MATCH','terminal_match',JSON_OBJECT('scale',1),
  'HOLD RANGE M',20,500),
 (6,36,'RCS TERMINAL MATCH','terminal_match',JSON_OBJECT('scale',1),
  'HOLD RANGE M',20,500)
 ON DUPLICATE KEY UPDATE title=VALUES(title),operation=VALUES(operation),
 config_json=VALUES(config_json),field1_label=VALUES(field1_label),
 field1_min=VALUES(field1_min),field1_max=VALUES(field1_max),
 field2_label=NULL,field3_label=NULL,field4_label=NULL");
}

/**
 * Radar observation reconstruction and alpha-beta-style relative tracking.
 * Radar truth is admitted ONLY through already-sampled computer_registers.
 * Ownship position and orientation are independent navigation + IMU.
 * No SELECT from objects here.
 */
function moonTerminalDot(array $a,array $b):float {return $a[0]*$b[0]+$a[1]*$b[1]+$a[2]*$b[2];}
function moonTerminalNorm(array $a):float {return sqrt(moonTerminalDot($a,$a));}
function moonTerminalBasis(float $pitch,float $yaw,float $roll):array {
 $p=deg2rad($pitch);$y=deg2rad($yaw);$r=deg2rad($roll);
 $f=[cos($p)*cos($y),cos($p)*sin($y),sin($p)];
 $right=[-sin($y),cos($y),0.];$up=[-sin($p)*cos($y),-sin($p)*sin($y),cos($p)];
 $rr=[];$uu=[];for($k=0;$k<3;$k++){
  $rr[$k]=$right[$k]*cos($r)+$up[$k]*sin($r);
  $uu[$k]=$up[$k]*cos($r)-$right[$k]*sin($r);
 }
 return [$f,$rr,$uu];
}
function moonTerminalRadarVector(float $range,float $azimuth,float $elevation,array $basis):array {
 $az=deg2rad($azimuth);$el=deg2rad($elevation);
 $local=[cos($el)*cos($az),cos($el)*sin($az),sin($el)];
 $inertial=[];
 for($k=0;$k<3;$k++)$inertial[$k]=$range*($local[0]*$basis[0][$k]+$local[1]*$basis[1][$k]+$local[2]*$basis[2][$k]);
 return $inertial;
}
function moonTerminalFilter(?array $prior,array $measured,float $rangeRate,int $tick):array {
 if($prior===null||$prior['sample_tick']===null||$tick<=(int)$prior['sample_tick']||
    $tick-(int)$prior['sample_tick']>5){
  return ['r'=>$measured,'v'=>[0.,0.,0.],'samples'=>1,'status'=>'ACQUIRING'];
 }
 $dt=(float)($tick-(int)$prior['sample_tick']);
 $r0=array_map('floatval',[$prior['rx'],$prior['ry'],$prior['rz']]);
 $v0=array_map('floatval',[$prior['vx'],$prior['vy'],$prior['vz']]);
 $pred=[];$residual=[];
 for($k=0;$k<3;$k++){
  $pred[$k]=$r0[$k]+$v0[$k]*$dt;
  $residual[$k]=$measured[$k]-$pred[$k];
 }
 // 0.65 position / 0.30 velocity update: smooth angular changes rather
 // than using a single noisy 1-second difference as a true velocity.
 $r=[];$v=[];
 for($k=0;$k<3;$k++){
  $r[$k]=$pred[$k]+.65*$residual[$k];
  $v[$k]=$v0[$k]+.30*$residual[$k]/$dt;
 }
 // Accurate scalar radar range-rate constrains radial velocity while
 // successive bearing changes supply transverse velocity.
 $n=moonTerminalNorm($measured);
 if($n>0.){
  $direction=array_map(static fn($x)=>$x/$n,$measured);
  $radial=moonTerminalDot($v,$direction);
  for($k=0;$k<3;$k++)$v[$k]+=.65*(-$rangeRate-$radial)*$direction[$k];
 }
 $samples=min(100000,(int)$prior['sample_count']+1);
 return ['r'=>$r,'v'=>$v,'samples'=>$samples,'status'=>$samples>=4?'TRACKING':'ACQUIRING'];
}
function moonTerminalTrackingTick(mysqli $db,int $tick):void {
 if(!$db->query("SHOW TABLES LIKE 'terminal_rendezvous'")->fetch_row())return;
 $radar=$db->prepare("SELECT register_key,numeric_value,valid,updated_tick FROM computer_registers WHERE craft_id=? AND register_key IN
 ('RENDEZVOUS_RANGE_M','RENDEZVOUS_RANGE_RATE_MPS','RENDEZVOUS_AZIMUTH_DEG','RENDEZVOUS_ELEVATION_DEG')");
 $nav=$db->prepare('SELECT x,y,z,last_tick FROM navigation_states WHERE craft_id=?');
 $imu=$db->prepare('SELECT pitch_deg,yaw_deg,roll_deg,last_tick FROM imu WHERE object_id=?');
 $priorQ=$db->prepare('SELECT * FROM terminal_rendezvous WHERE craft_id=? FOR UPDATE');
 $save=$db->prepare('UPDATE terminal_rendezvous SET sample_tick=?,sample_count=?,rx=?,ry=?,rz=?,vx=?,vy=?,vz=?,target_x=?,target_y=?,target_z=?,last_range_m=?,status=? WHERE craft_id=?');
 $hold=$db->prepare('UPDATE terminal_rendezvous SET status=? WHERE craft_id=?');
 foreach([3,6] as $craft){
  $radar->bind_param('i',$craft);$radar->execute();
  $regs=[];foreach($radar->get_result()->fetch_all(MYSQLI_ASSOC) as $row)$regs[$row['register_key']]=$row;
  $nav->bind_param('i',$craft);$nav->execute();$own=$nav->get_result()->fetch_assoc();
  $imu->bind_param('i',$craft);$imu->execute();$att=$imu->get_result()->fetch_assoc();
  $priorQ->bind_param('i',$craft);$priorQ->execute();$previous=$priorQ->get_result()->fetch_assoc();
  if(!$previous)continue;
  $names=['RENDEZVOUS_RANGE_M','RENDEZVOUS_RANGE_RATE_MPS','RENDEZVOUS_AZIMUTH_DEG','RENDEZVOUS_ELEVATION_DEG'];
  $valid=$own&&$att&&(int)$own['last_tick']===$tick&&(int)$att['last_tick']===$tick;
  foreach($names as $name){
   $reg=$regs[$name]??null;
   if(!$reg||(int)$reg['valid']!==1||(int)$reg['updated_tick']!==$tick||!is_finite((float)$reg['numeric_value']))$valid=false;
  }
  if(!$valid){
   $status='RADAR / NAV / IMU UNAVAILABLE';
   $hold->bind_param('si',$status,$craft);$hold->execute();continue;
  }
  $range=(float)$regs['RENDEZVOUS_RANGE_M']['numeric_value'];
  $rate=(float)$regs['RENDEZVOUS_RANGE_RATE_MPS']['numeric_value'];
  if($range<=0.||$range>740000.)continue;
  $basis=moonTerminalBasis((float)$att['pitch_deg'],(float)$att['yaw_deg'],(float)$att['roll_deg']);
  $r=moonTerminalRadarVector($range,
   (float)$regs['RENDEZVOUS_AZIMUTH_DEG']['numeric_value'],
   (float)$regs['RENDEZVOUS_ELEVATION_DEG']['numeric_value'],$basis);
  $estimate=moonTerminalFilter($previous,$r,$rate,$tick);
  $pos=$estimate['r'];$vel=$estimate['v'];
  $target=[(float)$own['x']+$pos[0],(float)$own['y']+$pos[1],(float)$own['z']+$pos[2]];
  $samples=$estimate['samples'];$status=$estimate['status'];
  $save->bind_param('iiddddddddddsi',$tick,$samples,
   $pos[0],$pos[1],$pos[2],$vel[0],$vel[1],$vel[2],
   $target[0],$target[1],$target[2],$range,$status,$craft);
  $save->execute();
 }
}
