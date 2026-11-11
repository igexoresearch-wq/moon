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
<div style="text-align:center;margin:7px 0;font-size:11px;letter-spacing:1px">OPTICAL RETICLE · 0.5° PER MINOR DIVISION</div>
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

<div class="sext-instructions">P30: IDENTIFY A/B/C USING THE REFERENCE CHART. READ ANGLES FROM ENGRAVED RETICLE SCALES: X RIGHT, Y UP. EACH SMALL TICK IS 0.5°. CONVERT TO XXYY WITH 50 AS ZERO, THEN ENTER R1-R3. KEEP ATTITUDE STEADY.</div>
</div>
<script type="module">
// Shared star projection logic is inserted server-side to avoid ES module fetch failures.
<?=preg_replace('/^export /m','',file_get_contents(__DIR__.'/star_field.js'))?>
const root=document.getElementById(<?=json_encode($sextantId)?>),canvas=root.querySelector('canvas'),ctx=canvas.getContext('2d'),craft=Number(root.dataset.craft);
const stars=<?=json_encode($sextantStars,JSON_THROW_ON_ERROR)?>;
let objects=[],self=null,pending=false;
const chartKey=root.querySelector('[data-chart-key]'),chartButton=root.querySelector('[data-chart-toggle]');
chartButton.addEventListener('click',()=>{
 const hidden=chartKey.style.display!=='none';
 chartKey.style.display=hidden?'none':'';
 chartButton.textContent=hidden?'SHOW CHART KEY':'HIDE CHART KEY';
});
// Fixed, engraved crosshair and numerical angular scale: no digital sight readout.
function reticle(){
 const center=154,scale=308/49;
 ctx.save();
 ctx.strokeStyle='rgba(215,218,189,.83)';ctx.lineWidth=1;
 ctx.beginPath();
 ctx.moveTo(14,center);ctx.lineTo(294,center);
 ctx.moveTo(center,14);ctx.lineTo(center,294);
 ctx.stroke();
 // Calibration matches starProject's 49-degree square angular field.
 // 1 small division is 0.5 degrees; numbered divisions are 5 degrees.
 for(let half=-48;half<=48;half++){
  const angle=half*.5;
  if(angle===0)continue;
  const p=center+angle*scale;
  const y=center-angle*scale;
  const major=half%10===0,medium=half%2===0;
  const len=major?11:(medium?6:3);
  ctx.beginPath();
  ctx.moveTo(p,center-len/2);ctx.lineTo(p,center+len/2);
  ctx.moveTo(center-len/2,y);ctx.lineTo(center+len/2,y);
  ctx.stroke();
  if(major){
   ctx.fillStyle='rgba(229,224,192,.95)';
   ctx.font='10px monospace';
   ctx.textAlign='center';ctx.textBaseline='top';
   ctx.fillText(String(angle),p,center+13);
   ctx.textAlign='left';ctx.textBaseline='middle';
   ctx.fillText(String(angle),center+14,y);
  }
 }
 ctx.fillStyle='#e9dfbe';ctx.font='10px monospace';
 ctx.textAlign='center';ctx.textBaseline='top';ctx.fillText('0',center+5,center+7);
 ctx.textAlign='right';ctx.fillText('X +',292,center-22);
 ctx.textAlign='left';ctx.fillText('Y +',center+15,17);
 ctx.restore();
}
function render(){
 ctx.clearRect(0,0,308,308);
 if(self&&stars.length)drawStarField(ctx,canvas,stars,self,objects,{clear:false});
 reticle();
}
async function poll(){
 if(pending)return;pending=true;
 try{
  const r=await fetch('state.php?observer='+craft,{cache:'no-store'});
  if(!r.ok)throw Error('telemetry unavailable');objects=(await r.json()).objects||[];
  self=objects.find(o=>Number(o.id)===craft);
  render();
 }catch(e){console.warn('Sextant telemetry unavailable:',e.message);}
 finally{pending=false;}
}
poll();const timer=setInterval(()=>{if(!document.hidden)poll();},1000);
window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
</script>
