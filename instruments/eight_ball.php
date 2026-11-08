<?php
// Independent Apollo-inspired FDAI. Visual approximation, not certified Apollo symbology.
$ballCraft=(($console??'')==='lmc')?6:3;
$ballUid='fdai-'.bin2hex(random_bytes(5));
?>
<div id="<?=htmlspecialchars($ballUid,ENT_QUOTES)?>" class="fdai-instrument" data-craft="<?=$ballCraft?>">
<style>
#<?=htmlspecialchars($ballUid)?>{width:300px;padding:14px;box-sizing:border-box;background:#555951;border:5px ridge #282c2b;color:#e8e3d5;font:12px Arial,sans-serif;user-select:none}
#<?=htmlspecialchars($ballUid)?> .fdai-heading{text-align:center;letter-spacing:2px;font-weight:bold;margin-bottom:8px}
#<?=htmlspecialchars($ballUid)?> canvas{display:block;width:260px;height:260px;border-radius:50%;background:#141719;border:6px solid #242627;box-sizing:border-box;margin:auto}
#<?=htmlspecialchars($ballUid)?> .fdai-rates{display:flex;justify-content:space-between;font-family:monospace;margin:9px 0;gap:3px}
#<?=htmlspecialchars($ballUid)?> .fdai-rates span{background:#232723;padding:5px;font-size:12px}
#<?=htmlspecialchars($ballUid)?> button{display:block;margin:8px auto 0;background:#9a9a90;border:3px solid #252725;padding:8px 15px;font-weight:bold;cursor:pointer}
#<?=htmlspecialchars($ballUid)?> .fdai-power{display:flex;justify-content:center;align-items:center;gap:8px;margin:8px}
#<?=htmlspecialchars($ballUid)?> .fdai-power button{margin:0;min-width:48px}
#<?=htmlspecialchars($ballUid)?> .fdai-power button.selected{background:#d4c56c}
#<?=htmlspecialchars($ballUid)?> .fdai-foot{text-align:center;opacity:.8;font-size:10px;margin-top:5px}
</style>
<div class="fdai-heading">FLIGHT DIRECTOR ATTITUDE INDICATOR</div>
<canvas width="520" height="520" aria-label="Eight-ball attitude indicator"></canvas>
<div class="fdai-rates"><span>R <b data-r>000.0</b></span><span>P <b data-p>000.0</b></span><span>Y <b data-y>000.0</b></span></div>
<div class="fdai-power"><span>IMU POWER</span><button type="button" data-power="off">OFF</button><button type="button" data-power="on">ON</button></div>
<button type="button" data-align>IMU ALIGN</button>
<div class="fdai-foot">DEVELOPMENT ALIGNMENT CONTROL</div>
</div>
<script>
(()=>{
const root=document.getElementById(<?=json_encode($ballUid)?>),canvas=root.querySelector('canvas'),ctx=canvas.getContext('2d');
const craft=+root.dataset.craft,cy=260,cx=260,radius=239;
let last={pitch_deg:0,yaw_deg:0,roll_deg:0};
function draw(s){
const pitch=s.pitch_deg,yaw=s.yaw_deg,roll=s.roll_deg;
ctx.clearRect(0,0,520,520);ctx.save();
ctx.beginPath();ctx.arc(cx,cy,radius,0,2*Math.PI);ctx.clip();
ctx.fillStyle='#d8d5c2';ctx.fillRect(0,0,520,520);
ctx.translate(cx,cy);ctx.rotate(-roll*Math.PI/180);
// Approximate ball: latitude bands move with pitch, longitude lines with yaw.
let po=((pitch+180)%360)-180;
let yo=((yaw+180)%360)-180;
const scale=radius/90;
for(let latitude=-180;latitude<=180;latitude+=30){
 const yy=(latitude-po)*scale;
 if(Math.abs(yy)>radius+90)continue;
 ctx.strokeStyle=latitude===0?'#202d34':'#66706e';ctx.lineWidth=latitude===0?5:2;
 ctx.beginPath();ctx.moveTo(-radius,yy);ctx.lineTo(radius,yy);ctx.stroke();
 ctx.fillStyle='#202c32';ctx.font='bold 20px Arial';ctx.fillText(String(latitude),-radius+18,yy-7);
}
for(let longitude=-360;longitude<=360;longitude+=30){
 const xx=(longitude-yo)*scale;
 if(Math.abs(xx)>radius+50)continue;
 ctx.strokeStyle='#7b817b';ctx.lineWidth=2;ctx.beginPath();ctx.moveTo(xx,-radius);ctx.lineTo(xx,radius);ctx.stroke();
}
ctx.restore();
// Fixed aircraft reference marks around moving ball.
ctx.strokeStyle='#e8b74d';ctx.lineWidth=7;
ctx.beginPath();ctx.moveTo(140,260);ctx.lineTo(230,260);ctx.moveTo(290,260);ctx.lineTo(380,260);ctx.moveTo(260,239);ctx.lineTo(260,281);ctx.stroke();
ctx.strokeStyle='#313638';ctx.lineWidth=10;ctx.beginPath();ctx.arc(cx,cy,radius,0,2*Math.PI);ctx.stroke();
for(const [key,val] of [['r',roll],['p',pitch],['y',yaw]]){
 root.querySelector('[data-'+key+']').textContent=((val%360)+360)%360<0?'000.0':(((val%360)+360)%360).toFixed(1).padStart(5,'0');
}
}
async function refreshPower(){try{const r=await fetch('component_power.php?system=imu&craft='+craft,{cache:'no-store'});if(!r.ok)return;const v=await r.json();root.querySelectorAll('[data-power]').forEach(b=>b.classList.toggle('selected',(b.dataset.power==='on')===v.on));}catch(e){}}
root.querySelectorAll('[data-power]').forEach(b=>b.addEventListener('click',async()=>{try{await fetch('component_power.php?system=imu&craft='+craft,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({system:'imu',craft,command:b.dataset.power})});await refreshPower();}catch(e){}}));
async function poll(){try{
 const res=await fetch('imu_status.php?craft='+craft,{cache:'no-store'});if(!res.ok)return;
 last=await res.json();draw(last);
}catch(e){}}
root.querySelector('[data-align]').addEventListener('click',async()=>{
 if(!window.confirm('Simulator shortcut: realign IMU estimate to the true spacecraft attitude?'))return;
 try{let r=await fetch('imu_align.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({craft})});if(r.ok)await poll();}catch(e){}
});
draw(last);poll();refreshPower();const timer=setInterval(()=>{poll();refreshPower();},1000);
window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
})();
</script>
