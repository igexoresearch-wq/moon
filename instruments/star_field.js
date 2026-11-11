// Fixed-star optical projections. Always use physical attitude, NEVER IMU.
export const RAD=Math.PI/180;
export function starBasis(o){
 const p=Number(o.pitch||0)*RAD,y=Number(o.yaw||0)*RAD,r=Number(o.roll||0)*RAD;
 const f=[Math.cos(p)*Math.cos(y),Math.cos(p)*Math.sin(y),Math.sin(p)];
 const rr=[-Math.sin(y),Math.cos(y),0],uu=[-Math.sin(p)*Math.cos(y),-Math.sin(p)*Math.sin(y),Math.cos(p)];
 return [f,rr.map((v,i)=>v*Math.cos(r)+uu[i]*Math.sin(r)),uu.map((v,i)=>v*Math.cos(r)-rr[i]*Math.sin(r))];
}
export function starProject(direction,basis){
 const d=direction, dot=a=>a.reduce((s,v,i)=>s+v*d[i],0);
 const forward=dot(basis[0]);
 if(forward<=0)return null;
 const x=Math.atan2(dot(basis[1]),forward)/RAD;
 const y=Math.atan2(dot(basis[2]),forward)/RAD;
 if(Math.abs(x)>24.5||Math.abs(y)>24.5)return null;
 return {x,y};
}
export function starOcculted(dir,self,objects){
 for(const body of objects){
  if(![1,2].includes(Number(body.id)))continue;
  const v=[Number(body.x)-Number(self.x),Number(body.y)-Number(self.y),Number(body.z)-Number(self.z)];
  const range=Math.hypot(...v),radius=Number(body.radius_length);
  if(range<=radius)return true;
  if(v.reduce((sum,x,i)=>sum+x*dir[i],0)/range>Math.sqrt(Math.max(0,1-(radius/range)**2)))return true;
 }
 return false;
}
export function drawStarField(ctx,canvas,stars,self,objects,{clear=true}={}){
 if(clear)ctx.clearRect(0,0,canvas.width,canvas.height);
 const basis=starBasis(self),scale=canvas.width/49;
 let inFov=0,hidden=0,drawn=0,referenceVisible=0;
 for(const s of stars){
  const pos=starProject(s.vector,basis);
  if(!pos)continue;
  inFov++;
  if(starOcculted(s.vector,self,objects)){hidden++;continue;}
  drawn++;
  if(s.group==='TRIAD')referenceVisible++;
  const px=canvas.width/2+pos.x*scale,py=canvas.height/2-pos.y*scale;
  const bright=s.group==='TRIAD',radius=bright?3.5:s.group==='FIELD'?.5+Math.max(0,5-Number(s.magnitude))*.23:2.2;
  ctx.beginPath();ctx.arc(px,py,radius,0,2*Math.PI);
  ctx.fillStyle=bright?'#ffdf9f':'#e6efff';ctx.fill();
 }
 return {inFov,hidden,drawn,referenceVisible};
}
