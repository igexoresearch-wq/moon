<?php
// Individually positionable CM two-battery indicator. Analog Ah dials.
$bid='battery-'.bin2hex(random_bytes(5));
?>
<div id="<?=htmlspecialchars($bid,ENT_QUOTES)?>" class="bat-panel">
<style>
#<?=htmlspecialchars($bid)?>{width:288px;padding:12px;box-sizing:border-box;background:#545950;border:5px ridge #252926;color:#e0ded0;font:12px Arial,sans-serif;user-select:none}
#<?=htmlspecialchars($bid)?> .bat-title{text-align:center;background:#30332f;padding:6px;letter-spacing:2px;font-weight:bold}
#<?=htmlspecialchars($bid)?> .bat-pair{display:flex;justify-content:space-around;gap:8px;margin-top:12px}
#<?=htmlspecialchars($bid)?> .bat-unit{width:118px;text-align:center}
#<?=htmlspecialchars($bid)?> svg{width:108px;height:108px;display:block;margin:auto}
#<?=htmlspecialchars($bid)?> .bat-needle{transform-origin:60px 60px;transform:rotate(-120deg)}
#<?=htmlspecialchars($bid)?> .bat-switch{display:flex;justify-content:center;gap:5px}
#<?=htmlspecialchars($bid)?> button{padding:7px;min-width:45px;background:#999a8e;border:2px solid #222922;font-weight:bold;cursor:pointer}
#<?=htmlspecialchars($bid)?> button.selected{background:#d4c486}
</style>
<div class="bat-title">CM BATTERIES — AMPERE HOURS</div>
<div class="bat-pair">
<?php for($n=1;$n<=2;$n++): ?>
<div class="bat-unit" data-battery="<?=$n?>">
 <strong>BATTERY <?=$n?></strong>
 <svg viewBox="0 0 120 120" role="img" aria-label="Battery <?=$n?> remaining capacity in ampere hours">
 <circle cx="60" cy="60" r="55" fill="#222622" stroke="#aeb0a3" stroke-width="4"/>
 <?php for($i=0;$i<=8;$i++):$ang=deg2rad(-120+$i*30);
 $x1=60+42*sin($ang);$y1=60-42*cos($ang);
 $x2=60+49*sin($ang);$y2=60-49*cos($ang);?>
 <line x1="<?=round($x1,2)?>" y1="<?=round($y1,2)?>" x2="<?=round($x2,2)?>" y2="<?=round($y2,2)?>" stroke="#e0dfce" stroke-width="<?=$i%2?1:2?>"/>
 <?php endfor;?>
 <text x="60" y="35" text-anchor="middle" fill="#e0dfce" font-size="11" font-family="Arial">A·H</text>
 <text x="31" y="96" fill="#e0dfce" font-size="11" font-family="Arial">0</text>
 <text x="79" y="96" fill="#e0dfce" font-size="11" font-family="Arial">40</text>
 <g class="bat-needle"><path d="M60 17L57 62L63 62Z" fill="#e5c689"/></g>
 <circle cx="60" cy="60" r="5" fill="#aeb0a3"/>
 </svg>
 <div class="bat-switch"><button data-switch="off" type="button">OFF</button><button data-switch="on" type="button">ON</button></div>
</div>
<?php endfor;?>
</div></div>
<script>
(()=>{
const root=document.getElementById(<?=json_encode($bid)?>);
async function update(n){
 try{
  const res=await fetch('battery_control.php?battery='+n,{cache:'no-store'});if(!res.ok)return;
  const v=await res.json(),unit=root.querySelector('[data-battery="'+n+'"]');
  const f=Math.max(0,Math.min(1,Number(v.charge_ah)/Number(v.capacity_ah)));
  unit.querySelector('.bat-needle').style.transform='rotate('+(-120+240*f)+'deg)';
  unit.querySelectorAll('button').forEach(b=>b.classList.toggle('selected',(b.dataset.switch==='on')===v.on));
 }catch(e){}
}
for(const unit of root.querySelectorAll('[data-battery]')){
 const n=Number(unit.dataset.battery);update(n);
 unit.querySelectorAll('button').forEach(b=>b.addEventListener('click',async()=>{
  try{await fetch('battery_control.php?battery='+n,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({command:b.dataset.switch})});}catch(e){}
  await update(n);
 }));
}
const timer=setInterval(()=>{update(1);update(2);},2000);
window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
})();
</script>
