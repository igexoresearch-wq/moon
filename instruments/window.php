<?php
// A single reusable optical window. Its observer is supplied by console.php.
// All graphics are rendered here; the console page never reloads.
$windowId='optical-'.bin2hex(random_bytes(6));
?>
<div style="width:600px;height:600px;position:relative;background:#000;border:6px solid #444;box-sizing:border-box;overflow:hidden">
  <canvas id="<?=htmlspecialchars($windowId,ENT_QUOTES)?>" width="588" height="588" style="display:block;width:100%;height:100%"></canvas>
  <div id="<?=htmlspecialchars($windowId,ENT_QUOTES)?>-status" style="position:absolute;bottom:8px;left:10px;color:#9fb3a5;font:11px monospace;pointer-events:none"></div>
</div>
<script type="module">
import {startOpticalWindow} from './instruments/window3d.js';
startOpticalWindow(<?=json_encode($windowId)?>, <?=(int)$observerId?>);
</script>
