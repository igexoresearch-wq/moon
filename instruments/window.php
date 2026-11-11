<?php
// A single reusable optical window. Its observer is supplied by console.php.
// All graphics are rendered here; the console page never reloads.
require_once dirname(__DIR__).'/star_alignment.php';
$windowStars=moonStarCatalog();
$windowId='optical-'.bin2hex(random_bytes(6));
?>
<div style="width:400px;height:400px;position:relative;background:#000;overflow:hidden;clip-path:polygon(0 75%,75% 0,100% 0,100% 100%,0 100%)">
  <canvas id="<?=htmlspecialchars($windowId,ENT_QUOTES)?>" width="400" height="400" style="display:block;width:400px;height:400px"></canvas>
  <canvas id="<?=htmlspecialchars($windowId,ENT_QUOTES)?>-stars" width="400" height="400" style="position:absolute;inset:0;pointer-events:none"></canvas>
  <svg viewBox="0 0 400 400" width="400" height="400" aria-hidden="true" style="position:absolute;inset:0;pointer-events:none">
    <!-- Clipped-corner CM viewport border. -->
    <path d="M 0 300 L 300 0 L 400 0 L 400 400 L 0 400 Z" fill="none" stroke="#555" stroke-width="6"/>
    <!-- Five-degree pitch reference marks along the right-hand edge;
         long ticks mark 10 degrees. Vertical optical FOV: 45 degrees. -->
    <?php for($degree=10;$degree>=-20;$degree-=5):
       $y=200-$degree*(400/45);
       $length=($degree%10===0)?19:10;
    ?>
    <line x1="<?=400-$length?>" y1="<?=round($y,2)?>" x2="397" y2="<?=round($y,2)?>" stroke="#a6afa8" stroke-width="2"/>
    <?php endfor; ?>
  </svg>
  <div id="<?=htmlspecialchars($windowId,ENT_QUOTES)?>-status" aria-live="polite" style="position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap">3D WINDOW INITIALIZING…</div>
</div>
<script type="module">
const id=<?=json_encode($windowId)?>;
const status=document.getElementById(id+'-status');
// Self-contained sky renderer: no stale external star_field.js can reintroduce labels.
// Real optical sky: stars only. Reference labels remain on the separate chart.
const starRadians=Math.PI/180;
function starBasis(o){
 const p=Number(o.pitch||0)*starRadians,y=Number(o.yaw||0)*starRadians,r=Number(o.roll||0)*starRadians;
 const f=[Math.cos(p)*Math.cos(y),Math.cos(p)*Math.sin(y),Math.sin(p)];
 const right0=[-Math.sin(y),Math.cos(y),0];
 const up0=[-Math.sin(p)*Math.cos(y),-Math.sin(p)*Math.sin(y),Math.cos(p)];
 return [f,right0.map((v,i)=>v*Math.cos(r)+up0[i]*Math.sin(r)),
  up0.map((v,i)=>v*Math.cos(r)-right0[i]*Math.sin(r))];
}
function starProject(dir,basis){
 const dot=b=>b[0]*dir[0]+b[1]*dir[1]+b[2]*dir[2];
 const f=dot(basis[0]);if(f<=0)return null;
 const x=Math.atan2(dot(basis[1]),f)/starRadians;
 const y=Math.atan2(dot(basis[2]),f)/starRadians;
 return Math.abs(x)<=24.5&&Math.abs(y)<=24.5?{x,y}:null;
}
function starOcculted(dir,self,objects){
 for(const body of objects){
  if(Number(body.id)!==1&&Number(body.id)!==2)continue;
  const v=[Number(body.x)-Number(self.x),Number(body.y)-Number(self.y),Number(body.z)-Number(self.z)];
  const distance=Math.hypot(...v),radius=Number(body.radius_length);
  if(distance<=radius)return true;
  const cosine=(v[0]*dir[0]+v[1]*dir[1]+v[2]*dir[2])/distance;
  if(cosine>Math.sqrt(Math.max(0,1-(radius/distance)**2)))return true;
 }
 return false;
}
function drawStarField(ctx,canvas,stars,self,objects,{clear=true}={}){
 if(clear)ctx.clearRect(0,0,canvas.width,canvas.height);
 const basis=starBasis(self),scale=canvas.width/49;
 for(const star of stars){
  const pos=starProject(star.vector,basis);
  if(!pos||starOcculted(star.vector,self,objects))continue;
  const x=canvas.width/2+pos.x*scale,y=canvas.height/2-pos.y*scale;
  const bright=star.group==='TRIAD';
  const radius=bright?2.4:star.group==='KEY'?2.0:0.5+Math.max(0,5-Number(star.magnitude))*.23;
  ctx.beginPath();ctx.arc(x,y,radius,0,Math.PI*2);
  ctx.fillStyle='#e6efff';ctx.fill();
 }
 // Deliberately NO labels, IDs, or constellation guide lines in the live sky.
}

