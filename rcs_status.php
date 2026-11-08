<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$craft=(int)($_GET['craft']??0);
if(!in_array($craft,[3,6],true)){http_response_code(400);exit;}
$system=$craft===3?4:6;
$q=$conn->prepare('SELECT fuel_kg,oxidizer_kg FROM rcs WHERE craft_id=?');
$q->bind_param('i',$system);$q->execute();
$row=$q->get_result()->fetch_assoc();
if(!$row){http_response_code(404);echo json_encode(['error'=>'RCS not installed']);exit;}
echo json_encode(['fuel_kg'=>(float)$row['fuel_kg'],'oxidizer_kg'=>(float)$row['oxidizer_kg']],JSON_THROW_ON_ERROR);
