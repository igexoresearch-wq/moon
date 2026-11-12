<?php
// Embeddable Mission Elapsed Time clock. Works as a draggable cockpit
// instrument AND a compact Mission Control FIDO header widget.
$metId='met-'.bin2hex(random_bytes(5));
$metCompact=($metCompact??false)===true;
$metInitial='---:--:--';
try{
 if(isset($conn)&&$conn instanceof mysqli){
  require_once __DIR__.'/../met_clock.php';
  $metRow=$conn->query('SELECT tick FROM sim_clock WHERE id=1')->fetch_assoc();
  if($metRow!==null)$metInitial=moonMetFormat((int)$metRow['tick']);
 }
}catch(Throwable $metError){error_log('Moon MET initial snapshot: '.$metError->getMessage());}
?>
<div id="<?=htmlspecialchars($metId,ENT_QUOTES)?>" class="moon-met-clock <?=$metCompact?'moon-met-compact':''?>" role="timer" aria-label="Mission elapsed time">
<style>
#<?=htmlspecialchars($metId)?>{box-sizing:border-box;width:266px;padding:9px 11px;border:5px ridge #575c52;background:#414841;color:#e9eade;text-align:center;font:11px Arial,sans-serif}
#<?=htmlspecialchars($metId)?> .met-label{font:700 11px Arial,sans-serif;letter-spacing:2px;color:#dfe5d8;margin:0 0 6px}
#<?=htmlspecialchars($metId)?> .met-face{background:#0d170f;color:#bae1b6;border:4px inset #737e6a;font:26px 'Courier New',monospace;letter-spacing:1px;padding:8px 4px;white-space:nowrap;font-variant-numeric:tabular-nums}
#<?=htmlspecialchars($metId)?> .met-units{display:grid;grid-template-columns:1fr 1fr 1fr;font:9px monospace;letter-spacing:1px;padding:4px 6px 0;color:#b9c9b4}
#<?=htmlspecialchars($metId)?> .met-status{font:9px monospace;min-height:12px;margin-top:5px;color:#d2c2a4}
#<?=htmlspecialchars($metId)?>.moon-met-compact{width:230px;padding:3px 7px;border-width:2px;display:inline-flex;align-items:center;gap:8px;vertical-align:middle}
#<?=htmlspecialchars($metId)?>.moon-met-compact .met-label{font-size:10px;margin:0;white-space:nowrap}
#<?=htmlspecialchars($metId)?>.moon-met-compact .met-face{font-size:16px;border-width:2px;padding:3px}
#<?=htmlspecialchars($metId)?>.moon-met-compact .met-units{display:none}
#<?=htmlspecialchars($metId)?>.moon-met-compact .met-status{display:block;font-size:8px;margin:0;max-width:125px}
</style>
<div class="met-label">MISSION ELAPSED TIME</div>
<div class="met-face" data-met-display><?=htmlspecialchars($metInitial,ENT_QUOTES)?></div>
<div class="met-units"><span>HOURS</span><span>MINUTES</span><span>SECONDS</span></div>
<div class="met-status" data-met-status>SYNCING</div>
</div>
<script>
(()=>{
 const panel=document.getElementById(<?=json_encode($metId)?>);
 if(!panel)return;
 const display=panel.querySelector('[data-met-display]');
 const status=panel.querySelector('[data-met-status]');
 let busy=false,lastTick=null;
 async function poll(){
  if(busy)return;
  busy=true;
  try{
   const res=await fetch('met_status.php',{cache:'no-store',credentials:'same-origin'});
   if(res.redirected)throw Error('LOGIN REQUIRED / RELOAD');
   const kind=res.headers.get('content-type')||'';
   if(!kind.includes('application/json'))throw Error('MET HTTP '+res.status+' / NON-JSON');
   const data=await res.json();
   if(!res.ok)throw Error(data.error||'MET HTTP '+res.status);
   const tick=typeof data.tick==='string'&&/^\d+$/.test(data.tick)?Number(data.tick):data.tick;
   if(!Number.isSafeInteger(tick)||tick<0||typeof data.met!=='string')
    throw Error('INVALID MET RESPONSE');
   display.textContent=data.met;
   // Distinguish a paused/stalled simulation from wall-clock time:
   // never invent seconds between authoritative server snapshots.
   status.textContent=lastTick===tick?'MET HOLD':'MET RUNNING';
   lastTick=tick;
  }catch(e){status.textContent=e instanceof Error?e.message:'MET REQUEST FAILED';}
  finally{busy=false;}
 }
 poll();
 const timer=setInterval(()=>{if(!document.hidden)poll();},1000);
 window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
})();
</script>
