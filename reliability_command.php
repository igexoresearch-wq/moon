<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
require_once __DIR__.'/reliability.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['error'=>'POST required']);exit;}
// A same-origin request protects the operator's session from cross-site triggers.
// The browser sends the Origin header for standard fetch POST requests.
$origin=$_SERVER['HTTP_ORIGIN']??'';
$host=$_SERVER['HTTP_HOST']??'';
if($origin==='' || !$host || parse_url($origin,PHP_URL_HOST)!==preg_replace('/:\d+$/','',$host)){
    http_response_code(403);echo json_encode(['error'=>'Same-origin POST required']);exit;
}
$data=json_decode(file_get_contents('php://input'),true);
if(!is_array($data)){http_response_code(400);echo json_encode(['error'=>'JSON required']);exit;}
$key=(string)($data['component']??'');
$operation=(string)($data['operation']??'activate');
if(strlen($operation)>48 || !preg_match('/^[a-z0-9_ -]+$/i',$operation)){
    http_response_code(400);echo json_encode(['error'=>'Invalid operation']);exit;
}
try{
    $conn->begin_transaction();
    $result=moonReliabilityCommand($conn,$key,$operation);
    $conn->commit();
    // Future button handlers should execute the actual hardware command only
    // after receiving allowed=true. This endpoint never applies thrust itself.
    // Crew endpoints never reveal the hidden failure state, roll, or duration.
    echo json_encode(['allowed'=>(bool)$result['allowed']],JSON_THROW_ON_ERROR);
}catch(InvalidArgumentException $e){
    $conn->rollback();http_response_code(400);echo json_encode(['error'=>$e->getMessage()]);
}catch(Throwable $e){
    $conn->rollback();error_log('Moon reliability: '.$e->getMessage());
    http_response_code(500);echo json_encode(['error'=>'Reliability command failed']);
}
