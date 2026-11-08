<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$id=(int)($_GET['battery']??0);
if(!in_array($id,[1,2],true)){http_response_code(400);exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 $origin=$_SERVER['HTTP_ORIGIN']??'';$host=preg_replace('/:\d+$/','',$_SERVER['HTTP_HOST']??'');
 if(!$origin||parse_url($origin,PHP_URL_HOST)!==$host){http_response_code(403);exit;}
 $data=json_decode(file_get_contents('php://input'),true);
 if(!in_array($data['command']??'',['on','off'],true)){http_response_code(400);exit;}
 $v=$data['command']==='on'?1:0;
 $q=$conn->prepare('UPDATE batteries SET enabled=? WHERE craft_id=3 AND battery_number=?');
 $q->bind_param('ii',$v,$id);$q->execute();
}
$q=$conn->prepare('SELECT charge_ah,capacity_ah,enabled FROM batteries WHERE craft_id=3 AND battery_number=?');
$q->bind_param('i',$id);$q->execute();$row=$q->get_result()->fetch_assoc();
if(!$row){http_response_code(404);exit;}
echo json_encode(['charge_ah'=>(float)$row['charge_ah'],'capacity_ah'=>(float)$row['capacity_ah'],'on'=>(bool)$row['enabled']],JSON_THROW_ON_ERROR);
