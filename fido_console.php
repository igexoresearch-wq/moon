<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
// The database contains only button metadata and filenames. It never executes stored PHP/HTML.
$conn->query("CREATE TABLE IF NOT EXISTS fido_displays (
 display_key VARCHAR(40) NOT NULL PRIMARY KEY,
 button_label VARCHAR(32) NOT NULL,
 page_file VARCHAR(80) NOT NULL,
 sort_order SMALLINT UNSIGNED NOT NULL,
 enabled TINYINT(1) NOT NULL DEFAULT 1,
 is_default TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conn->query("INSERT INTO fido_displays(display_key,button_label,page_file,sort_order,enabled,is_default) VALUES
 ('summary','001 TRAJECTORY','summary.php',10,1,1),
 ('orbit','002 ORBITAL DATA','orbit.php',20,1,0),
 ('state','003 POWERED FLIGHT','state.php',30,1,0),
 ('rendezvous','004 RENDEZVOUS','rendezvous.php',40,1,0),
 ('navigation','005 GROUND NAV','navigation.php',50,1,0)
 ON DUPLICATE KEY UPDATE button_label=VALUES(button_label),page_file=VALUES(page_file),sort_order=VALUES(sort_order),is_default=VALUES(is_default)");
$displays=$conn->query("SELECT display_key,button_label,page_file,is_default FROM fido_displays WHERE enabled=1 ORDER BY sort_order,display_key")->fetch_all(MYSQLI_ASSOC);
$allowed=[];
foreach($displays as $entry){
 $name=(string)$entry['page_file'];
 if(!preg_match('/\A[a-z][a-z0-9_]*\.php\z/i',$name)||$name[0]==='_')continue;
 if(!is_file(__DIR__.'/fido_pages/'.$name))continue;
 $allowed[$entry['display_key']]=$entry;
}
$default='summary';
foreach($allowed as $key=>$entry)if((int)$entry['is_default']===1){$default=$key;break;}
if(!isset($allowed[$default]))$default=(string)(array_key_first($allowed)??'');
$chosen=(string)($_GET['display']??$default);
if(!isset($allowed[$chosen]))$chosen=$default;
?>
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>FIDO / Flight Dynamics</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#595d57;color:#dfe4de;font:14px Arial,sans-serif}
a{color:inherit}.top{background:#313932;padding:10px 16px}.work{display:flex;align-items:flex-start;gap:12px;padding:14px;min-width:max-content}
.top{display:flex;align-items:center;justify-content:space-between;gap:14px}.controls{width:172px;flex:none;background:#363d38;border:5px ridge #6e756d;padding:10px;min-height:800px}
.controls h2{font-size:12px;letter-spacing:2px;text-align:center;margin:0 0 18px}
.controls button{display:block;width:100%;margin:0 0 9px;padding:14px 7px;background:#838b80;border:3px outset #b1b6ab;color:#151c17;text-align:left;font:700 12px monospace;cursor:pointer}
.controls button.active{background:#cbc9aa;border-style:inset}.controls p{font:10px monospace;color:#b9c9bd;line-height:1.6;margin-top:20px}
.housing{border:10px ridge #3b443e;background:#121d28;padding:5px;flex:none}
.screen{display:block;width:1000px;height:800px;border:0;background:#071924}
</style></head><body>
<header class="top"><span>FLIGHT DYNAMICS / MISSION CONTROL · <a href="index.php">MENU</a></span><?php $metCompact=true;include __DIR__.'/instruments/met_clock.php';unset($metCompact);?></header>
<main class="work"><nav class="controls" aria-label="FIDO display selector"><h2>DISPLAY SELECT</h2>
<?php foreach($allowed as $key=>$entry):?>
<button type="button" data-key="<?=htmlspecialchars($key,ENT_QUOTES)?>" class="<?= $chosen===$key?'active':'' ?>"><?=htmlspecialchars($entry['button_label'])?></button>
<?php endforeach;?>
<p>RTCC DISPLAY UNIT<br>SELECT DISPLAY FORMAT</p>
</nav>
<div class="housing"><iframe class="screen" id="fido-screen" title="Flight dynamics display" src="fido_page.php?display=<?=urlencode($chosen)?>"></iframe></div>
</main>
<script>
(()=>{
 const screen=document.getElementById('fido-screen');
 const buttons=[...document.querySelectorAll('.controls button[data-key]')];
 for(const b of buttons)b.addEventListener('click',()=>{
  const key=b.dataset.key;
  buttons.forEach(button=>button.classList.toggle('active',button===b));
  screen.src='fido_page.php?display='+encodeURIComponent(key);
  history.replaceState(null,'','?display='+encodeURIComponent(key));
 });
})();
</script></body></html>
