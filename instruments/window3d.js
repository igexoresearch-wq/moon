/* Moon optical instrument: self-hosted WebGL, no third-party JavaScript imports. */
const G=()=>{};
const rad=x=>Number(x||0)*Math.PI/180;
const dot=(a,b)=>a[0]*b[0]+a[1]*b[1]+a[2]*b[2];
const sub=(a,b)=>[a[0]-b[0],a[1]-b[1],a[2]-b[2]];
const norm=v=>{const n=Math.hypot(...v)||1;return v.map(x=>x/n);};
const cross=(a,b)=>[a[1]*b[2]-a[2]*b[1],a[2]*b[0]-a[0]*b[2],a[0]*b[1]-a[1]*b[0]];
function basis(o){
 const p=rad(o.pitch),y=rad(o.yaw),r=rad(o.roll);
 const f=[Math.cos(p)*Math.cos(y),Math.cos(p)*Math.sin(y),Math.sin(p)];
 const right=[-Math.sin(y),Math.cos(y),0],up=cross(f,right);
 return {f,right:right.map((v,i)=>v*Math.cos(r)+up[i]*Math.sin(r)),up:up.map((v,i)=>v*Math.cos(r)-right[i]*Math.sin(r))};
}
function shader(gl,kind,src){const s=gl.createShader(kind);gl.shaderSource(s,src);gl.compileShader(s);if(!gl.getShaderParameter(s,gl.COMPILE_STATUS))throw Error(gl.getShaderInfoLog(s));return s;}
function program(gl){
 const vertex=`attribute vec3 aPos;attribute vec2 aUV;uniform vec3 uCenter;uniform float uRadius;uniform vec3 uRight,uUp,uForward;uniform float uFocal;varying vec2 vUV;varying vec3 vNormal;void main(){vec3 world=uCenter+aPos*uRadius;float depth=dot(world,uForward);float x=dot(world,uRight),y=dot(world,uUp);gl_Position=vec4(x*uFocal,y*uFocal,depth-0.002,depth);vUV=aUV;vNormal=aPos;}`;
 const fragment=`precision mediump float;varying vec2 vUV;varying vec3 vNormal;uniform sampler2D uTex;uniform vec3 uTint;uniform float uHasTex;void main(){vec3 base=mix(uTint,texture2D(uTex,vUV).rgb,uHasTex);float light=0.27+0.73*max(dot(normalize(vNormal),normalize(vec3(0.65,0.32,0.55))),0.0);gl_FragColor=vec4(base*light,1.0);}`;
 const p=gl.createProgram();gl.attachShader(p,shader(gl,gl.VERTEX_SHADER,vertex));gl.attachShader(p,shader(gl,gl.FRAGMENT_SHADER,fragment));gl.linkProgram(p);if(!gl.getProgramParameter(p,gl.LINK_STATUS))throw Error(gl.getProgramInfoLog(p));return p;
}
function sphereMesh(gl){
 const vertices=[],indices=[],lon=64,lat=40;
 for(let j=0;j<=lat;j++){const phi=j*Math.PI/lat;for(let i=0;i<=lon;i++){const theta=i*2*Math.PI/lon;vertices.push(Math.sin(phi)*Math.cos(theta),Math.cos(phi),Math.sin(phi)*Math.sin(theta),1-i/lon,j/lat);}}
 for(let j=0;j<lat;j++)for(let i=0;i<lon;i++){const a=j*(lon+1)+i,b=a+lon+1;indices.push(a,b,a+1,b,b+1,a+1);}
 const v=gl.createBuffer();gl.bindBuffer(gl.ARRAY_BUFFER,v);gl.bufferData(gl.ARRAY_BUFFER,new Float32Array(vertices),gl.STATIC_DRAW);
 const index=gl.createBuffer();gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER,index);gl.bufferData(gl.ELEMENT_ARRAY_BUFFER,new Uint16Array(indices),gl.STATIC_DRAW);
 return {v,index,count:indices.length};
}
function texture(gl,url){
 const t=gl.createTexture();gl.bindTexture(gl.TEXTURE_2D,t);gl.texImage2D(gl.TEXTURE_2D,0,gl.RGBA,1,1,0,gl.RGBA,gl.UNSIGNED_BYTE,new Uint8Array([170,180,190,255]));
 gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_S,gl.CLAMP_TO_EDGE);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_T,gl.CLAMP_TO_EDGE);
 gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MIN_FILTER,gl.LINEAR);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MAG_FILTER,gl.LINEAR);
 const result={t,ready:false,failed:false};
 const img=new Image();img.crossOrigin='anonymous';
 img.onload=()=>{try{gl.bindTexture(gl.TEXTURE_2D,t);gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL,true);gl.texImage2D(gl.TEXTURE_2D,0,gl.RGBA,gl.RGBA,gl.UNSIGNED_BYTE,img);result.ready=true;}catch(e){result.failed=true;}};
 img.onerror=()=>result.failed=true;img.src=url;return result;
}
export function startOpticalWindow(id,observerId){
 const canvas=document.getElementById(id),status=document.getElementById(id+'-status');if(!canvas)return;
 const gl=canvas.getContext('webgl',{antialias:true,alpha:false})||canvas.getContext('experimental-webgl');
 if(!gl){status.textContent='WEBGL NOT AVAILABLE';return;}
 let p,mesh;try{p=program(gl);mesh=sphereMesh(gl);}catch(e){status.textContent='SHADER ERROR: '+e.message;return;}
 gl.useProgram(p);gl.bindBuffer(gl.ARRAY_BUFFER,mesh.v);gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER,mesh.index);
 const attr=(name,size,offset)=>{const a=gl.getAttribLocation(p,name);gl.enableVertexAttribArray(a);gl.vertexAttribPointer(a,size,gl.FLOAT,false,20,offset);};
 attr('aPos',3,0);attr('aUV',2,12);
 const u=n=>gl.getUniformLocation(p,n);
 const U={center:u('uCenter'),radius:u('uRadius'),right:u('uRight'),up:u('uUp'),forward:u('uForward'),focal:u('uFocal'),tint:u('uTint'),tex:u('uTex'),has:u('uHasTex')};
 gl.uniform1f(U.focal,1);gl.uniform1i(U.tex,0);gl.clearColor(.005,.01,.02,1);
 const earth=texture(gl,'https://eoimages.gsfc.nasa.gov/images/imagerecords/57000/57730/land_ocean_ice_2048.jpg');
 const moon=texture(gl,'https://svs.gsfc.nasa.gov/vis/a000000/a004700/a004720/lroc_color_2k.jpg');
 const blank=texture(gl,'');
 let snapshot=null,last=0,busy=false;
 async function read(){if(busy)return;busy=true;try{const res=await fetch('state.php?observer='+observerId,{cache:'no-store'});if(!res.ok)throw Error('HTTP '+res.status);snapshot=await res.json();last=Date.now();}catch(e){status.textContent='TELEMETRY ERROR: '+e.message;}finally{busy=false;}}
 function render(){
  requestAnimationFrame(render);gl.viewport(0,0,canvas.width,canvas.height);gl.clear(gl.COLOR_BUFFER_BIT|gl.DEPTH_BUFFER_BIT);
  if(!snapshot)return;
  const objs=snapshot.objects||[],self=objs.find(o=>+o.id===observerId);if(!self){status.textContent='OBSERVER MISSING';return;}
  const axes=basis(self),position=[+self.x,+self.y,+self.z],items=[];
  const earthObj=objs.find(o=>+o.id===1);
  if(earthObj){const aim=norm(sub([+earthObj.x,+earthObj.y,+earthObj.z],position));const angle=Math.acos(Math.max(-1,Math.min(1,dot(axes.f,aim))))*180/Math.PI;status.textContent='EARTH '+angle.toFixed(0)+'° OFF AXIS · YAW '+Number(self.yaw).toFixed(0)+'°'+(earth.failed?' · EARTH TEXTURE FAILED':'');}
  if(Date.now()-last>5000)status.textContent='TELEMETRY STALE';
  for(const o of objs){if(+o.id===observerId)continue;const center=sub([+o.x,+o.y,+o.z],position).map(x=>x*.001),dist=Math.hypot(...center),radius=(+o.radius_length||1)*.001;if(!dist)continue;items.push({o,center,dist,radius});}
  items.sort((a,b)=>b.dist-a.dist);
  gl.disable(gl.DEPTH_TEST); // Distance order avoids severe depth precision loss across lunar distances.
  gl.uniform3fv(U.right,new Float32Array(axes.right));gl.uniform3fv(U.up,new Float32Array(axes.up));gl.uniform3fv(U.forward,new Float32Array(axes.f));
  for(const item of items){
   const k=+item.o.id,tex=k===1?earth:k===2?moon:blank;
   gl.activeTexture(gl.TEXTURE0);gl.bindTexture(gl.TEXTURE_2D,tex.t);
   gl.uniform1f(U.has,tex.ready?1:0);gl.uniform3fv(U.tint,new Float32Array(k===1?[.18,.37,.72]:k===2?[.65,.63,.60]:[.8,.8,.76]));
   gl.uniform3fv(U.center,new Float32Array(item.center));gl.uniform1f(U.radius,item.radius);
   gl.drawElements(gl.TRIANGLES,mesh.count,gl.UNSIGNED_SHORT,0);
  }
 }
 read();setInterval(read,1000);render();
}
