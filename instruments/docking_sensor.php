<?php
// Docking probe is independent of the radar; either cockpit can inspect the ports.
$dsCraft=in_array($console??'',['lmc','lmp'],true)?6:3;
$dsId='docks-'.bin2hex(random_bytes(5));
?>
<div id="<?=htmlspecialchars($dsId,ENT_QUOTES)?>" class="moon-docking" data-craft="<?=$dsCraft?>">
<style>
#<?=htmlspecialchars($dsId)?>{width:248px;box-sizing:border-box;background:#565a52;border:5px ridge #292e29;color:#e6e5d8;font:11px Arial,sans-serif;padding:10px;text-align:center}
#<?=htmlspecialchars($dsId)?> .ds-title{background:#323b34;letter-spacing:1.4px;font:bold 12px Arial;padding:7px}
#<?=htmlspecialchars($dsId)?> .ds-display{background:#111a13;color:#c2dfb9;border:4px inset #797f71;font:23px 'Courier New',monospace;padding:8px 3px;margin:7px 0;font-variant-numeric:tabular-nums}
#<?=htmlspecialchars($dsId)?> .ds-row{display:flex;justify-content:space-between;gap:6px;padding:4px 2px;border-bottom:1px solid #777d74;font:11px monospace}
#<?=htmlspecialchars($dsId)?> .ds-status{color:#f4dfa4;font:bold 11px monospace;padding:7px 2px;min-height:34px}
#<?=htmlspecialchars($dsId)?> .ds-note{font:10px monospace;color:#d6d7c8;line-height:1.4}
</style>
<div class="ds-title">DOCKING / PROBE</div>
<div class="ds-note">CSM ↔ LM / FORWARD PORTS</div>
<div class="ds-display" data-gap>---.- m</div>
<div class="ds-row"><span>REL SPEED</span><b data-speed>---.- m/s</b></div>
<div class="ds-row"><span>LOS CLOSURE</span><b data-closing>---.- m/s</b></div>
<div class="ds-row"><span>CM PORT AXIS</span><b data-cm>---</b></div>
<div class="ds-row"><span>LM PORT AXIS</span><b data-lm>---</b></div>
<div class="ds-row"><span>LAST CONTACT</span><b data-contact>NONE</b></div>
<div class="ds-row"><span>CSM / LM DAMAGE</span><b data-damage>0 / 0</b></div>
<div class="ds-status" data-status>PROBE INITIALIZING</div>
<div class="ds-note">DOCK: ≤1 M PORT GAP / ≤1 M/S RELATIVE SPEED / AXES ALIGNED</div>
</div>
<script>
(()=>{
 const panel=document.getElementById(<?=json_encode($dsId)?>);if(!panel)return;
 const set=(name,val)=>{panel.querySelector('[data-'+name+']').textContent=val;};
 const craft=panel.dataset.craft;
 let busy=false;
 async function refresh(){
  if(busy)return;busy=true;
  try{
   const r=await fetch('docking_sensor_status.php?craft='+craft,{cache:'no-store'});
   const d=await r.json();if(!r.ok)throw Error(d.error||'SENSOR UNAVAILABLE');
   set('status',d.docked?'DOCKED / LATCHED':(d.condition||d.status));
   set('contact',d.last_event||'NONE');
   set('damage',String(d.last_damage_cm||0)+' / '+String(d.last_damage_lm||0));
   if(d.available){
    set('gap',Number(d.port_gap_m).toFixed(2)+' m');
    set('speed',Number(d.relative_speed_mps).toFixed(2)+' m/s');
    set('closing',Number(d.closing_mps).toFixed(2)+' m/s');
    set('cm',Number(d.cm_facing)>=Math.cos(Math.PI/12)?'ALIGNED':'OFF AXIS');
    set('lm',Number(d.lm_facing)>=Math.cos(Math.PI/12)?'ALIGNED':'OFF AXIS');
   }else{
    for(const field of ['gap','speed','closing','cm','lm'])set(field,'---');
   }
  }catch(e){set('status',e.message||'PROBE OFFLINE');}
  finally{busy=false;}
 }
 refresh();const timer=setInterval(()=>{if(!document.hidden)refresh();},2000);
 window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
})();
</script>
