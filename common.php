<?php
declare(strict_types=1);
require_once __DIR__.'/shared/operator_security.php';
// Moon is an intentionally standalone simulator: do not load SWC Back
// Office 5 authentication, session credentials or its database connection.
// Retain HTTPS and same-origin checks on control mutations; existing
// endpoint CSRF checks still apply. This does NOT restrict public access.
if(PHP_SAPI!=='cli'){
 moonSimRequireHttps();
 $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
 if(!in_array($method,['GET','HEAD','OPTIONS'],true))
  moonSimRequireSameOriginMutation();
 if(!headers_sent()){
  header('Cache-Control: no-store');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: same-origin');
 }
}
$moonDbFile=__DIR__.'/db.php';
if(!is_file($moonDbFile))throw new RuntimeException('Moon database config missing: create moon/db.php using db.example.php');
require_once $moonDbFile;
if(!isset($db_host,$db_user,$db_password))throw new RuntimeException('Database credentials unavailable');
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$conn=new mysqli((string)$db_host,(string)$db_user,(string)$db_password,'moonland');
$conn->set_charset('utf8mb4');
const STATIONS=['cmc'=>'CM Commander','cmp'=>'CM Pilot','lmc'=>'LM Commander','lmp'=>'LM Pilot','fido'=>'FIDO','flight'=>'Flight'];
function moonConsole():string{$id=(string)($_GET['console']??'');if(!isset(STATIONS[$id])){http_response_code(400);exit('Invalid console');}return $id;}
function moonObserver(string $id):int{return in_array($id,['lmc','lmp'],true)?6:3;}
