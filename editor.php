<?php
require_once __DIR__.'/common.php';
$console=moonConsole();
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
if(empty($_SESSION['moon_v2_csrf']))$_SESSION['moon_v2_csrf']=bin2hex(random_bytes(24));
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($_SESSION['moon_v2_csrf'],(string)($_POST['csrf']??''))){http_response_code(403);exit('Invalid form token');}
 $name=(string)($_POST['file_name']??'');
 if(!preg_match('/\A[a-z][a-z0-9_]*\.(?:php|html)\z/i',$name)){http_response_code(400);exit('Invalid instrument name');}
 $action=(string)($_POST['action']??'');
 if($action==='paste'){
  if(!str_ends_with($name,'.html')){http_response_code(400);exit('Pasted gauges use .html names');}
  $source=(string)($_POST['source_html']??'');
  if(strlen($source)>100000||trim($source)===''){http_response_code(400);exit('Paste between 1 and 100,000 bytes');}
  $w=max(80,min(1000,(int)($_POST['width']??320)));$h=max(80,min(1000,(int)($_POST['height']??220)));
  $stmt=$conn->prepare('INSERT INTO instrument_sources(file_name,source_html,width_px,height_px) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE source_html=VALUES(source_html),width_px=VALUES(width_px),height_px=VALUES(height_px)');
  $stmt->bind_param('ssii',$name,$source,$w,$h);$stmt->execute();
  $x=(int)($_POST['x']??20);$y=(int)($_POST['y']??70);
  $stmt=$conn->prepare('INSERT INTO console_instruments(console_id,file_name,pos_x,pos_y) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE pos_x=VALUES(pos_x),pos_y=VALUES(pos_y)');
  $stmt->bind_param('ssii',$console,$name,$x,$y);$stmt->execute();
 }elseif($action==='add'){
  $builtIn=(preg_match('/^[a-z][a-z0-9_]*\\.php$/i',$name) && is_file(__DIR__.'/instruments/'.$name));
  $stored=false;if(str_ends_with($name,'.html')){$check=$conn->prepare('SELECT 1 FROM instrument_sources WHERE file_name=?');$check->bind_param('s',$name);$check->execute();$stored=(bool)$check->get_result()->fetch_row();}
  if(!$builtIn&&!$stored){http_response_code(400);exit('File not found');}
  $stmt=$conn->prepare('INSERT INTO console_instruments(console_id,file_name,pos_x,pos_y) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE pos_x=VALUES(pos_x),pos_y=VALUES(pos_y)');
  $x=(int)($_POST['x']??20);$y=(int)($_POST['y']??70);
  $stmt->bind_param('ssii',$console,$name,$x,$y);$stmt->execute();
 }elseif(in_array($action,['up','down','left','right','remove'],true)){
  $stmt=$conn->prepare('SELECT id,pos_x,pos_y FROM console_instruments WHERE console_id=? AND file_name=?');
  $stmt->bind_param('ss',$console,$name);$stmt->execute();$row=$stmt->get_result()->fetch_assoc();
  if($row){
   if($action==='remove'){$stmt=$conn->prepare('DELETE FROM console_instruments WHERE id=?');$stmt->bind_param('i',$row['id']);$stmt->execute();}
   else{$dx=['left'=>-10,'right'=>10,'up'=>0,'down'=>0][$action];$dy=['left'=>0,'right'=>0,'up'=>-10,'down'=>10][$action];
    $x=(int)$row['pos_x']+$dx;$y=(int)$row['pos_y']+$dy;$id=(int)$row['id'];
    $stmt=$conn->prepare('UPDATE console_instruments SET pos_x=?,pos_y=? WHERE id=?');$stmt->bind_param('iii',$x,$y,$id);$stmt->execute();}
  }
 }
 header('Location: editor.php?console='.urlencode($console));exit;
}
$stmt=$conn->prepare('SELECT file_name,pos_x,pos_y FROM console_instruments WHERE console_id=? ORDER BY id');
$stmt->bind_param('s',$console);$stmt->execute();$items=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?><!doctype html><html><head><meta charset="utf-8"><title>Layout editor</title><style>body{background:#777;color:white;font:16px Arial}button{padding:7px;margin:3px}input{padding:7px}a{color:white}</style></head><body>
<h2><?=htmlspecialchars(STATIONS[$console])?> instrument layout</h2><p><a href="console.php?console=<?=urlencode($console)?>">Back to console</a></p>
<?php foreach($items as $item):?><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['moon_v2_csrf'])?>"><input type="hidden" name="file_name" value="<?=htmlspecialchars($item['file_name'])?>">
<?=htmlspecialchars($item['file_name'])?> (<?=(int)$item['pos_x']?>, <?=(int)$item['pos_y']?>)
<?php foreach(['up'=>'↑','down'=>'↓','left'=>'←','right'=>'→','remove'=>'Remove'] as $action=>$label):?><button name="action" value="<?=$action?>"><?=$label?></button><?php endforeach;?></form><?php endforeach;?>
<h3>Add existing instrument</h3><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['moon_v2_csrf'])?>"><input name="file_name" placeholder="window.php or gauge.html" required><input name="x" type="number" value="20"><input name="y" type="number" value="70"><button name="action" value="add">Add</button></form>
<p>For a new instrument, create a file in <code>v2/instruments/</code> then add its filename here. Existing instruments can be reused on any console.</p>
<h3>Paste a new reusable instrument</h3>
<p>Paste HTML, CSS, and JavaScript. The instrument runs in an isolated frame; it cannot run PHP.</p>
<form method="post">
<input type="hidden" name="csrf" value="<?=htmlspecialchars($_SESSION['moon_v2_csrf'])?>">
<p><input name="file_name" placeholder="attitude.html" pattern="[a-zA-Z][a-zA-Z0-9_]*\\.html" required>
Width <input name="width" type="number" value="320" min="80" max="1000">
Height <input name="height" type="number" value="220" min="80" max="1000"></p>
<p>X <input name="x" type="number" value="20"> Y <input name="y" type="number" value="70"></p>
<textarea name="source_html" rows="14" cols="90" required placeholder="<h2>Pitch: <span id='pitch'></span></h2>">&lt;style&gt;
body{margin:0;background:#111;color:#b8ffce;font:18px monospace;padding:15px}
.label{color:#aaa} .readout{font-size:26px}
&lt;/style&gt;
&lt;h3&gt;ATTITUDE&lt;/h3&gt;
&lt;p&gt;<span class="label">PITCH</span> <span id="p" class="readout">--</span>°&lt;/p&gt;
&lt;p&gt;<span class="label">ROLL</span> <span id="r" class="readout">--</span>°&lt;/p&gt;
&lt;p&gt;<span class="label">YAW</span> <span id="y" class="readout">--</span>°&lt;/p&gt;
&lt;script&gt;
addEventListener('message',e=&gt;{
 if(e.data?.type!=='moon-telemetry')return;
 const o=e.data.self;
 for(const [key,value] of [['p',o.pitch],['r',o.roll],['y',o.yaw]])
  document.getElementById(key).textContent=Number(value).toFixed(2);
});
&lt;/script&gt;</textarea><br>
<button name="action" value="paste">Save and add instrument</button></form>
<p>Pasted gauges receive a <code>moon-telemetry</code> message every second: <code>event.data.self.pitch</code>, <code>roll</code>, <code>yaw</code>, and rates. A gauge can be placed on either vehicle's console.</p>
</body></html>
