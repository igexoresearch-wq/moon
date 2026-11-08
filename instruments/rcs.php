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
 #<?=htmlspecialchars($rcsUid)?> .rcs-level{display:flex;justify-content:space-between;margin-top:9px;color:#e1dcb5;font:12px monospace}
 </style>
 <div class="rcs-title">REACTION CONTROL SYSTEM</div>
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
 <div class="rcs-level"><span>FUEL: <b class="rcs-fuel">—</b> kg</span><span>OXIDIZER: <b class="rcs-oxidizer">—</b> kg</span></div>
</div>
<script>
(()=>{
 const panel=document.getElementById(<?=json_encode($rcsUid)?>);
 if(!panel)return;
 const craft=Number(panel.dataset.craft);
 let busy=false;
 async function levels(){
  try{const r=await fetch('rcs_status.php?craft='+craft,{cache:'no-store'});
   if(!r.ok)return;const v=await r.json();
   panel.querySelector('.rcs-fuel').textContent=Number(v.fuel_kg).toFixed(2);
   panel.querySelector('.rcs-oxidizer').textContent=Number(v.oxidizer_kg).toFixed(2);
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
