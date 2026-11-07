<?php
declare(strict_types=1);

/**
 * Moon reuses SWC Back Office 5's existing SWCSESSION identity.
 * No independent operator credentials or password file are used.
 */
function moonSimRoot(): string {
 return dirname(__DIR__);
}
function moonSimSwcRoot(): string {
 return dirname(moonSimRoot());
}
function moonSimSecureTransport():bool {
 return (!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')||(int)($_SERVER['SERVER_PORT']??0)===443;
}
function moonSimRequireHttps():void {
 if(PHP_SAPI==='cli')return;
 if(!moonSimSecureTransport()&&!in_array((string)($_SERVER['REMOTE_ADDR']??''),['127.0.0.1','::1'],true)){
  http_response_code(403);exit('HTTPS required');
 }
}
function moonSimSessionStart():void {
 if(PHP_SAPI==='cli'||session_status()===PHP_SESSION_ACTIVE)return;
 moonSimRequireHttps();
 ini_set('session.use_strict_mode','1');
 ini_set('session.use_only_cookies','1');
 session_name('SWCSESSION');
 session_set_cookie_params(['lifetime'=>43200,'path'=>'/','secure'=>moonSimSecureTransport(),'httponly'=>true,'samesite'=>'Lax']);
 session_start();
}
function moonSimLoginUrl():string {
 return '/backoffice5/';
}
function moonSimSwcAuthAvailable():bool {
 $auth=moonSimSwcRoot().'/backoffice5/dbfiles/auth.php';
 $db=dirname($auth).'/db.php';
 return is_file($auth)&&is_file($db);
}
/** Calls the existing SWC auth when its expected includes are deployed. */
function moonSimLoadSwcAuth():void {
 if(!moonSimSwcAuthAvailable())return;
 require_once moonSimSwcRoot().'/backoffice5/dbfiles/auth.php';
}
function moonSimRequireOperator(bool $control=true):void {
 if(PHP_SAPI==='cli')return;
 moonSimRequireHttps();
 // SWC auth.php is the authority where available. Its sessions already
 // encode user identity, expiry and last activity.
 if(moonSimSwcAuthAvailable()){
  moonSimLoadSwcAuth();
  require_login(moonSimLoginUrl());
 }else{
  // Fallback for deployments where auth.php's own db.php was not bundled:
  // require a genuine SWCSESSION, never a Moon-specific login.
  moonSimSessionStart();
  $active=!empty($_SESSION['is_logged_in'])&&($_SESSION['is_logged_in']===true)
   &&(int)($_SESSION['user_id']??$_SESSION['userid']??0)>0;
  $last=(int)($_SESSION['last_activity']??0);
  $active=$active&&$last>0&&time()-$last<43200;
  if(!$active){
   moonSimRejectUnauthenticated();
  }
  $_SESSION['last_activity']=time();
 }
 // Header policies are emitted by the entry page before HTML begins.
 // Instrument fragments may invoke this guard again after output starts.
 if(!headers_sent()){
  header('Cache-Control: no-store');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: same-origin');
 }
 if($control&&!in_array(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET')),['GET','HEAD','OPTIONS'],true))
  moonSimRequireSameOriginMutation();
}
function moonSimRejectUnauthenticated():void {
 if(!in_array(strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET')),['GET','HEAD'],true)){
  http_response_code(401);exit('Standard Window Cleaning login required');
 }
 header('Location: '.moonSimLoginUrl(),true,303);exit;
}
function moonSimRequireSameOriginMutation():void {
 if(PHP_SAPI==='cli')return;
 $method=strtoupper((string)($_SERVER['REQUEST_METHOD']??'GET'));
 if(!in_array($method,['POST','PUT','PATCH','DELETE'],true)){http_response_code(405);exit('Unsupported control mutation method');}
 $site=strtolower((string)($_SERVER['HTTP_SEC_FETCH_SITE']??''));
 if(in_array($site,['cross-site','none'],true)){http_response_code(403);exit('Cross-site control denied');}
 $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
 $host=preg_replace('/:\\d+$/','',$host);
 $source=(string)($_SERVER['HTTP_ORIGIN']??'');
 if($source==='')$source=(string)($_SERVER['HTTP_REFERER']??'');
 $parts=$source!==''?parse_url($source):false;
 if(!is_array($parts)||strtolower((string)($parts['host']??''))!==$host||
  strtolower((string)($parts['scheme']??''))!==(moonSimSecureTransport()?'https':'http')){
  http_response_code(403);exit('Origin verification required for controls');
 }
}
function moonSimRequireWorker():void {
 if(PHP_SAPI==='cli')return;
 moonSimRequireHttps();
 $expected=(string)(getenv('MOON_WORKER_TOKEN')?:'');
 $provided=(string)($_SERVER['HTTP_X_MOON_WORKER_TOKEN']??'');
 if($expected===''||!hash_equals($expected,$provided)){
  http_response_code(403);exit('Worker authorization required');
 }
}
