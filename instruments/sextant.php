<?php
// Optical sextant installed on pilot stations. Sightings are human readings.
require_once dirname(__DIR__).'/star_alignment.php';
$sextantStars=moonStarCatalog();
$sextantCraft=($console??'')==='lmp'?6:3;
$sextantId='sext-'.bin2hex(random_bytes(4));
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
<div class="sext-readout"><button type="button" data-star="A">A / CORNER</button><button type="button" data-star="B">B / ARROW</button><button type="button" data-star="C">C / CROWN</button></div>
<div data-status>SELECT STAR · MEASURE THROUGH RETICLE</div>
<div class="sext-instructions">P30: ENTER PACKED XXYY FOR STARS A, B, C IN R1-R3. 50=ZERO; EACH COUNT=0.5°. X RIGHT, Y UP. MOVE THE SEXTANT CURSOR TO A STAR, THEN COPY PACKED CODE INTO DSKY. HOLD SPACECRAFT ATTITUDE STEADY DURING ALL THREE SIGHTS.</div>
</div>
<script type="module">
// Shared star projection logic is inserted server-side to avoid ES module fetch failures.
<?=preg_replace('/^export /m','',file_get_contents(__DIR__.'/star_field.js'))?>
const root=document.getElementById(<?=json_encode($sextantId)?>),canvas=root.querySelector('canvas'),ctx=canvas.getContext('2d'),craft=Number(root.dataset.craft);
const stars=<?=json_encode($sextantStars,JSON_THROW_ON_ERROR)?>;
let objects=[],self=null,selected='A',mouse=null,pending=false;
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
  const visibility=drawStarField(ctx,canvas,stars,self,objects,{annotate:true,clear:false});
  const s=stars.find(item=>item.id===selected);
  if(s){
   const p=starProject(s.vector,starBasis(self));
   if(p&&!starOcculted(s.vector,self,objects)){
    const px=154+p.x*308/49,py=154-p.y*308/49;
    ctx.strokeStyle='#ffe19a';ctx.lineWidth=1;ctx.beginPath();ctx.arc(px,py,9,0,2*Math.PI);ctx.stroke();
    if(!mouse)readout(p.x,p.y);
    root.querySelector('[data-status]').textContent='STAR '+selected+' VISIBLE — READ XY';
   }else root.querySelector('[data-status]').textContent='STAR '+selected+(p?' HIDDEN BY PLANET':' OUTSIDE FOV')+
    ' · OTHER VISIBLE STARS '+visibility.drawn;
  }
  if(visibility.drawn===0)root.querySelector('[data-status]').textContent=visibility.inFov>0?'STARLIGHT BLOCKED BY EARTH / MOON':'NO STARS IN FOV — CHANGE ATTITUDE';
 }
 grid();
 if(mouse){
  ctx.strokeStyle='#f9bfa3';ctx.beginPath();ctx.moveTo(mouse.px-7,mouse.py);ctx.lineTo(mouse.px+7,mouse.py);
  ctx.moveTo(mouse.px,mouse.py-7);ctx.lineTo(mouse.px,mouse.py+7);ctx.stroke();
  readout((mouse.px-154)*49/308,(154-mouse.py)*49/308);
 }
}
root.querySelectorAll('[data-star]').forEach(b=>b.addEventListener('click',()=>{selected=b.dataset.star;mouse=null;render();}));
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
