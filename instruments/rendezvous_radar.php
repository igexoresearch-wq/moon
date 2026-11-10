<?php
$rrCraft=in_array($console??'',['lmc','lmp'],true)?6:3;
$rrId='radar-'.bin2hex(random_bytes(5));
?>
<div id="<?=htmlspecialchars($rrId,ENT_QUOTES)?>" data-craft="<?=$rrCraft?>" class="rr-panel">
<style>
#<?=htmlspecialchars($rrId)?>{width:310px;box-sizing:border-box;padding:12px;background:#535850;border:5px ridge #242724;color:#e4e1d1;font:12px Arial,sans-serif;user-select:none}
#<?=htmlspecialchars($rrId)?> .rr-head{text-align:center;letter-spacing:2px;background:#282e2a;padding:7px;font-weight:bold}
#<?=htmlspecialchars($rrId)?> .rr-sub{text-align:center;margin:9px auto}
#<?=htmlspecialchars($rrId)?> .rr-track{height:184px;margin:0 auto;position:relative;width:112px;border:4px inset #a0a49b;background:#202522;overflow:hidden}
#<?=htmlspecialchars($rrId)?> .rr-scale{position:absolute;inset:10px 0 10px 0;border-left:2px solid #d0d4ca;margin-left:40px}
#<?=htmlspecialchars($rrId)?> .rr-scale span{position:absolute;left:7px;font:10px monospace;transform:translateY(-50%)}
#<?=htmlspecialchars($rrId)?> .rr-pointer{position:absolute;top:95%;left:0;width:72px;height:3px;background:#dbc68f;box-shadow:0 0 4px #b39c6b}
#<?=htmlspecialchars($rrId)?> .rr-data{display:flex;justify-content:center;gap:10px;margin:12px 0;font:bold 12px monospace}
#<?=htmlspecialchars($rrId)?> .rr-data div{background:#242924;padding:5px;text-align:center}
#<?=htmlspecialchars($rrId)?> .rr-switch{display:flex;justify-content:center;align-items:center;gap:5px;margin:7px 0}
#<?=htmlspecialchars($rrId)?> .rr-switch strong{width:110px}
#<?=htmlspecialchars($rrId)?> button{border:2px solid #202421;background:#969b92;color:#111;padding:6px;min-width:48px;font-weight:bold;cursor:pointer}
#<?=htmlspecialchars($rrId)?> button.selected{background:#d2bc7b}
#<?=htmlspecialchars($rrId)?> .rr-status{text-align:center;letter-spacing:2px;margin:8px 0;color:#d1c99e}
</style>
<div class="rr-head">RENDEZVOUS RADAR</div>
<div class="rr-sub">RANGE — KILOMETERS · MAX 740</div>
<div class="rr-track"><div class="rr-scale">
<?php for($i=0;$i<=8;$i++): ?><span style="top:<?=$i*12.5?>%"><?=number_format(740-$i*92.5,1)?></span><?php endfor; ?>
</div><div class="rr-pointer"></div></div>
<div class="rr-data"><div>OFF AXIS<br><span data-angle>—</span>°</div><div>RANGE<br><span data-range>—</span> km</div></div>
<div class="rr-status" data-lock>NO RETURN</div>
<?php foreach(['radar'=>'RADAR','responder'=>'RESPONDER'] as $key=>$label): ?>
<div class="rr-switch"><strong><?=$label?></strong><button type="button" data-unit="<?=$key?>" data-command="off">OFF</button><button type="button" data-unit="<?=$key?>" data-command="on">ON</button></div>
<?php endforeach; ?>
</div>
<script>
(()=>{
 const root=document.getElementById(<?=json_encode($rrId)?>),craft=Number(root.dataset.craft);
 async function refresh(){
  try{
   const r=await fetch('rendezvous_radar_status.php?craft='+craft,{cache:'no-store'});if(!r.ok)return;
   const v=await r.json(),reg=v.registers||{},range=reg.RENDEZVOUS_RANGE_M,angle=reg.RENDEZVOUS_OFF_AXIS_DEG;
   const valid=Boolean(range?.valid&&angle?.valid);
   root.querySelector('[data-lock]').textContent=valid?'RANGE RETURN':'NO RETURN';
   root.querySelector('[data-range]').textContent=valid?(range.value/1000).toFixed(1):'—';
   root.querySelector('[data-angle]').textContent=valid?angle.value.toFixed(1):'—';
   root.querySelector('.rr-pointer').style.display=valid?'block':'none';
   if(valid)root.querySelector('.rr-pointer').style.top=(100-Math.min(100,range.value/740000*100))+'%';
   root.querySelectorAll('button[data-unit]').forEach(b=>b.classList.toggle('selected',(b.dataset.command==='on')===Boolean(v.power[b.dataset.unit])));
  }catch(e){}
 }
 root.querySelectorAll('button[data-unit]').forEach(button=>button.addEventListener('click',async()=>{
  try{await fetch('rendezvous_radar_status.php?craft='+craft,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({unit:button.dataset.unit,command:button.dataset.command})});}catch(e){}
  await refresh();
 }));
 root.querySelector('.rr-pointer').style.display='none';
 refresh();let polling=false;const timer=setInterval(async()=>{if(document.hidden||polling)return;polling=true;try{await refresh();}finally{polling=false;}},5000);
 window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
})();
</script>
