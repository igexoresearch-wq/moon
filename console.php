<?php
require_once __DIR__.'/common.php';
$console=moonConsole();
$observerId=moonObserver($console);
$stmt=$conn->prepare('SELECT file_name,pos_x,pos_y FROM console_instruments WHERE console_id=? ORDER BY id');
$stmt->bind_param('s',$console);$stmt->execute();$instruments=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stored=[];$rs=$conn->query('SELECT file_name,source_html,width_px,height_px FROM instrument_sources');while($row=$rs->fetch_assoc())$stored[$row['file_name']]=$row;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars(STATIONS[$console])?></title><style>
body{margin:0;background:#777;color:#eee;font-family:Arial,sans-serif}
.station{position:relative;min-height:1200px}
.station>header{padding:9px 15px;background:#555}
.instrument{position:absolute}
a{color:white}
</style></head><body><div class="station"><header><?=htmlspecialchars(STATIONS[$console])?> · <a href="index.php">Menu</a> · <a href="editor.php?console=<?=urlencode($console)?>">Edit layout</a></header>
<?php foreach($instruments as $instrument):$file=(preg_match('/^[a-z][a-z0-9_]*\\.php$/i',$instrument['file_name']) && is_file(__DIR__.'/instruments/'.$instrument['file_name'])?$instrument['file_name']:null);$custom=$stored[$instrument['file_name']]??null;if($file===null&&$custom===null)continue;?>
<div class="instrument" style="left:<?=(int)$instrument['pos_x']?>px;top:<?=(int)$instrument['pos_y']?>px">
<?php if($file!==null){include __DIR__.'/instruments/'.$file;}else{ ?>
<iframe class="pasted-gauge" title="<?=htmlspecialchars($instrument['file_name'])?>" sandbox="allow-scripts" referrerpolicy="no-referrer" width="<?=(int)$custom['width_px']?>" height="<?=(int)$custom['height_px']?>" style="border:0;background:#171717" srcdoc="<?=htmlspecialchars($custom['source_html'],ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8')?>"></iframe>
<?php } ?>
</div><?php endforeach;?></div><script>
const moonObserverId=<?=(int)$observerId?>;
async function broadcastTelemetry(){try{const r=await fetch('state.php?observer='+moonObserverId,{cache:'no-store'});if(!r.ok)return;const state=await r.json();const self=state.objects.find(o=>Number(o.id)===moonObserverId);if(!self)return;for(const frame of document.querySelectorAll('iframe.pasted-gauge'))frame.contentWindow?.postMessage({type:'moon-telemetry',self,objects:state.objects},'*');}catch(e){console.warn(e)}}
let moonTelemetryBusy=false;
const moonTelemetrySafely=async()=>{if(document.hidden||moonTelemetryBusy)return;moonTelemetryBusy=true;try{await broadcastTelemetry();}finally{moonTelemetryBusy=false;}};
setInterval(moonTelemetrySafely,5000);setTimeout(moonTelemetrySafely,500);
</script></body></html>