{
 const stars=<?=json_encode($windowStars,JSON_THROW_ON_ERROR)?>;
 const sky=document.getElementById(id+'-stars'),ctx=sky.getContext('2d');
 let inFlight=false;
 async function paint(){
  if(inFlight||document.hidden)return;inFlight=true;
  try{
   const res=await fetch('state.php?observer='+<?=(int)$observerId?>,{cache:'no-store'});
   if(!res.ok)return;
   const data=await res.json(),objects=data.objects||[];
   const self=objects.find(o=>Number(o.id)===<?=(int)$observerId?>);
   if(!self||Number(self.active)!==1){ctx.clearRect(0,0,400,400);return;}
   drawStarField(ctx,sky,stars,self,objects);
  }catch(e){console.warn('Star window telemetry:',e.message)}finally{inFlight=false;}
 }
 paint();
 const starTimer=setInterval(paint,1000);
 window.addEventListener('pagehide',()=>clearInterval(starTimer),{once:true});
}
import('./instruments/window3d.js').then(({startOpticalWindow})=>{
 startOpticalWindow(id,<?=(int)$observerId?>);
}).catch(err=>{
 status.textContent='3D MODULE FAILED — '+(err?.message||String(err));
 console.error('Moon 3D import error',err);
});
</script>


<?php if(in_array($console??'',['cmc','lmc'],true)): ?>
<style>
.moon-caution{width:400px;box-sizing:border-box;margin-top:12px;padding:12px;background:#333;border:4px solid #4c4c4c;font-family:Arial,sans-serif}
.moon-master-row{display:flex;justify-content:center;margin-bottom:12px}
.moon-caution-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
.moon-caution-light{box-sizing:border-box;min-height:52px;background:#545759;color:#c5c5c5;border:4px solid #181a1b;border-radius:3px;box-shadow:inset 0 2px 7px #202020;text-shadow:0 1px #111;display:flex;align-items:center;justify-content:center;text-align:center;font:bold 12px/1.2 Arial,sans-serif;padding:4px;text-transform:uppercase;overflow-wrap:anywhere}
.moon-caution-light.active{background:#e4c333;color:#28220a;box-shadow:inset 0 0 14px #fff49a,0 0 8px #e7d044;text-shadow:none}
button.moon-caution-light{cursor:pointer;width:140px;font-size:15px}
</style>
<section class="moon-caution" data-station="<?=htmlspecialchars($console,ENT_QUOTES)?>" aria-label="Caution and master alarm">
 <div class="moon-master-row"><button type="button" class="moon-caution-light moon-master-button" title="Acknowledge master alarm">MASTER</button></div>
 <div class="moon-caution-grid" aria-live="polite"></div>
</section>
<script>
(()=>{
 const panel=document.currentScript.previousElementSibling;
 if(!panel||!panel.classList.contains('moon-caution'))return;
 const master=panel.querySelector('.moon-master-button'),grid=panel.querySelector('.moon-caution-grid');
 const url='reliability_alarms.php?console='+encodeURIComponent(panel.dataset.station);
 let pending=false;
 async function refresh(){
  try{
   const response=await fetch(url,{cache:'no-store'});
   if(!response.ok)throw Error('Alarm data unavailable');
   const data=await response.json();pending=!!data.master;
   master.classList.toggle('active',pending);
   master.setAttribute('aria-label',pending?'MASTER alarm active, click to acknowledge':'MASTER alarm clear');
   grid.replaceChildren(...data.lights.map(item=>{
    const lamp=document.createElement('div');lamp.className='moon-caution-light'+(item.lit?' active':'');lamp.textContent=item.label;return lamp;
   }));
  }catch(e){console.warn('Alarm panel:',e.message);}
 }
 master.addEventListener('click',async()=>{
  if(!pending)return;
  try{await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'acknowledge'})});await refresh();}
  catch(e){console.warn('Master acknowledge:',e);}
 });
 refresh();let polling=false;const timer=setInterval(async()=>{if(document.hidden||polling)return;polling=true;try{await refresh();}finally{polling=false;}},5000);
 window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
})();
</script>
<?php endif; ?>
