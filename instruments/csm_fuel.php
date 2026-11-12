<?php
// CSM service propulsion fuel gauge; the command module itself has no SPS tank.
$cfId='csmfuel-'.bin2hex(random_bytes(5));
?>
<div id="<?=htmlspecialchars($cfId,ENT_QUOTES)?>" class="csm-fuel-panel" role="group" aria-label="CSM service propulsion fuel">
<style>
#<?=htmlspecialchars($cfId)?>{box-sizing:border-box;width:250px;padding:10px;border:5px ridge #44483f;background:#565a51;color:#eee9d6;font:12px Arial,sans-serif;text-align:center}
#<?=htmlspecialchars($cfId)?> .cf-heading{background:#323931;padding:7px;font:bold 12px Arial,sans-serif;letter-spacing:1.3px}
#<?=htmlspecialchars($cfId)?> .cf-reading{margin:9px 0 3px;padding:8px 3px;border:4px inset #85887a;background:#0d1911;color:#c8e7bb;font:24px 'Courier New',monospace;font-variant-numeric:tabular-nums}
#<?=htmlspecialchars($cfId)?> .cf-percent{font:bold 15px 'Courier New',monospace;margin:5px 0}
#<?=htmlspecialchars($cfId)?> .cf-meter{height:20px;margin:8px 0;background:#1b211b;border:3px inset #767a6d;overflow:hidden}
#<?=htmlspecialchars($cfId)?> .cf-fill{height:100%;width:0;background:#a8c4a2;transition:width .25s linear}
#<?=htmlspecialchars($cfId)?> .cf-sub{font:10px 'Courier New',monospace;color:#e5e0cc}
#<?=htmlspecialchars($cfId)?> .cf-status{font:10px monospace;margin-top:7px;min-height:12px;color:#dfc9a2}
</style>
<div class="cf-heading">SERVICE MODULE / SPS FUEL</div>
<div class="cf-reading" data-remaining>----- kg</div>
<div class="cf-percent" data-percent>---.-%</div>
<div class="cf-meter" role="meter" aria-label="SPS propellant remaining" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="cf-fill" data-fill></div></div>
<div class="cf-sub">TOTAL PROPELLANT (FUEL + OXIDIZER)</div>
<div class="cf-sub" data-capacity>CAPACITY ----- kg</div>
<div class="cf-status" data-status>READING TANK</div>
</div>
<script>
(()=>{
 const panel=document.getElementById(<?=json_encode($cfId)?>);
 if(!panel)return;
 const read=panel.querySelector('[data-remaining]');
 const percent=panel.querySelector('[data-percent]');
 const fill=panel.querySelector('[data-fill]');
 const capacity=panel.querySelector('[data-capacity]');
 const status=panel.querySelector('[data-status]');
 const meter=panel.querySelector('[role=meter]');
 let busy=false;
 async function refresh(){
  if(busy)return;
  busy=true;
  try{
   const response=await fetch('csm_fuel_status.php',{cache:'no-store',credentials:'same-origin'});
   const data=await response.json();
   if(!response.ok)throw Error(data.error||'FUEL SENSOR UNAVAILABLE');
   const remaining=Number(data.remaining_kg),maximum=Number(data.capacity_kg),pct=Number(data.remaining_pct);
   if(!Number.isFinite(remaining)||!Number.isFinite(maximum)||maximum<=0||!Number.isFinite(pct))
    throw Error('INVALID TANK DATA');
   read.textContent=remaining.toLocaleString('en-US',{maximumFractionDigits:1,minimumFractionDigits:1})+' kg';
   percent.textContent=pct.toFixed(1)+'%';
   fill.style.width=Math.max(0,Math.min(100,pct))+'%';
   meter.setAttribute('aria-valuenow',String(Math.max(0,Math.min(100,pct))));
   capacity.textContent='CAPACITY '+maximum.toLocaleString('en-US',{maximumFractionDigits:0})+' kg';
   status.textContent='SPS PROPELLANT';
  }catch(e){status.textContent=e instanceof Error?e.message:'FUEL DATA UNAVAILABLE';}
  finally{busy=false;}
 }
 refresh();
 const timer=setInterval(()=>{if(!document.hidden)refresh();},5000);
 window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
})();
</script>
