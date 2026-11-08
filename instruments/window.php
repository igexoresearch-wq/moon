<?php
// A single reusable optical window. Its observer is supplied by console.php.
// All graphics are rendered here; the console page never reloads.
$windowId='optical-'.bin2hex(random_bytes(6));
?>
<div style="width:400px;height:400px;position:relative;background:#000;overflow:hidden;clip-path:polygon(0 75%,100% 25%,100% 100%,0 100%)">
  <canvas id="<?=htmlspecialchars($windowId,ENT_QUOTES)?>" width="400" height="400" style="display:block;width:400px;height:400px"></canvas>
  <svg viewBox="0 0 400 400" width="400" height="400" aria-hidden="true" style="position:absolute;inset:0;pointer-events:none">
    <!-- Four-sided sloping CM viewport border. -->
    <path d="M 0 300 L 400 100 L 400 400 L 0 400 Z" fill="none" stroke="#555" stroke-width="6"/>
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
import('./instruments/window3d.js').then(({startOpticalWindow})=>{
 startOpticalWindow(id,<?=(int)$observerId?>);
}).catch(err=>{
 status.textContent='3D MODULE FAILED — '+(err?.message||String(err));
 console.error('Moon 3D import error',err);
});
</script>
