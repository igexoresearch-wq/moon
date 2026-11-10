<?php
// Mechanical-style range tape / range-rate indicator. Radar physics is in /rendezvous_radar.php.
$rrCraft=in_array($console??'',['lmc','lmp'],true)?6:3;
$rrId='radar-'.bin2hex(random_bytes(5));
?>
<div id="<?=htmlspecialchars($rrId,ENT_QUOTES)?>" data-craft="<?=$rrCraft?>" class="rr-panel">
<style>
#<?=htmlspecialchars($rrId)?>{width:372px;box-sizing:border-box;padding:10px;background:#54574f;border:5px ridge #292c28;color:#ddd8c8;font:11px Arial,sans-serif;user-select:none}
#<?=htmlspecialchars($rrId)?> .rr-title{background:#2c312d;text-align:center;font-weight:700;letter-spacing:2px;padding:7px}
#<?=htmlspecialchars($rrId)?> .rr-instruments{display:flex;gap:7px;margin-top:8px}
#<?=htmlspecialchars($rrId)?> .rr-side{position:relative;background:#1d231f;border:3px inset #858a7f;box-sizing:border-box;overflow:hidden}
#<?=htmlspecialchars($rrId)?> .rr-range{width:132px;height:220px}
#<?=htmlspecialchars($rrId)?> .rr-rate{width:207px;height:220px}
#<?=htmlspecialchars($rrId)?> .rr-plate{position:absolute;top:5px;left:0;right:0;text-align:center;z-index:3;font:bold 10px Arial;letter-spacing:1px;color:#dad9c8;background:#1d231f}
#<?=htmlspecialchars($rrId)?> .rr-window{position:absolute;left:8px;right:8px;top:27px;bottom:13px;overflow:hidden;border:1px solid #50584e;background:#252d29}
#<?=htmlspecialchars($rrId)?> .rr-ruler{position:absolute;inset:0;width:100%;height:100%}
#<?=htmlspecialchars($rrId)?> .rr-index{position:absolute;left:0;right:0;top:50%;border-top:2px solid #e5cb8b;z-index:2;pointer-events:none}
#<?=htmlspecialchars($rrId)?> .rr-index:after{content:'';position:absolute;right:0;top:-6px;border-top:5px solid transparent;border-bottom:5px solid transparent;border-right:9px solid #e5cb8b}
#<?=htmlspecialchars($rrId)?> .rr-rate svg{width:100%;height:100%;display:block}
#<?=htmlspecialchars($rrId)?> .rr-needle{transform-origin:104px 166px;transition:transform .35s ease-out}
#<?=htmlspecialchars($rrId)?> .rr-lamp{position:absolute;bottom:7px;left:50%;transform:translateX(-50%);padding:3px 7px;border:1px solid #555;border-radius:2px;background:#332622;color:#61534d;font-weight:bold;letter-spacing:1px;white-space:nowrap}
#<?=htmlspecialchars($rrId)?> .rr-lamp.lit{background:#872a19;color:#ffddd0;box-shadow:0 0 7px #b74b28}
#<?=htmlspecialchars($rrId)?> .rr-switches{display:flex;gap:8px;justify-content:space-between;margin-top:9px}
#<?=htmlspecialchars($rrId)?> .rr-switch{display:flex;align-items:center;gap:3px}
#<?=htmlspecialchars($rrId)?> .rr-switch strong{font-size:9px;margin-right:3px}
#<?=htmlspecialchars($rrId)?> button{border:2px solid #222720;background:#a1a398;color:#171b16;padding:5px 6px;min-width:31px;font-size:10px;font-weight:700;cursor:pointer}
#<?=htmlspecialchars($rrId)?> button.selected{background:#d4c18c}
</style>
<div class="rr-title">RENDEZVOUS RADAR</div>
<div class="rr-instruments">
 <div class="rr-side rr-range">
  <div class="rr-plate">RANGE · m / km</div>
  <div class="rr-window">
   <svg class="rr-ruler" viewBox="0 0 108 178" aria-label="Moving range scale in meters and kilometers">
    <g data-ruler-marks></g>
   </svg>
   <div class="rr-index"></div>
  </div>
 </div>
 <div class="rr-side rr-rate">
  <svg viewBox="0 0 207 220" role="img" aria-label="Range rate, opening left, closing right, meters per second">
   <rect width="207" height="220" fill="#202621"/>
   <text x="103.5" y="18" fill="#e1dbca" font-size="11" text-anchor="middle" font-weight="bold" letter-spacing="1.5">RANGE RATE</text>
   <text x="36" y="34" fill="#ded9c6" font-size="10" text-anchor="middle">OPENING</text>
   <text x="170" y="34" fill="#ded9c6" font-size="10" text-anchor="middle">CLOSING</text>
   <g data-rate-marks></g>
   <g class="rr-needle" data-needle>
    <path d="M104 166 L101 77 L107 77Z" fill="#e4c58b" stroke="#5d4d32" stroke-width=".6"/>
    <circle cx="104" cy="166" r="7" fill="#8c8e82" stroke="#c7c5b7" stroke-width="2"/>
   </g>
   <text x="104" y="195" fill="#ded9c6" font-size="11" text-anchor="middle">m/s</text>
  </svg>
  <div class="rr-lamp" data-lock>NO RETURN</div>
 </div>
</div>
<div class="rr-switches">
<?php foreach(['radar'=>'RADAR','responder'=>'RESPONDER'] as $key=>$label): ?>
 <div class="rr-switch"><strong><?=$label?></strong><button type="button" data-unit="<?=$key?>" data-command="off">OFF</button><button type="button" data-unit="<?=$key?>" data-command="on">ON</button></div>
