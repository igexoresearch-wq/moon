<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$craft=(int)($_GET['craft']??0);
if(!in_array($craft,[3,6],true)){http_response_code(400);exit;}
$q=$conn->prepare("SELECT sample_tick,sampled_at,g_magnitude,g_forward,valid FROM g_meter_samples WHERE craft_id=?");
$q->bind_param('i',$craft);$q->execute();$row=$q->get_result()->fetch_assoc();
if(!$row){http_response_code(404);echo json_encode(['error'=>'G meter not installed']);exit;}
echo json_encode([
 'valid'=>(bool)$row['valid'],
 'g_magnitude'=>$row['valid']?(float)$row['g_magnitude']:null,
 'g_forward'=>$row['valid']?(float)$row['g_forward']:null,
 'sample_tick'=>$row['sample_tick']===null?null:(int)$row['sample_tick'],
 'sampled_at'=>$row['sampled_at']
],JSON_THROW_ON_ERROR);
