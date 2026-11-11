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
  <div id="<?=htmlspecialchars($windowId,ENT_QUOTES)?>-sky-status" style="position:absolute;left:65px;bottom:9px;right:9px;z-index:4;color:#e1e8e2;background:rgba(0,0,0,.76);font:11px monospace;padding:5px 7px;pointer-events:none">STAR CATALOG LOADING</div>
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
const skyStatus=document.getElementById(id+'-sky-status');
// Render the shared star code inline: GoDaddy need not serve an ES module.
<?=preg_replace('/^export /m','',file_get_contents(__DIR__.'/star_field.js'))?>
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
   if(!self||Number(self.active)!==1){ctx.clearRect(0,0,400,400);skyStatus.textContent='SPACECRAFT INACTIVE';return;}
   // Older deployed star_field.js draws stars but returns no statistics.
   // Calculate a fallback count so mixed-version installs keep rendering.
   const rendered=drawStarField(ctx,sky,stars,self,objects);
   const sight=rendered&&typeof rendered.drawn==='number'?rendered:(()=>{
    let inFov=0,drawn=0,referenceVisible=0;
    const basis=starBasis(self);
    for(const star of stars){
     if(!starProject(star.vector,basis))continue;
     inFov++;
     if(starOcculted(star.vector,self,objects))continue;
     drawn++;
     if(star.group==='TRIAD')referenceVisible++;
    }
    return {inFov,drawn,referenceVisible};
   })();
   skyStatus.textContent=sight.drawn===0
    ?(sight.inFov>0?'STARS OCCULTED BY EARTH / MOON':'NO STARS IN FOV — CHANGE ATTITUDE')
    :'VISIBLE STARS '+sight.drawn+' · REFERENCE '+sight.referenceVisible+'/3'+
      (sight.referenceVisible<3?' · POINT NEAR INERTIAL +X FOR TRIAD':'');
  }catch(e){skyStatus.textContent='STAR TELEMETRY ERROR: '+e.message;console.warn('Star window:',e.message)}finally{inFlight=false;}
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
