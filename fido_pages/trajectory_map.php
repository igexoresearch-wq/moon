<?php
require_once __DIR__.'/../fido_projection.php';
$mode=(string)($_GET['mode']??'earth');
if(!in_array($mode,['earth','moon','system'],true))$mode='earth';
$center=$mode==='moon'?2:1;
try{
 $cm=fidoMapCoast($estimates,3);$lm=fidoMapCoast($estimates,6);
 $same=rtccNorm(rtccSub(rtccState($estimates[3])['p'],rtccState($estimates[6])['p']))<10.
  &&rtccNorm(rtccSub(rtccState($estimates[3])['v'],rtccState($estimates[6])['v']))<0.01;
 $paths=[3=>$cm];if(!$same)$paths[6]=$lm;
}catch(Throwable $e){echo '<h2>006 / ORBIT MAP</h2><p>'.htmlspecialchars($e->getMessage()).'</p>';return;}
?>
<h2>006 / FIDO PREDICTED TRAJECTORIES</h2>
<p class="fd-note">VIEW:
<a style="color:#b4e4cf" href="fido_page.php?display=trajectory_map&amp;mode=earth">EARTH</a> /
<a style="color:#b4e4cf" href="fido_page.php?display=trajectory_map&amp;mode=moon">MOON</a> /
<a style="color:#b4e4cf" href="fido_page.php?display=trajectory_map&amp;mode=system">EARTH–MOON SYSTEM</a></p>
<?=fidoMapSvg($estimates,$paths,$center,1240,555,$mode==='system')?>
<p class="fd-note">DASHED GOLD: CSM / DOTTED CYAN: LM. DOTS MARK CURRENT ESTIMATED POSITIONS.
<?= $same?'COINCIDENT ESTIMATES, ONE TRAJECTORY DRAWN.':'TWO SEPARATE ESTIMATED TRAJECTORIES.' ?>
Projection uses the FIDO ground-state epoch, including Earth and moving Moon gravity. This is a 2D view of 3D motion, not a scaled spacecraft drawing. Approximate local scale is printed on the plot; 10 m cannot be resolved at Earth–Moon scale.</p>
<div class="fd-grid">
<?php foreach([3=>$cm,6=>$lm] as $id=>$path):?>
<section><h3><?=$id===3?'CSM':'LM'?> / <?=htmlspecialchars($estimates[$path['body']]['name'])?></h3>
<?php fidoRows([
 'DYNAMIC REFERENCE'=>$estimates[$path['body']]['name'],
 'TRAJECTORY'=>$path['bound']?'BOUND / ONE ORBIT':'UNBOUND / FINITE COAST',
 'ORBIT PERIOD SEC'=>$path['period_s']===null?'---':fidoN($path['period_s'],1),
 'PROJECTED DURATION SEC'=>fidoN($path['horizon_s'],0),
 'ENDING CONDITION'=>$path['termination'],
 'GROUND TRACKING STATUS'=>$estimates[$id]['status']
]);?></section>
<?php endforeach;?>
</div>
<p class="fd-note">Prediction stops after one bounded two-body osculating orbital period, a collision, a lunar far-side threshold, or a capped sampling horizon. It is advisory and assumes no future engine burn. Osculating bound classification is approximate in the Earth–Moon three-body system.</p>
