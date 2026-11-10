<?php
// CM-only, independently positionable Service Module fuel-cell instrument.
$fcId='fc-'.bin2hex(random_bytes(5));
?>
<div id="<?=htmlspecialchars($fcId,ENT_QUOTES)?>" class="fuel-cell-panel">
<style>
#<?=htmlspecialchars($fcId)?>{width:360px;box-sizing:border-box;padding:14px 12px;background:#555a57;border:5px ridge #282b2a;color:#deddd2;font:12px Arial,sans-serif;user-select:none}
#<?=htmlspecialchars($fcId)?> .fc-title{text-align:center;letter-spacing:2px;font-weight:bold;background:#303330;padding:6px}
#<?=htmlspecialchars($fcId)?> .fc-units{display:flex;gap:8px;justify-content:space-evenly;margin-top:12px}
#<?=htmlspecialchars($fcId)?> .fc-unit{width:104px;text-align:center}
#<?=htmlspecialchars($fcId)?> .fc-name{font-size:12px;letter-spacing:1px;margin-bottom:6px;font-weight:bold}
#<?=htmlspecialchars($fcId)?> svg{display:block;width:100px;height:100px}
#<?=htmlspecialchars($fcId)?> .fc-switches{margin-top:6px;display:flex;gap:5px;justify-content:center}
#<?=htmlspecialchars($fcId)?> button{background:#9da097;border:2px solid #222625;color:#1d211e;font-weight:bold;min-width:43px;padding:8px 2px;cursor:pointer}
#<?=htmlspecialchars($fcId)?> button.selected{background:#d5c483}
#<?=htmlspecialchars($fcId)?> .fc-needle{transform-origin:50px 50px;transform:rotate(-115deg)}
</style>
<div class="fc-title">SERVICE MODULE FUEL CELLS</div>
<div class="fc-units">
<?php for($n=1;$n<=3;$n++): ?>
<div class="fc-unit" data-cell="<?=$n?>">
 <div class="fc-name">CELL <?=$n?></div>
 <svg viewBox="0 0 100 100" role="img" aria-label="Fuel cell <?=$n?> electrical output, analog ammeter">
  <circle cx="50" cy="50" r="47" fill="#222723" stroke="#b6b6a8" stroke-width="3"/>
  <?php for($i=0;$i<=5;$i++):
    $a=deg2rad(-115+$i*46);
    $x1=50+35*sin($a);$y1=50-35*cos($a);
    $x2=50+42*sin($a);$y2=50-42*cos($a);
  ?><line x1="<?=round($x1,2)?>" y1="<?=round($y1,2)?>" x2="<?=round($x2,2)?>" y2="<?=round($y2,2)?>" stroke="#dadbd0" stroke-width="2"/><?php endfor; ?>
  <text x="50" y="26" fill="#e3e0d0" text-anchor="middle" font-size="11" font-weight="bold">AMPS</text>
  <text x="20" y="82" fill="#e3e0d0" text-anchor="middle" font-size="9">0</text>
  <text x="79" y="82" fill="#e3e0d0" text-anchor="middle" font-size="9">50</text>
  <g class="fc-needle"><path d="M50 14L47 52L53 52Z" fill="#dec488"/></g>
  <circle cx="50" cy="50" r="4" fill="#bdbdb1"/>
 </svg>
 <div class="fc-switches"><button type="button" data-switch="off">OFF</button><button type="button" data-switch="on">ON</button></div>
</div>
<?php endfor; ?>
</div>
</div>
<script>
(()=>{
const panel=document.getElementById(<?=json_encode($fcId)?>);
if(!panel)return;
async function refresh(cell){
 const holder=panel.querySelector('[data-cell="'+cell+'"]');
 try{
  const res=await fetch('fuel_cell_control.php?cell='+cell,{cache:'no-store'});
  if(!res.ok)return;
  const v=await res.json();
  // Engine uses negative electrical generation. The period ammeter reads magnitude.
  const magnitude=Math.max(0,Math.min(50,-Number(v.output_amps)));
  holder.querySelector('.fc-needle').style.transform='rotate('+(-115+(magnitude/50)*230)+'deg)';
  holder.querySelectorAll('button').forEach(b=>b.classList.toggle('selected',(b.dataset.switch==='on')===v.on));
 }catch(e){}
}
for(const holder of panel.querySelectorAll('[data-cell]')){
 const cell=Number(holder.dataset.cell);
 holder.querySelectorAll('button').forEach(button=>button.addEventListener('click',async()=>{
  try{
   await fetch('fuel_cell_control.php?cell='+cell,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({command:button.dataset.switch})});
  }catch(e){}finally{await refresh(cell);}
 }));
 refresh(cell);
}
let polling=false;const timer=setInterval(async()=>{if(document.hidden||polling)return;polling=true;try{await Promise.all([1,2,3].map(n=>refresh(n)));}finally{polling=false;}},5000);
window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
})();
</script>
