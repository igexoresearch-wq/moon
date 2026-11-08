<?php
// Independently positionable RCS hand-control panel for CM/LM Commander.
$rcsCraft=(($console??'')==='lmc')?6:3;
$rcsUid='rcs-'.bin2hex(random_bytes(5));
?>
<div id="<?=htmlspecialchars($rcsUid)?>" class="rcs-unit" data-craft="<?=$rcsCraft?>">
 <style>
 #<?=htmlspecialchars($rcsUid)?>{width:320px;box-sizing:border-box;padding:12px;background:#414443;border:5px solid #282b29;color:#e4e4df;font:12px Arial,sans-serif;user-select:none}
 #<?=htmlspecialchars($rcsUid)?> .rcs-title{text-align:center;font-weight:bold;letter-spacing:2px;padding:5px;background:#292d2b}
 #<?=htmlspecialchars($rcsUid)?> .rcs-sub{text-align:center;font-size:11px;margin:9px 0 4px}
 #<?=htmlspecialchars($rcsUid)?> .rcs-pad{display:grid;grid-template-columns:repeat(3,68px);grid-template-rows:repeat(3,40px);justify-content:center;gap:4px}
 #<?=htmlspecialchars($rcsUid)?> button{border:2px solid #191c1b;background:#9b9c95;color:#151716;font:bold 12px Arial;cursor:pointer;touch-action:manipulation}
 #<?=htmlspecialchars($rcsUid)?> button:active{background:#d4c56c}
 #<?=htmlspecialchars($rcsUid)?> .rcs-sep{height:1px;background:#72766f;margin:10px 0}
 #<?=htmlspecialchars($rcsUid)?> .rcs-pair{display:flex;gap:5px;justify-content:center}
 #<?=htmlspecialchars($rcsUid)?> .rcs-pair button{min-height:40px;min-width:106px}
 #<?=htmlspecialchars($rcsUid)?> .rcs-power{display:flex;align-items:center;justify-content:center;gap:6px;margin:10px 0}
 #<?=htmlspecialchars($rcsUid)?> .rcs-power button{min-width:46px;padding:7px}
 #<?=htmlspecialchars($rcsUid)?> .rcs-power button.selected{background:#d4c56c}
 #<?=htmlspecialchars($rcsUid)?> .rcs-gauges{display:flex;justify-content:space-evenly;gap:12px;margin-top:12px}
 #<?=htmlspecialchars($rcsUid)?> .rcs-gauge{width:112px;height:112px;position:relative}
 #<?=htmlspecialchars($rcsUid)?> .rcs-gauge svg{width:100%;height:100%;display:block}
 #<?=htmlspecialchars($rcsUid)?> .rcs-needle{transform-origin:60px 60px;transform:rotate(-120deg)}
 </style>
 <div class="rcs-title">REACTION CONTROL SYSTEM</div>
 <div class="rcs-power"><span>RCS POWER</span><button type="button" data-power="off">OFF</button><button type="button" data-power="on">ON</button></div>
 <div class="rcs-sub">ATTITUDE — PITCH / YAW</div>
 <div class="rcs-pad">
  <span></span><button data-axis="pitch" data-sign="1">PITCH +</button><span></span>
  <button data-axis="yaw" data-sign="-1">YAW −</button><span></span><button data-axis="yaw" data-sign="1">YAW +</button>
  <span></span><button data-axis="pitch" data-sign="-1">PITCH −</button><span></span>
 </div>
 <div class="rcs-sub">ATTITUDE — ROLL</div>
 <div class="rcs-pair"><button data-axis="roll" data-sign="-1">ROLL −</button><button data-axis="roll" data-sign="1">ROLL +</button></div>
 <div class="rcs-sep"></div>
 <div class="rcs-sub">TRANSLATION — FORWARD / AFT / LEFT / RIGHT</div>
 <div class="rcs-pad">
  <span></span><button data-axis="forward" data-sign="1">FWD</button><span></span>
  <button data-axis="lateral" data-sign="-1">LEFT</button><span></span><button data-axis="lateral" data-sign="1">RIGHT</button>
  <span></span><button data-axis="forward" data-sign="-1">AFT</button><span></span>
 </div>
 <div class="rcs-sub">TRANSLATION — UP / DOWN</div>
 <div class="rcs-pair"><button data-axis="vertical" data-sign="1">UP</button><button data-axis="vertical" data-sign="-1">DOWN</button></div>
 <div class="rcs-gauges">
  <div class="rcs-gauge" role="img" aria-label="FUEL quantity analog gauge">
   <svg viewBox="0 0 120 120" aria-hidden="true">
    <circle cx="60" cy="60" r="55" fill="#1d2221" stroke="#999b90" stroke-width="4"/>
    <circle cx="60" cy="60" r="49" fill="none" stroke="#111" stroke-width="2"/>
    <?php for($i=0;$i<=8;$i++):
      $a=deg2rad(-120+($i*30));
      $x1=60+($i%2===0?39:43)*sin($a);$y1=60-($i%2===0?39:43)*cos($a);
      $x2=60+49*sin($a);$y2=60-49*cos($a);
    ?>
    <line x1="<?=round($x1,2)?>" y1="<?=round($y1,2)?>" x2="<?=round($x2,2)?>" y2="<?=round($y2,2)?>" stroke="#dddcc9" stroke-width="<?=$i%2===0?2.5:1?>"/>
    <?php endfor; ?>
    <text x="60" y="34" text-anchor="middle" fill="#e5e5d9" font-family="Arial" font-size="12" font-weight="bold">FUEL</text>
    <text x="29" y="95" text-anchor="middle" fill="#d7d7c8" font-family="Arial" font-size="9">E</text>
    <text x="91" y="95" text-anchor="middle" fill="#d7d7c8" font-family="Arial" font-size="9">F</text>
    <g class="rcs-needle rcs-fuel-needle"><path d="M 60 17 L 57.5 62 L 62.5 62 Z" fill="#e6c99a" stroke="#9b8060" stroke-width="0.7"/></g>
    <circle cx="60" cy="60" r="5" fill="#999b94" stroke="#141616" stroke-width="2"/>
   </svg>
  </div>
  <div class="rcs-gauge" role="img" aria-label="OX quantity analog gauge">
   <svg viewBox="0 0 120 120" aria-hidden="true">
    <circle cx="60" cy="60" r="55" fill="#1d2221" stroke="#999b90" stroke-width="4"/>
    <circle cx="60" cy="60" r="49" fill="none" stroke="#111" stroke-width="2"/>
    <?php for($i=0;$i<=8;$i++):
      $a=deg2rad(-120+($i*30));
      $x1=60+($i%2===0?39:43)*sin($a);$y1=60-($i%2===0?39:43)*cos($a);
      $x2=60+49*sin($a);$y2=60-49*cos($a);
    ?>
    <line x1="<?=round($x1,2)?>" y1="<?=round($y1,2)?>" x2="<?=round($x2,2)?>" y2="<?=round($y2,2)?>" stroke="#dddcc9" stroke-width="<?=$i%2===0?2.5:1?>"/>
    <?php endfor; ?>
    <text x="60" y="34" text-anchor="middle" fill="#e5e5d9" font-family="Arial" font-size="12" font-weight="bold">OX</text>
    <text x="29" y="95" text-anchor="middle" fill="#d7d7c8" font-family="Arial" font-size="9">E</text>
    <text x="91" y="95" text-anchor="middle" fill="#d7d7c8" font-family="Arial" font-size="9">F</text>
    <g class="rcs-needle rcs-oxidizer-needle"><path d="M 60 17 L 57.5 62 L 62.5 62 Z" fill="#e6c99a" stroke="#9b8060" stroke-width="0.7"/></g>
    <circle cx="60" cy="60" r="5" fill="#999b94" stroke="#141616" stroke-width="2"/>
   </svg>
  </div>
 </div>