<?php endforeach; ?>
</div>
</div>
<script>
(()=>{
 const root=document.getElementById(<?=json_encode($rrId)?>),craft=Number(root.dataset.craft);
 const svgNS='http://www.w3.org/2000/svg';
 const ruler=root.querySelector('[data-ruler-marks]'),ticks=root.querySelector('[data-rate-marks]');
 const needle=root.querySelector('[data-needle]'),lamp=root.querySelector('[data-lock]');
 const svg=(tag,attrs,parent,text)=>{
  const el=document.createElementNS(svgNS,tag);
  for(const [k,v] of Object.entries(attrs))el.setAttribute(k,String(v));
  if(text!==undefined)el.textContent=text;
  parent.appendChild(el);return el;
 };
 // A compressed fixed scale: 40% of each half for 0–1, 32% for 1–10, 28% for 10–100.
 const fraction=rate=>{
  const a=Math.min(100,Math.abs(rate));
  return a<=1? .4*a : a<=10? .4+.32*(a-1)/9 : .72+.28*(a-10)/90;
 };
 const rateAngle=closing=>80*Math.sign(closing)*fraction(closing);
 // Permanent silk-screen graduations: moving needle is the only dynamic element.
 for(const side of [-1,1]){
  for(const val of [0.25,0.5,0.75,1,2,5,10,20,50,100]){
   const theta=(side*80*fraction(val))*Math.PI/180;
   const r1=val===1||val===10||val===100?78:83,r2=90;
   const x=104+Math.sin(theta)*r2,y=166-Math.cos(theta)*r2;
   const x1=104+Math.sin(theta)*r1,y1=166-Math.cos(theta)*r1;
   svg('line',{x1,y1,x2:x,y2:y,stroke:'#d9d4c2','stroke-width':val===1||val===10||val===100?2:1},ticks);
   if([1,10,100].includes(val)){
    svg('text',{x:104+Math.sin(theta)*105,y:169-Math.cos(theta)*105,fill:'#e8e0ca','font-size':10,'text-anchor':'middle'},ticks,String(val));
   }
  }
 }
 svg('line',{x1:104,y1:76,x2:104,y2:83,stroke:'#e0d8be','stroke-width':2},ticks);
 svg('text',{x:104,y:66,fill:'#e8e0ca','font-size':11,'text-anchor':'middle'},ticks,'0');
 // Physically moving range tape, with an always-fixed index line at the window center.
 // A decade-compressed scale spans the near-docking region and the 740 km radar limit.
 const tapeCoord=meters=>Math.log10(1+Math.max(0,meters));
 const major=[0,0.1,0.2,0.5,1,2,5,10,20,50,100,200,500,1000,2000,5000,10000,20000,50000,100000,200000,500000,740000];
 const format=v=>v>=1000?(v/1000).toLocaleString('en-US',{maximumFractionDigits:1})+' km':v.toLocaleString('en-US',{maximumFractionDigits:1})+' m';
 function moveTape(meters){
  ruler.replaceChildren();
  const pos=tapeCoord(meters),height=178,span=110; // units: pixels per logarithmic decade
  // Show only the marks physically inside the visible tape window.
  for(const v of major){
   const y=height/2+(tapeCoord(v)-pos)*span;
   if(y< -12||y>height+12)continue;
   svg('line',{x1:4,y1:y,x2:25,y2:y,stroke:'#dcd9cd','stroke-width':1.5},ruler);
   svg('text',{x:31,y:y+4,fill:'#e5e0d0','font-size':11,'font-family':'monospace'},ruler,format(v));
  }
 }
 moveTape(0);
 needle.style.visibility='hidden';
 let refreshing=false;
 async function refresh(){
  if(refreshing)return;refreshing=true;
  try{
   const response=await fetch('rendezvous_radar_status.php?craft='+craft,{cache:'no-store'});
   if(!response.ok)throw new Error('Radar status unavailable');
   const v=await response.json(),regs=v.registers||{};
   const range=regs.RENDEZVOUS_RANGE_M,rate=regs.RENDEZVOUS_RANGE_RATE_MPS;
   const valid=Boolean(range?.valid&&rate?.valid&&Number.isFinite(Number(range.value))&&Number.isFinite(Number(rate.value)));
   lamp.classList.toggle('lit',!valid);
   needle.style.visibility=valid?'visible':'hidden';
   if(valid){
    moveTape(Math.max(0,Number(range.value)));
    needle.style.transform='rotate('+rateAngle(Number(rate.value))+'deg)';
   }
   root.querySelectorAll('button[data-unit]').forEach(b=>b.classList.toggle('selected',(b.dataset.command==='on')===Boolean(v.power?.[b.dataset.unit])));
  }catch(e){lamp.classList.add('lit');needle.style.visibility='hidden';}
  finally{refreshing=false;}
 }
 root.querySelectorAll('button[data-unit]').forEach(button=>button.addEventListener('click',async()=>{
  try{
   const response=await fetch('rendezvous_radar_status.php?craft='+craft,{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({unit:button.dataset.unit,command:button.dataset.command})
   });
   if(!response.ok)throw new Error('Radar switch failed');
  }catch(e){lamp.classList.add('lit');}
  await refresh();
 }));
 refresh();
 const timer=setInterval(()=>{if(!document.hidden)refresh();},5000);
 window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
})();
</script>
