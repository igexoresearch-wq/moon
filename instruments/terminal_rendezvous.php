<?php
$trCraft=in_array($console??'',['lmc','lmp'],true)?6:3;
$trId='terminal-'.bin2hex(random_bytes(5));
?>
<div id="<?=htmlspecialchars($trId,ENT_QUOTES)?>" data-craft="<?=$trCraft?>" class="terminal-panel">
<style>
#<?=htmlspecialchars($trId)?>{width:240px;box-sizing:border-box;padding:10px;border:5px ridge #44483f;background:#565a51;color:#eee9d6;font:12px Arial,sans-serif;text-align:center}
#<?=htmlspecialchars($trId)?> .tr-header{background:#323931;padding:7px;font:bold 12px Arial;letter-spacing:1px}
#<?=htmlspecialchars($trId)?> .tr-reading{margin:9px 0 3px;padding:8px 3px;border:4px inset #85887a;background:#0d1911;color:#c8e7bb;font:20px 'Courier New',monospace;font-variant-numeric:tabular-nums}
#<?=htmlspecialchars($trId)?> .tr-field{display:flex;justify-content:space-between;font:11px monospace;padding:6px 0;border-bottom:1px solid #878b81}
#<?=htmlspecialchars($trId)?> .tr-state{font:11px monospace;color:#f4dfa4;padding-top:8px}
</style>
<div class="tr-header">P36 / TARGET TRACK</div>
<div class="tr-reading" data-range>---.- m</div>
<div class="tr-field"><span>RELATIVE V</span><strong data-velocity>---.- M/S</strong></div>
<div class="tr-field"><span>FIXES</span><strong data-samples>0</strong></div>
<div class="tr-state" data-state>ACQUIRING</div>
</div>
<script>
(()=>{
 const el=document.getElementById(<?=json_encode($trId)?>);
 if(!el)return;
 const out=(key,text)=>el.querySelector('[data-'+key+']').textContent=text;
 let busy=false;
 async function refresh(){
  if(busy)return;busy=true;
  try{
   const r=await fetch('terminal_rendezvous_status.php?craft='+el.dataset.craft,{cache:'no-store'});
   const d=await r.json();
   if(!r.ok)throw Error(d.error||'TRACKER UNAVAILABLE');
   out('range',d.range_m===null?'---.- m':Number(d.range_m).toFixed(1)+' m');
   out('velocity',d.relative_speed_mps===null?'---.- M/S':Number(d.relative_speed_mps).toFixed(2)+' M/S');
   out('samples',String(d.samples));
   out('state',d.valid?'3D TARGET LOCK':String(d.status));
  }catch(e){out('state',e.message||'TRACKER OFFLINE');}
  finally{busy=false;}
 }
 refresh();
 const timer=setInterval(()=>{if(!document.hidden)refresh();},2000);
 window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
})();
</script>