</div>
<script>
(()=>{
 const panel=document.getElementById(<?=json_encode($rcsUid)?>);
 if(!panel)return;
 const craft=Number(panel.dataset.craft);
 let busy=false;
 async function refreshPower(){try{const r=await fetch("component_power.php?system=rcs&craft="+craft,{cache:"no-store"});if(!r.ok)return;const v=await r.json();for(const b of panel.querySelectorAll("[data-power]"))b.classList.toggle("selected",(b.dataset.power==="on")===v.on);}catch(e){}}
 panel.querySelectorAll("[data-power]").forEach(b=>b.addEventListener("click",async()=>{try{await fetch("component_power.php?system=rcs&craft="+craft,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({system:"rcs",craft,command:b.dataset.power})});await refreshPower();}catch(e){}}));
 refreshPower();
 async function levels(){
  try{const r=await fetch('rcs_status.php?craft='+craft,{cache:'no-store'});
   if(!r.ok)return;const v=await r.json();
   for(const [name,capacity] of [['fuel','fuel_capacity_kg'],['oxidizer','oxidizer_capacity_kg']]){
    const full=Number(v[capacity]),remaining=Number(v[name+'_kg']);
    if(!Number.isFinite(full)||full<=0||!Number.isFinite(remaining))continue;
    const ratio=Math.max(0,Math.min(1,remaining/full));
    const needle=panel.querySelector('.rcs-'+name+'-needle');
    needle.style.transform='rotate('+(-120+ratio*240)+'deg)';
   }
  }catch(e){}
 }
 panel.querySelectorAll('button[data-axis]').forEach(button=>button.addEventListener('click',async()=>{
  if(busy)return;busy=true;
  try{
   const resp=await fetch('rcs_command.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({craft,axis:button.dataset.axis,sign:Number(button.dataset.sign)})});
   if(!resp.ok)throw Error('RCS command request failed');
   await levels();
  }catch(e){console.warn('RCS command:',e);}finally{busy=false;}
 }));
 levels();const timer=setInterval(levels,3000);
 window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
})();
</script>
