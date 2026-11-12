<?php
// Compact pilot-only delta-V accumulator; reads sampled G, not true state.
$dvCraft=in_array($console??'',['lmp'],true)?6:3;
$dvId='dv-'.bin2hex(random_bytes(5));
?>
<div id="<?=htmlspecialchars($dvId,ENT_QUOTES)?>" class="moon-dv" data-craft="<?=$dvCraft?>">
<style>
#<?=htmlspecialchars($dvId)?>{width:240px;background:#565a52;border:5px ridge #292e29;color:#e8e5d5;box-sizing:border-box;padding:10px;font:12px Arial,sans-serif;text-align:center}
#<?=htmlspecialchars($dvId)?> .dv-title{background:#323b34;letter-spacing:1.2px;font:bold 12px Arial;padding:7px}
#<?=htmlspecialchars($dvId)?> .dv-screen{background:#111a13;color:#c2dfb9;border:4px inset #797f71;font:24px 'Courier New',monospace;padding:12px 4px;margin:9px 0 6px;letter-spacing:.5px;font-variant-numeric:tabular-nums}
#<?=htmlspecialchars($dvId)?> .dv-label{font:11px monospace;letter-spacing:1px;margin-top:5px}
#<?=htmlspecialchars($dvId)?> .dv-g{font:12px monospace;margin:8px 0;color:#d8e3cf}
#<?=htmlspecialchars($dvId)?> button{background:#a6aaa1;border:2px outset #d4d5c7;color:#222;font:bold 12px Arial;padding:7px 18px;cursor:pointer}
#<?=htmlspecialchars($dvId)?> .dv-state{font:10px monospace;min-height:28px;margin-top:7px;color:#e3d6b4}
</style>
<div class="dv-title">DELTA-V / ACCUMULATOR</div>
<div class="dv-label">CUMULATIVE SINCE MANUAL RESET</div>
<div class="dv-screen"><span data-reading>+0000.000</span></div>
<div class="dv-label">METERS / SECOND</div>
<div class="dv-g">ACCEL <span data-g>---.---</span> G</div>
<div class="dv-g" data-burn-progress>DSKY BURN: NONE</div>
<button type="button" data-reset>RESET ZERO</button>
<div class="dv-state" data-status>WAITING FOR INSTRUMENT</div>
</div>
<script>
(()=>{
 const panel=document.getElementById(<?=json_encode($dvId)?>);
 if(!panel)return;
 const craft=Number(panel.dataset.craft),url='delta_v_status.php?craft='+craft;
 let token='',busy=false;
 const reading=panel.querySelector('[data-reading]'),g=panel.querySelector('[data-g]'),status=panel.querySelector('[data-status]'),burn=panel.querySelector('[data-burn-progress]');
 async function refresh(){
  if(busy)return;
  try{
   const r=await fetch(url,{cache:'no-store'});
   const d=await r.json();
   if(!r.ok)throw Error(d.error||'INSTRUMENT OFFLINE');
   token=d.csrf||token;
   const v=Number(d.delta_v_mps);
   reading.textContent=(v<0?'-':'+')+Math.abs(v).toFixed(3);
   g.textContent=d.valid_sample?Number(d.axial_g).toFixed(3):'---.---';
   status.textContent=d.status||'WAITING';
   const active=d.active_dsky_burn;
   burn.textContent=active?'P'+active.program+' BURN '+Number(active.achieved_mps).toFixed(1)+' / '+Number(active.target_mps).toFixed(1)+' M/S':'DSKY BURN: NONE';
  }catch(e){status.textContent=e.message||'CONNECTION FAILURE';}
 }
 panel.querySelector('[data-reset]').addEventListener('click',async()=>{
  if(busy||!token)return;busy=true;
  try{
   const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action:'reset',csrf:token})});
   const d=await r.json();if(!r.ok)throw Error(d.error||'RESET FAILED');
   reading.textContent='+0.000';status.textContent='RESET';
  }catch(e){status.textContent=e.message||'RESET FAILED';}
  finally{busy=false;await refresh();}
 });
 refresh();
 const timer=setInterval(()=>{if(!document.hidden)refresh();},2000);
 window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
})();
</script>
