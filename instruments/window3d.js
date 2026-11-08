/* Moon optical viewport — local WebGL ray/sphere renderer.
 * Rays cast from the spacecraft through each pixel; planet-size objects can
 * extend past the viewport without near-plane clipping.
 * Positions/radii both converted m -> km, no scale cheats.
 */
const R=Math.PI/180;
function cameraBasis(o){
 const pitch=Number(o.pitch||0)*R,yaw=Number(o.yaw||0)*R,roll=Number(o.roll||0)*R;
 const forward=[Math.cos(pitch)*Math.cos(yaw),Math.cos(pitch)*Math.sin(yaw),Math.sin(pitch)];
 const right0=[-Math.sin(yaw),Math.cos(yaw),0];
 const up0=[-Math.sin(pitch)*Math.cos(yaw),-Math.sin(pitch)*Math.sin(yaw),Math.cos(pitch)];
 return {forward,right:right0.map((v,i)=>v*Math.cos(roll)+up0[i]*Math.sin(roll)),
 up:up0.map((v,i)=>v*Math.cos(roll)-right0[i]*Math.sin(roll))};
}
function compile(gl,type,code){const s=gl.createShader(type);gl.shaderSource(s,code);gl.compileShader(s);
 if(!gl.getShaderParameter(s,gl.COMPILE_STATUS))throw Error(gl.getShaderInfoLog(s));return s;}
