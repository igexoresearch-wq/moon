<?php
// Reusable cockpit instrument: observerId comes from the containing station.
$windowId='moon-window-'.bin2hex(random_bytes(5));
?>
<div style="width:600px;height:600px;background:#03060b;border:6px solid #404347;box-sizing:border-box;position:relative;overflow:hidden">
<canvas id="<?=htmlspecialchars($windowId)?>" width="600" height="600" style="width:600px;height:600px;display:block"></canvas>
</div>
<script>
(()=> {
 const canvas=document.getElementById(<?=json_encode($windowId)?>),ctx=canvas.getContext('2d');
 const observerId=<?=(int)$observerId?>;
 const fov=Math.PI/2, focal=300/Math.tan(fov/2);
 function draw(data){
  ctx.fillStyle='#020409';ctx.fillRect(0,0,600,600);
  const objects=data.objects.map(o=>Object.fromEntries(Object.entries(o).map(([k,v])=>[k,k==='name'?v:Number(v)])));
  const self=objects.find(o=>o.id===observerId);if(!self)return;
  const yaw=self.yaw*Math.PI/180,pitch=self.pitch*Math.PI/180,roll=self.roll*Math.PI/180;
  // Local camera optical axis +X; +Y right and +Z up at zero attitude.
  const f=[Math.cos(pitch)*Math.cos(yaw),Math.cos(pitch)*Math.sin(yaw),Math.sin(pitch)];
  const right0=[-Math.sin(yaw),Math.cos(yaw),0],up0=[-Math.sin(pitch)*Math.cos(yaw),-Math.sin(pitch)*Math.sin(yaw),Math.cos(pitch)];
  const right=right0.map((v,i)=>v*Math.cos(roll)+up0[i]*Math.sin(roll));
  const up=up0.map((v,i)=>v*Math.cos(roll)-right0[i]*Math.sin(roll));
  const dot=(a,b)=>a.reduce((sum,v,i)=>sum+v*b[i],0);
  const bodies=[];
  for(const obj of objects){if(obj.id===self.id)continue;
   let rel=[obj.x-self.x,obj.y-self.y,obj.z-self.z],dist=Math.hypot(...rel);if(dist<=0)continue;
   const z=dot(rel,f),rightM=dot(rel,right),upM=dot(rel,up);
   if(z+obj.radius_length<=0)continue;
   const x=300+focal*rightM/Math.max(1,z),y=300-focal*upM/Math.max(1,z);
   const radius=Math.max(1,Math.min(3000,focal*obj.radius_length/Math.max(1,z)));
   bodies.push({obj,dist,x,y,radius});
  }
  bodies.sort((a,b)=>b.dist-a.dist);
  for(const b of bodies){
   let color=b.obj.id===1?'#467ac1':b.obj.id===2?'#b6b7b4':'#d8d0bd';
   ctx.fillStyle=color;ctx.beginPath();ctx.arc(b.x,b.y,b.radius,0,2*Math.PI);ctx.fill();
   ctx.fillStyle='white';ctx.font='13px monospace';
   ctx.fillText(b.obj.name,b.x+Math.min(b.radius,40)+6,b.y);
  }
  ctx.strokeStyle='#6c8c7d';ctx.beginPath();ctx.moveTo(287,300);ctx.lineTo(313,300);ctx.moveTo(300,287);ctx.lineTo(300,313);ctx.stroke();
 }
 async function refresh(){try{
  const response=await fetch('../state.php?observer='+observerId,{cache:'no-store'});
  if(response.ok)draw(await response.json());
 }catch(e){console.warn('Window telemetry unavailable',e)}}
 refresh();setInterval(refresh,1000);
})();
</script>
