<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
require_once __DIR__.'/fido_pages/_shared.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$key=(string)($_GET['display']??'summary');
if(!preg_match('/\A[a-z][a-z0-9_]*\z/i',$key)){http_response_code(400);exit('Invalid display');}
$q=$conn->prepare('SELECT page_file FROM fido_displays WHERE display_key=? AND enabled=1 LIMIT 1');
$q->bind_param('s',$key);$q->execute();$entry=$q->get_result()->fetch_assoc();
if(!$entry){http_response_code(404);exit('Display unavailable');}
$file=(string)$entry['page_file'];
if(!preg_match('/\A[a-z][a-z0-9_]*\.php\z/i',$file)||str_starts_with($file,'_')||!is_file(__DIR__.'/fido_pages/'.$file)){http_response_code(404);exit('Display module unavailable');}
$objects=[];
$result=$conn->query('SELECT id,name,active,dry_mass,radius_length,x,y,z,delta_x,delta_y,delta_z FROM objects WHERE id IN (1,2,3,6)');
while($row=$result->fetch_assoc())$objects[(int)$row['id']]=$row;
?><!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
<meta http-equiv="refresh" content="5">
<style>
*{box-sizing:border-box}body{width:1000px;height:800px;overflow:auto;margin:0;padding:24px 28px;background:#111b20;color:#d0dfdf;font:17px 'Courier New',monospace}
h2{color:#e3f2e8;border-bottom:1px solid #647477;padding-bottom:10px;letter-spacing:2px;font-size:19px;margin:0 0 20px}
h3{color:#a8d3e1;font-size:15px;letter-spacing:1px;margin:0 0 11px;border-bottom:1px solid #32516a;padding-bottom:5px}
.fd-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:22px 35px}
section{padding:12px 0;background:transparent;border:0;border-bottom:1px solid #3c4a50}
.fd-row{display:flex;justify-content:space-between;gap:12px;margin-bottom:9px;font-size:14px;line-height:1.3}
.fd-row span{color:#a6baba}.fd-row b{font-weight:normal;color:#eff2df;text-align:right;overflow-wrap:anywhere}
.fd-note{margin:20px 0;font-size:14px;color:#96b6c5;line-height:1.6}
.fd-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:12px}
.fd-table th,.fd-table td{padding:11px 4px;text-align:right;border-bottom:1px solid #34516a;overflow-wrap:anywhere}
.fd-table th:first-child,.fd-table td:first-child{text-align:left;width:22%}
.fd-table th{color:#9cb9c6}p{line-height:1.5}
</style></head><body>
<?php if(!isset($objects[1],$objects[2])): ?>
<h2>RTCC / DATA UNAVAILABLE</h2><p>EARTH OR MOON RECORD MISSING</p>
<?php else: include __DIR__.'/fido_pages/'.$file; endif;?>
</body></html>
