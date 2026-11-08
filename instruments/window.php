<?php
// A single reusable optical window. Its observer is supplied by console.php.
// All graphics are rendered here; the console page never reloads.
$windowId='optical-'.bin2hex(random_bytes(6));
?>
<div style="width:600px;height:600px;position:relative;background:#000;border:6px solid #444;box-sizing:border-box;overflow:hidden">
  <canvas id="<?=htmlspecialchars($windowId,ENT_QUOTES)?>" width="588" height="588" style="display:block;width:100%;height:100%"></canvas>
  <img src="textures/earth.jpg" alt="Earth texture diagnostic" title="Raw Earth texture (not rendered)" style="position:absolute;right:8px;bottom:8px;width:128px;height:64px;object-fit:fill;border:1px solid #b8d0a4;pointer-events:none" onerror="this.style.border='2px solid red';this.alt='EARTH IMAGE REQUEST FAILED'">
  <div id="<?=htmlspecialchars($windowId,ENT_QUOTES)?>-status" style="position:absolute;top:9px;left:9px;right:9px;color:#dfffcf;background:rgba(0,0,0,.7);padding:5px;font:13px monospace;pointer-events:none">3D WINDOW INITIALIZING…</div>
</div>
<script type="module">
const id=<?=json_encode($windowId)?>;
const status=document.getElementById(id+'-status');
import('./instruments/window3d.js').then(({startOpticalWindow})=>{
 startOpticalWindow(id,<?=(int)$observerId?>);
}).catch(err=>{
 status.textContent='3D MODULE FAILED — '+(err?.message||String(err));
 console.error('Moon 3D import error',err);
});
</script>