const VS='attribute vec2 aPoint;varying vec2 vPoint;void main(){vPoint=aPoint;gl_Position=vec4(aPoint,0.,1.);}';
const FS=`precision highp float;
varying vec2 vPoint;
uniform vec3 uForward,uRight,uUp;
uniform vec3 uCenters[16],uColors[16];
uniform float uRadii[16];
uniform int uCount;
uniform sampler2D uEarth,uMoon;
uniform float uEarthReady,uMoonReady;
const float FAR=1.0e12;
void main(){
 vec3 direction=normalize(uForward+vPoint.x*uRight+vPoint.y*uUp);
 float nearest=FAR;int hit=-1;vec3 normal=vec3(0.);
 for(int i=0;i<16;i++){
  if(i>=uCount)break;
  vec3 center=uCenters[i];float radius=uRadii[i];
  float proj=dot(center,direction);
  float discr=proj*proj-dot(center,center)+radius*radius;
  if(discr<0.)continue;
  float root=sqrt(discr);
  float dist=proj-root;
  if(dist<=0.)dist=proj+root;
  if(dist>0. && dist<nearest){
   nearest=dist;hit=i;normal=normalize(direction*dist-center);
  }
 }
 if(hit<0){gl_FragColor=vec4(0.002,0.004,0.009,1.);return;}
 vec3 color=vec3(0.7);
 for(int i=0;i<16;i++){if(i==hit){color=uColors[i];break;}}
 float longitude=atan(normal.z,normal.x);
 float latitude=asin(clamp(normal.y,-1.,1.));
 vec2 uv=vec2(fract(0.5+longitude/6.28318530718),clamp(0.5-latitude/3.14159265359,0.001,0.999));
 // In WebGL 1 both samplers must be evaluated in uniform control flow.
 // Sampling inside per-pixel hit branches can give undefined texture results.
 vec3 earthPixel=texture2D(uEarth,uv).rgb;
 vec3 moonPixel=texture2D(uMoon,uv).rgb;
 if(hit==0 && uEarthReady>0.5)color=earthPixel;
 else if(hit==1 && uMoonReady>0.5)color=moonPixel;
 // Diagnostic: display the actual texture without artificial dark-side shading.
 gl_FragColor=vec4(color,1.);
}`;
function loadImageTexture(gl,path,unit){
 const result={ready:false,error:false,texture:null};
 const image=new Image();
 const tex=gl.createTexture();result.texture=tex;gl.activeTexture(gl.TEXTURE0+unit);gl.bindTexture(gl.TEXTURE_2D,tex);
 gl.texImage2D(gl.TEXTURE_2D,0,gl.RGBA,1,1,0,gl.RGBA,gl.UNSIGNED_BYTE,new Uint8Array([150,160,170,255]));
 gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_S,gl.CLAMP_TO_EDGE);
 gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_T,gl.CLAMP_TO_EDGE);
 gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MIN_FILTER,gl.LINEAR);
 gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MAG_FILTER,gl.LINEAR);
 image.onload=()=>{gl.activeTexture(gl.TEXTURE0+unit);gl.bindTexture(gl.TEXTURE_2D,tex);gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL,true);
  // Normalize the browser-decoded image through a 2D canvas. This removes
  // JPEG decoding / color-space upload differences between drivers.
  const surface=document.createElement('canvas');surface.width=1024;surface.height=512;
  const ctx=surface.getContext('2d',{willReadFrequently:true});
  if(!ctx){result.error=true;return;}
  ctx.drawImage(image,0,0,surface.width,surface.height);
  gl.pixelStorei(gl.UNPACK_COLORSPACE_CONVERSION_WEBGL,gl.NONE);
  gl.texImage2D(gl.TEXTURE_2D,0,gl.RGBA,gl.RGBA,gl.UNSIGNED_BYTE,surface);
  const err=gl.getError();if(err!==gl.NO_ERROR){result.error=true;result.errorCode=err;return;}
  result.ready=true;};
 image.onerror=()=>{result.error=true;};
 image.src=path;return result;
}
export function startOpticalWindow(canvasId,observerId){
 const canvas=document.getElementById(canvasId),status=document.getElementById(canvasId+'-status');
 if(!canvas)return;
 const gl=canvas.getContext('webgl',{alpha:false,antialias:false});
 if(!gl){status.textContent='WEBGL NOT AVAILABLE';return;}
 let program;
 try{program=gl.createProgram();gl.attachShader(program,compile(gl,gl.VERTEX_SHADER,VS));
 gl.attachShader(program,compile(gl,gl.FRAGMENT_SHADER,FS));gl.linkProgram(program);
 if(!gl.getProgramParameter(program,gl.LINK_STATUS))throw Error(gl.getProgramInfoLog(program));
 }catch(e){status.textContent='SHADER ERROR: '+e.message;return;}
 gl.useProgram(program);
 const quad=gl.createBuffer();gl.bindBuffer(gl.ARRAY_BUFFER,quad);
 gl.bufferData(gl.ARRAY_BUFFER,new Float32Array([-1,-1,1,-1,-1,1,1,1]),gl.STATIC_DRAW);
 const loc=gl.getAttribLocation(program,'aPoint');gl.enableVertexAttribArray(loc);gl.vertexAttribPointer(loc,2,gl.FLOAT,false,0,0);
 const u=name=>gl.getUniformLocation(program,name);
 const U={forward:u('uForward'),right:u('uRight'),up:u('uUp'),centers:u('uCenters[0]'),colors:u('uColors[0]'),radii:u('uRadii[0]'),count:u('uCount'),earthReady:u('uEarthReady'),moonReady:u('uMoonReady')};
 gl.uniform1i(u('uEarth'),0);gl.uniform1i(u('uMoon'),1);
 const earth=loadImageTexture(gl,'textures/earth.jpg',0),moon=loadImageTexture(gl,'textures/moon.jpg',1);
 let state=null,loading=false,lastUpdate=0;
 async function poll(){if(loading)return;loading=true;
 try{const res=await fetch('state.php?observer='+observerId,{cache:'no-store'});if(!res.ok)throw Error('HTTP '+res.status);
 state=await res.json();lastUpdate=Date.now();}
 catch(e){status.textContent='TELEMETRY ERROR: '+e.message;}finally{loading=false;}}
 function render(){
  if(document.hidden){setTimeout(render,300);return;}
  setTimeout(render,100);if(!state)return;
  const objects=state.objects||[],self=objects.find(x=>Number(x.id)===observerId);
  if(!self){status.textContent='OBSERVER MISSING';return;}
  if(Number(self.active)!==1){
   gl.viewport(0,0,canvas.width,canvas.height);
   gl.clearColor(0.002,0.004,0.009,1);gl.clear(gl.COLOR_BUFFER_BIT);
   status.textContent='SPACECRAFT INACTIVE — NO LIVE WINDOW VIEW';
   return;
  }
  const basis=cameraBasis(self),centers=[],colors=[],radii=[];
  // Always reserve texture slots 0/1 for Earth/Moon. Objects not present
  // in telemetry are not added, and slots are not inferred from shader IDs.
  // The CM camera is inside the attached SM's coarse collision sphere.
  // Exclude any small spacecraft component enclosing the observer; otherwise
  // its solid color masks Earth regardless of a valid Earth texture.
  const visible=objects.filter(x=>{
   if(Number(x.id)===observerId)return false;
   const distance=Math.hypot(Number(x.x)-Number(self.x),Number(x.y)-Number(self.y),Number(x.z)-Number(self.z));
   if(Number(x.id)>2 && distance<=Math.max(0,Number(x.radius_length)))return false;
   return true;
  }).sort((a,b)=>Number(a.id)-Number(b.id)).slice(0,16);
  for(const object of visible){
   centers.push((Number(object.x)-Number(self.x))*.001,(Number(object.y)-Number(self.y))*.001,(Number(object.z)-Number(self.z))*.001);
   radii.push(Math.max(.0001,Number(object.radius_length)*.001));
   const c=Number(object.id)===1?[.16,.38,.8]:Number(object.id)===2?[.72,.7,.67]:[.8,.8,.75];
   colors.push(...c);
  }
  // Shader needs Earth at slot 0, Moon at slot 1, irrespective of other objects.
  const earthObj=visible.find(x=>Number(x.id)===1),moonObj=visible.find(x=>Number(x.id)===2);
  const earthVector=earthObj?[Number(earthObj.x)-Number(self.x),Number(earthObj.y)-Number(self.y),Number(earthObj.z)-Number(self.z)]:null;
  let angle=null;
  if(earthVector){const len=Math.hypot(...earthVector);
   if(len>1){const dp=earthVector.reduce((sum,x,i)=>sum+x*basis.forward[i],0)/len;
    angle=Math.acos(Math.max(-1,Math.min(1,dp)))*180/Math.PI;}}
  gl.viewport(0,0,canvas.width,canvas.height);gl.useProgram(program);
  gl.uniform3fv(U.forward,new Float32Array(basis.forward));gl.uniform3fv(U.right,new Float32Array(basis.right));gl.uniform3fv(U.up,new Float32Array(basis.up));
  gl.uniform3fv(U.centers,new Float32Array(centers));gl.uniform3fv(U.colors,new Float32Array(colors));
  gl.uniform1fv(U.radii,new Float32Array(radii));gl.uniform1i(U.count,visible.length);
  gl.uniform1f(U.earthReady,earth.ready?1:0);gl.uniform1f(U.moonReady,moon.ready?1:0);
  // Rebind textures explicitly before rendering; never rely on the active
  // texture state left over from asynchronous image onload handlers.
  gl.activeTexture(gl.TEXTURE0);gl.bindTexture(gl.TEXTURE_2D,earth.texture);
  gl.activeTexture(gl.TEXTURE1);gl.bindTexture(gl.TEXTURE_2D,moon.texture);
  gl.drawArrays(gl.TRIANGLE_STRIP,0,4);
  const error=gl.getError();
  if(error!==gl.NO_ERROR){status.textContent='WEBGL ERROR '+error;return;}
  let geometry='EARTH ROW MISSING';
  if(earthObj){
   const d=Math.hypot(...earthVector),radius=Number(earthObj.radius_length);
   const angularRadius=d>radius?Math.asin(Math.min(1,radius/d))*180/Math.PI:90;
   const altitude=d-radius;
   const pointing=angle!==null && angle<angularRadius;
   // An independent JavaScript ray/sphere check; if it says HIT while the
   // window is blank, the fault is confined to the GPU shader/draw pipeline.
   const forward=basis.forward,ray=earthVector.map(v=>v*.001);
   const projection=ray.reduce((a,v,i)=>a+v*forward[i],0);
   const discriminant=projection*projection-ray.reduce((a,v)=>a+v*v,0)+(radius*.001)**2;
   geometry='ALT '+Math.round(altitude/1000)+'km · DISC '+Math.round(discriminant)+
      ' · '+(pointing?'CENTER RAY INSIDE DISK':'CENTER RAY OUTSIDE DISK')+
      ' · HALF ANGLE '+angularRadius.toFixed(0)+'°';
  }
  status.textContent=(angle===null?'NO EARTH':('EARTH '+angle.toFixed(0)+'° OFF AXIS'))+
   ' · UNLIT TEXTURE TEST · '+geometry+
   (earth.ready?' · TEXTURE OK':earth.error?' · EARTH TEXTURE FAILED':' · TEXTURE LOADING')+
   (Date.now()-lastUpdate>5000?' · TELEMETRY STALE':'');
 }
 poll();const timer=setInterval(poll,1000);render();
 window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
}
