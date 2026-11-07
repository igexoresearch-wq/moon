<?php
declare(strict_types=1);
require_once __DIR__.'/shared/operator_security.php';
moonSimRequireOperator();
$root=dirname(__DIR__).'/db.php';
if(!is_file($root))throw new RuntimeException('Shared SWC db.php not found');
require_once $root;
if(!isset($db_host,$db_user,$db_password))throw new RuntimeException('Database credentials unavailable');
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$conn=new mysqli((string)$db_host,(string)$db_user,(string)$db_password,'moonland');
$conn->set_charset('utf8mb4');
const STATIONS=['cmc'=>'CM Commander','cmp'=>'CM Pilot','lmc'=>'LM Commander','lmp'=>'LM Pilot','fido'=>'FIDO','flight'=>'Flight'];
function moonConsole():string{$id=(string)($_GET['console']??'');if(!isset(STATIONS[$id])){http_response_code(400);exit('Invalid console');}return $id;}
function moonObserver(string $id):int{return in_array($id,['lmc','lmp'],true)?6:3;}
