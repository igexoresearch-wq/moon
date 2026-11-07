/**
 * Shared optical viewport, single source for CM/LM imagery.
 * One scene, one camera, one WebGL render loop. Physics is authoritative.
 * Distances and radii are converted from meters to kilometers uniformly;
 * no arbitrary object enlargement or distance compression.
 */
import * as THREE from 'https://cdn.jsdelivr.net/npm/three@0.170.0/build/three.module.js';

const EARTH_TEXTURE='https://eoimages.gsfc.nasa.gov/images/imagerecords/57000/57730/land_ocean_ice_2048.jpg';
const MOON_TEXTURE='https://svs.gsfc.nasa.gov/vis/a000000/a004700/a004720/lroc_color_2k.jpg';
const SPEED_SCALE=0.001; // meters to scene km, same for radii and positions
const clamp=(n,a,b)=>Math.min(b,Math.max(a,n));
const number=(o,k)=>Number(o[k]??0);
function makeSpacecraft(id){
 const root=new THREE.Group();
 const aluminum=new THREE.MeshStandardMaterial({color:0xc1c8c9,metalness:.65,roughness:.45});
 const dark=new THREE.MeshStandardMaterial({color:0x343c45,metalness:.5,roughness:.65});
 // Deliberately simple geometric stand-ins until accurate vehicle models exist.
 if(id===3){const capsule=new THREE.Mesh(new THREE.ConeGeometry(.00195,.0039,12),aluminum);capsule.rotation.z=-Math.PI/2;root.add(capsule);}
 else if(id===4){const cylinder=new THREE.Mesh(new THREE.CylinderGeometry(.0035,.0035,.0075,14),aluminum);cylinder.rotation.z=Math.PI/2;root.add(cylinder);}
 else {const body=new THREE.Mesh(new THREE.CylinderGeometry(.002,.003,.005,10),dark);body.rotation.z=Math.PI/2;root.add(body);}
 return root;
}
function windowBasis(self){
 const p=THREE.MathUtils.degToRad(number(self,'pitch'));
 const y=THREE.MathUtils.degToRad(number(self,'yaw'));
 const r=THREE.MathUtils.degToRad(number(self,'roll'));
 const f=new THREE.Vector3(Math.cos(p)*Math.cos(y),Math.cos(p)*Math.sin(y),Math.sin(p));
 const right=new THREE.Vector3(-Math.sin(y),Math.cos(y),0);
 const up=new THREE.Vector3().crossVectors(f,right).normalize();
 right.applyAxisAngle(f,r);up.applyAxisAngle(f,r);
 return {f,up};
}
export function startOpticalWindow(canvasId,observerId){
 const canvas=document.getElementById(canvasId);
 const status=document.getElementById(canvasId+'-status');
 if(!canvas)return;
 let renderer;
 try{renderer=new THREE.WebGLRenderer({canvas,antialias:true,alpha:false,logarithmicDepthBuffer:true});}
 catch(err){status.textContent='WEBGL UNAVAILABLE';return;}
 renderer.setPixelRatio(Math.min(window.devicePixelRatio||1,2));
 renderer.setSize(588,588,false);
 renderer.outputColorSpace=THREE.SRGBColorSpace;
 const scene=new THREE.Scene();scene.background=new THREE.Color(0x010205);
 const camera=new THREE.PerspectiveCamera(90,1,0.00001,1000000);
 camera.position.set(0,0,0);
 scene.add(new THREE.AmbientLight(0xffffff,.30));
 const sun=new THREE.DirectionalLight(0xffffff,2.0);sun.position.set(1,0.2,0.35);scene.add(sun);
 const meshes=new Map();
 const texLoader=new THREE.TextureLoader();texLoader.setCrossOrigin('anonymous');
 function celestialTexture(url,material){
  texLoader.load(url,tex=>{tex.colorSpace=THREE.SRGBColorSpace;material.map=tex;material.needsUpdate=true;},undefined,
   ()=>{status.textContent='TEXTURE UNAVAILABLE — USING COLOR FALLBACK';});
 }
 function meshFor(obj){
  const id=number(obj,'id');
  if(meshes.has(id))return meshes.get(id);
  let mesh;
  if(id===1||id===2){
   const material=new THREE.MeshStandardMaterial({color:id===1?0x658db9:0xaaa69a,roughness:1});
   celestialTexture(id===1?EARTH_TEXTURE:MOON_TEXTURE,material);
   mesh=new THREE.Mesh(new THREE.SphereGeometry(1,96,64),material);
  }else mesh=makeSpacecraft(id);
  scene.add(mesh);meshes.set(id,mesh);return mesh;
 }
 let current=null,next=null,lastReceived=0,requesting=false,disposed=false;
 const vec=o=>new THREE.Vector3(number(o,'x'),number(o,'y'),number(o,'z'));
 function applySnapshot(snapshot){
  const objects=snapshot.objects||[];
  const self=objects.find(o=>number(o,'id')===observerId);
  if(!self){status.textContent='OBSERVER NOT FOUND';return;}
  const origin=vec(self);
  const seen=new Set();
  for(const obj of objects){
   const id=number(obj,'id');if(id===observerId)continue;
   const mesh=meshFor(obj);seen.add(id);
   const relative=vec(obj).sub(origin).multiplyScalar(SPEED_SCALE);
   mesh.position.copy(relative);
   if(id===1||id===2){const radius=number(obj,'radius_length')*SPEED_SCALE;mesh.scale.setScalar(radius);}
   else{
    // Spacecraft geometric stand-ins use kilometers natively.
    mesh.rotation.set(THREE.MathUtils.degToRad(number(obj,'roll')),THREE.MathUtils.degToRad(number(obj,'pitch')),THREE.MathUtils.degToRad(number(obj,'yaw')));
   }
   mesh.visible=true;
  }
  for(const [id,mesh] of meshes)if(!seen.has(id))mesh.visible=false;
  const {f,up}=windowBasis(self);
  camera.up.copy(up);
  camera.lookAt(f);
  const earth=objects.find(o=>number(o,'id')===1);
  if(earth){
   const earthDirection=vec(earth).sub(origin).normalize();
   const separation=THREE.MathUtils.radToDeg(Math.acos(clamp(f.dot(earthDirection),-1,1)));
   const earthVisible=separation<45+THREE.MathUtils.radToDeg(Math.asin(clamp(number(earth,'radius_length')/Math.max(1,vec(earth).distanceTo(origin)),0,1)));
   status.textContent='YAW '+number(self,'yaw').toFixed(1)+'° · EARTH '+separation.toFixed(0)+'° OFF-AXIS'+(earthVisible?' · IN VIEW':' · OUT OF VIEW');
  }
 }
 async function fetchState(){
  if(requesting)return;requesting=true;
  try{
   const response=await fetch('state.php?observer='+observerId,{cache:'no-store'});
   if(!response.ok)throw new Error('Telemetry HTTP '+response.status);
   const data=await response.json();
   current=data;lastReceived=performance.now();
  }catch(error){status.textContent='TELEMETRY OFFLINE';console.warn('Optical window:',error);}
  finally{requesting=false;}
 }
 function animate(){
  if(disposed)return;
  requestAnimationFrame(animate);
  if(current)applySnapshot(current);
  renderer.render(scene,camera);
  if(current && performance.now()-lastReceived>5000)status.textContent='TELEMETRY STALE';
 }
 fetchState();const timer=setInterval(fetchState,1000);animate();
 // Avoid leaks when an instrument is removed via navigation.
 window.addEventListener('pagehide',()=>{disposed=true;clearInterval(timer);renderer.dispose();},{once:true});
}
