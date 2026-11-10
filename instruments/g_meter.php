<?php
// Compact CM/LM axial G indicator; values come only from stored velocity samples.
$gCraft=in_array($console??'',['lmc','lmp'],true)?6:3;
$gId='gm-'.bin2hex(random_bytes(5));
?>
<div id="<?=htmlspecialchars($gId,ENT_QUOTES)?>" class="gmeter-panel" data-craft="<?=$gCraft?>">
<style>
#<?=htmlspecialchars($gId)?>{width:184px;padding:9px;box-sizing:border-box;background:#55594f;border:4px ridge #272c28;color:#e7e4d6;font:11px Arial,sans-serif;text-align:center;user-select:none}
#<?=htmlspecialchars($gId)?> .gm-title{font-size:12px;letter-spacing:1.5px;font-weight:bold;background:#303430;padding:5px}
#<?=htmlspecialchars($gId)?> svg{width:152px;height:138px;display:block;margin:5px auto 0}
#<?=htmlspecialchars($gId)?> .gm-needle{transform-origin:80px 80px;transform:rotate(-73.333deg)}
#<?=htmlspecialchars($gId)?> .gm-bottom{font:11px monospace;margin:2px 0 6px}
#<?=htmlspecialchars($gId)?> .gm-buttons{display:flex;align-items:center;justify-content:center;gap:4px}
#<?=htmlspecialchars($gId)?> .gm-buttons button{background:#a1a398;border:2px solid #212421;padding:6px 8px;font-size:10px;font-weight:bold;cursor:pointer}
#<?=htmlspecialchars($gId)?> .gm-buttons button.selected{background:#d6c789}
#<?=htmlspecialchars($gId)?> .gm-indicator{font:10px monospace;opacity:.8;margin-top:5px}
</style>
<div class="gm-title"><?=$gCraft===3?'ACCEL G':'LM T/W · G'?></div>
<svg viewBox="0 0 160 145" role="img" aria-label="Spacecraft longitudinal G-force indicator">
 <circle cx="80" cy="80" r="65" fill="#262b29" stroke="#b9bcb0" stroke-width="4"/>
 <?php for($i=0;$i<=12;$i++):
  $angle=deg2rad(-110+$i*220/12);
  $x1=80+50*sin($angle);$y1=80-50*cos($angle);
  $x2=80+59*sin($angle);$y2=80-59*cos($angle);
 ?><line x1="<?=round($x1,2)?>" y1="<?=round($y1,2)?>" x2="<?=round($x2,2)?>" y2="<?=round($y2,2)?>" stroke="#dedfd3" stroke-width="<?=$i%2===0?2:1?>"/>
 <?php if($i%2===0):
 $xt=80+41*sin($angle);$yt=80-41*cos($angle)+3;
 ?><text x="<?=round($xt,2)?>" y="<?=round($yt,2)?>" fill="#e9e5d6" font-size="9" text-anchor="middle"><?=$i-2?></text><?php endif;endfor;?>
 <text x="80" y="47" text-anchor="middle" fill="#e9e5d6" font-size="10">G</text>
 <g class="gm-needle"><path d="M80 25 L77 82 L83 82Z" fill="#ddc687"/></g>
 <circle cx="80" cy="80" r="5" fill="#bdbbb0"/>
</svg>
<div class="gm-bottom">AXIAL <strong data-g>--</strong> G</div>
<div class="gm-bottom">TOTAL <span data-total>--</span> G</div>
<div class="gm-buttons"><span>POWER</span><button type="button" data-power="off">OFF</button><button type="button" data-power="on">ON</button></div>
<div class="gm-indicator" data-info>WAITING FOR SAMPLE</div>
</div>
<script>
(()=>{
const root=document.getElementById(<?=json_encode($gId)?>);
if(!root)return;
const craft=Number(root.dataset.craft);
async function update(){
 try{
  const [reading,power]=await Promise.all([
   fetch('g_meter_status.php?craft='+craft,{cache:'no-store'}),
   fetch('component_power.php?system=g_meter&craft='+craft,{cache:'no-store'})
  ]);
  const s=await reading.json(),p=power.ok?await power.json():{on:false};
  if(!reading.ok){root.querySelector('[data-info]').textContent=s.error||'DATA ERROR';return;}
  if(!power.ok){root.querySelector('[data-info]').textContent='POWER STATUS ERROR';return;}
  root.querySelectorAll('[data-power]').forEach(b=>b.classList.toggle('selected',(b.dataset.power==='on')===p.on));
  const valid=Boolean(s.valid && p.on);
  root.querySelector('[data-g]').textContent=valid?Number(s.g_forward).toFixed(3):'--';
  root.querySelector('[data-total]').textContent=valid?Number(s.g_magnitude).toFixed(3):'--';
  root.querySelector('[data-info]').textContent=s.status||(!p.on?'POWER OFF':valid?'SAMPLED':'WAITING FOR SAMPLE');
  const needle=root.querySelector('.gm-needle');
  needle.style.visibility=valid?'visible':'hidden';
  if(valid){
   const axial=Math.max(-2,Math.min(10,Number(s.g_forward)));
   needle.style.transform='rotate('+(-110+(axial+2)/12*220)+'deg)';
  }
 }catch(e){root.querySelector('[data-info]').textContent='CONNECTION ERROR';}
}
root.querySelectorAll('[data-power]').forEach(b=>b.addEventListener('click',async()=>{
 try{await fetch('component_power.php?system=g_meter&craft='+craft,{
 method:'POST',headers:{'Content-Type':'application/json'},
 body:JSON.stringify({command:b.dataset.power})});}catch(e){}
 await update();
}));
update();
let polling=false;
const timer=setInterval(async()=>{if(document.hidden||polling)return;polling=true;try{await update();}finally{polling=false;}},5000);
window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
})();
</script>
