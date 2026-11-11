<?php
// Optical sextant installed on pilot stations. Sightings are human readings.
require_once dirname(__DIR__).'/star_alignment.php';
$sextantStars=moonStarCatalog();
$sextantCraft=($console??'')==='lmp'?6:3;
$sextantId='sext-'.bin2hex(random_bytes(4));
$chartStars=[];
foreach($sextantStars as $s){
 $v=$s['vector'];if($v[0]<=0)continue;
 $sx=rad2deg(atan2($v[1],$v[0]));$sy=rad2deg(atan2($v[2],$v[0]));
 if(abs($sx)>24.5||abs($sy)>24.5)continue;
 $chartStars[]=['id'=>$s['id'],'group'=>$s['group'],'x'=>105+$sx*4.1,'y'=>105-$sy*4.1];
}
?>
<div id="<?=htmlspecialchars($sextantId,ENT_QUOTES)?>" data-craft="<?=$sextantCraft?>" style="width:340px;background:#505951;border:5px ridge #282c27;color:#e7e5d9;padding:10px;font:12px monospace;box-sizing:border-box">
<style>
#<?=htmlspecialchars($sextantId)?> canvas{display:block;background:#02060a;border:3px inset #949b8f;width:308px;height:308px}
#<?=htmlspecialchars($sextantId)?> .sext-readout{display:flex;justify-content:space-between;margin:8px 0}
#<?=htmlspecialchars($sextantId)?> button{background:#aaa99a;border:2px outset #d3d4c9;color:#222;font:bold 11px Arial;padding:5px;cursor:pointer}
#<?=htmlspecialchars($sextantId)?> .sext-instructions{font:10px monospace;line-height:1.5;color:#d4d7be;margin-top:6px}
</style>
<div style="text-align:center;font-weight:bold;letter-spacing:1px">OPTICAL SEXTANT · STAR SIGHTINGS</div>
<canvas width="308" height="308" aria-label="Sextant field with central reticle and measured X/Y"></canvas>
<div class="sext-readout"><span>X <b data-x>00.0</b>°</span><span>Y <b data-y>00.0</b>°</span><span>PACK <b data-pack>5050</b></span></div>
<details style="margin:10px 0;padding:7px;background:#242b2b;border:1px solid #858b7e" open>
<summary style="cursor:pointer;font-weight:bold">REFERENCE STAR CHART / INERTIAL +X</summary>
<svg viewBox="0 0 210 210" width="210" height="210" role="img" aria-label="Reference star chart showing the Corner Arrow Crown triangle and nearby background stars" style="display:block;margin:8px auto;background:#050b14;border:1px solid #5d736f">
<?php foreach($chartStars as $c):?>
<circle cx="<?=round($c['x'],2)?>" cy="<?=round($c['y'],2)?>" r="<?=$c['group']==='TRIAD'?3.3:($c['group']==='KEY'?2.1:0.75)?>" fill="<?=$c['group']==='TRIAD'?'#ffe2a1':'#dce8f8'?>"/>
<?php endforeach;?>
<g data-chart-key>
<?php foreach($chartStars as $c):if($c['group']!=='TRIAD')continue;?>
<text x="<?=round($c['x']+6,2)?>" y="<?=round($c['y']-5,2)?>" fill="#e8e2b5" font-family="monospace" font-size="10"><?=htmlspecialchars($c['id'],ENT_QUOTES)?></text>
<?php endforeach;?>
</g>
</svg>
<div style="font-size:10px;line-height:1.5">A CORNER · B ARROW · C CROWN<br>CHART USES THE SAME FIXED STAR CATALOG AS THE WINDOW. ROTATE THE SPACECRAFT TO FIND THE PATTERN; ORIENTATION IN THE SEXTANT MAY BE ROTATED.</div>
<button type="button" data-chart-toggle style="margin-top:6px">HIDE CHART KEY</button>
</details>
<div data-status>SELECT STAR · MEASURE THROUGH RETICLE</div>
<div class="sext-instructions">P30: IDENTIFY A/B/C USING THE SEPARATE REFERENCE CHART. CLICK EACH STAR IN THE LIVE SEXTANT TO MEASURE IT; ENTER XXYY INTO DSKY R1-R3. 50=ZERO, 0.5° PER COUNT; X RIGHT, Y UP. DO NOT ROTATE BETWEEN SIGHTINGS.</div>
</div>
<script type="module">
// Shared star projection logic is inserted server-side to avoid ES module fetch failures.
<?=preg_replace('/^export /m','',file_get_contents(__DIR__.'/star_field.js'))?>
const root=document.getElementById(<?=json_encode($sextantId)?>),canvas=root.querySelector('canvas'),ctx=canvas.getContext('2d'),craft=Number(root.dataset.craft);
const stars=<?=json_encode($sextantStars,JSON_THROW_ON_ERROR)?>;
let objects=[],self=null,mouse=null,pending=false;
const chartKey=root.querySelector('[data-chart-key]'),chartButton=root.querySelector('[data-chart-toggle]');
chartButton.addEventListener('click',()=>{const hide=chartKey.style.display!=='none';chartKey.style.display=hide?'none':'';chartButton.textContent=hide?'SHOW CHART KEY':'HIDE CHART KEY';});
function grid(){
 ctx.save();ctx.strokeStyle='#405b57';ctx.lineWidth=.5;
 for(let i=0;i<=10;i++){const n=i*canvas.width/10;ctx.beginPath();ctx.moveTo(n,0);ctx.lineTo(n,canvas.height);ctx.moveTo(0,n);ctx.lineTo(canvas.width,n);ctx.stroke();}
 ctx.strokeStyle='#d5d9b6';ctx.lineWidth=1;const x=canvas.width/2,y=canvas.height/2;
 ctx.beginPath();ctx.moveTo(x-13,y);ctx.lineTo(x+13,y);ctx.moveTo(x,y-13);ctx.lineTo(x,y+13);ctx.stroke();ctx.restore();
}
function readout(x,y){
 const ix=Math.max(0,Math.min(99,Math.round(x*2+50))),iy=Math.max(0,Math.min(99,Math.round(y*2+50)));
 const packed=String(ix).padStart(2,'0')+String(iy).padStart(2,'0');
 root.querySelector('[data-x]').textContent=((ix-50)*.5).toFixed(1);
 root.querySelector('[data-y]').textContent=((iy-50)*.5).toFixed(1);
 root.querySelector('[data-pack]').textContent=packed;
}
function render(){
 ctx.clearRect(0,0,308,308);
 if(self&&stars.length){
  const drawn=drawStarField(ctx,canvas,stars,self,objects,{clear:false});
  const visibility=drawn&&typeof drawn.drawn==='number'?drawn:(()=>{
   let inFov=0,visible=0;const basis=starBasis(self);
   for(const star of stars){
    if(!starProject(star.vector,basis))continue;
    inFov++;
    if(!starOcculted(star.vector,self,objects))visible++;
   }
   return {inFov,drawn:visible};
  })();
  root.querySelector('[data-status]').textContent='VISIBLE STARS '+visibility.drawn+' · IDENTIFY BY REFERENCE CHART';
  if(visibility.drawn===0)root.querySelector('[data-status]').textContent=visibility.inFov>0?'STARLIGHT BLOCKED BY EARTH / MOON':'NO STARS IN FOV — CHANGE ATTITUDE';
 }
 grid();
 if(mouse){
  ctx.strokeStyle='#f9bfa3';ctx.beginPath();ctx.moveTo(mouse.px-7,mouse.py);ctx.lineTo(mouse.px+7,mouse.py);
  ctx.moveTo(mouse.px,mouse.py-7);ctx.lineTo(mouse.px,mouse.py+7);ctx.stroke();
  readout((mouse.px-154)*49/308,(154-mouse.py)*49/308);
 }
}
canvas.addEventListener('click',event=>{
 const rect=canvas.getBoundingClientRect();mouse={px:(event.clientX-rect.left)*308/rect.width,py:(event.clientY-rect.top)*308/rect.height};
 render();
});
async function poll(){
 if(pending)return;pending=true;
 try{
  const r=await fetch('state.php?observer='+craft,{cache:'no-store'});
  if(!r.ok)throw Error('telemetry unavailable');objects=(await r.json()).objects||[];
  self=objects.find(o=>Number(o.id)===craft);
  render();
 }catch(e){root.querySelector('[data-status]').textContent='TELEMETRY UNAVAILABLE';}
 finally{pending=false;}
}
poll();const timer=setInterval(()=>{if(!document.hidden)poll();},1000);
window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
</script>
