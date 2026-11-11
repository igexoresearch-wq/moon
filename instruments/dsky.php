<?php
$dcCraft=in_array($console??'',['lmc','lmp'],true)?6:3;
$dcId='dsky-'.bin2hex(random_bytes(5));
?>
<div id="<?=htmlspecialchars($dcId,ENT_QUOTES)?>" class="moon-dsky" data-craft="<?=$dcCraft?>">
<style>
#<?=htmlspecialchars($dcId)?>{width:380px;background:#5b5d57;border:6px ridge #272a27;padding:12px;box-sizing:border-box;color:#e2ddd0;font:12px Arial,sans-serif;user-select:none}
#<?=htmlspecialchars($dcId)?> .dc-head{text-align:center;background:#303632;letter-spacing:2px;font:bold 14px Arial;padding:8px}
#<?=htmlspecialchars($dcId)?> .dc-panel{background:#131c17;border:5px inset #858c7c;padding:9px;margin:10px 0;color:#a5e0ad;font:18px monospace}
#<?=htmlspecialchars($dcId)?> .dc-prog{display:flex;justify-content:space-between;font-size:16px;margin-bottom:8px}
#<?=htmlspecialchars($dcId)?> .dc-field{display:grid;grid-template-columns:48px 1fr 92px 22px;align-items:center;gap:6px;padding:4px 0;font-size:14px}
#<?=htmlspecialchars($dcId)?> .dc-field button{padding:5px 0;font-size:10px}
#<?=htmlspecialchars($dcId)?> .dc-field output{border:1px solid #3b513c;background:#0e1710;color:#b1e8b7;letter-spacing:3px;font:18px monospace;padding:5px 6px;min-height:30px}
#<?=htmlspecialchars($dcId)?> .dc-field .dc-lamp{width:9px;height:9px;border-radius:50%;background:#303d2f;border:1px solid #55634f}
#<?=htmlspecialchars($dcId)?> .dc-field .dc-lamp.lit{background:#e2ba5c;box-shadow:0 0 7px #e2ba5c}
#<?=htmlspecialchars($dcId)?> .dc-field.selected output{outline:1px solid #e4c37c}
#<?=htmlspecialchars($dcId)?> .dc-regs{border:2px solid #343a35;background:#222a25;padding:7px}
#<?=htmlspecialchars($dcId)?> .dc-register{display:grid;grid-template-columns:28px 48px 1fr 46px;align-items:center;gap:5px;padding:4px;border-bottom:1px solid #3e4840;font:11px monospace}
#<?=htmlspecialchars($dcId)?> .dc-register .dc-status{color:#cfdabf}
#<?=htmlspecialchars($dcId)?> button{border:2px outset #c3c7ba;background:#a6aaa0;color:#10140e;font:bold 12px Arial;cursor:pointer;border-radius:2px}
#<?=htmlspecialchars($dcId)?> button:disabled{opacity:.35;cursor:default}
#<?=htmlspecialchars($dcId)?> .dc-keys{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-top:10px}
#<?=htmlspecialchars($dcId)?> .dc-keys button{min-height:40px;font:700 18px monospace}
#<?=htmlspecialchars($dcId)?> .dc-keys .dc-action{font:700 12px Arial;background:#c5bc9e}
#<?=htmlspecialchars($dcId)?> .dc-pending{display:block;text-align:center;height:24px;margin:7px 0 0;color:#ffe4ad;font:16px monospace;letter-spacing:2px}
#<?=htmlspecialchars($dcId)?> .dc-message{min-height:17px;text-align:center;font:11px monospace;color:#f2d192;margin:7px 0}
#<?=htmlspecialchars($dcId)?> .dc-foot{text-align:center;font:10px monospace;margin:8px 0 0}
</style>
<div class="dc-head"><?=$dcCraft===3?'CMC':'LGC'?> / PROGRAM CONTROL</div>
<div class="dc-panel">
<div class="dc-prog"><span>PROG <b data-program>----</b></span><span data-title>STANDBY</span></div>
<?php for($i=1;$i<=4;$i++):?>
<div class="dc-field" data-field="<?=$i?>">
 <button type="button" data-select="<?=$i?>">R<?=$i?></button>
 <output data-value="<?=$i?>">----</output>
 <span data-label="<?=$i?>">---</span>
 <span class="dc-lamp" data-required="<?=$i?>"></span>
</div>
<?php endfor;?>
<output class="dc-pending" data-pending>----</output>
</div>
<div class="dc-regs"><div style="letter-spacing:1px;margin-bottom:5px">EXECUTION REGISTERS</div>
<?php for($i=1;$i<=5;$i++):?>
<div class="dc-register"><b><?=$i?></b><span data-slot-program="<?=$i?>">----</span><span class="dc-status" data-slot-state="<?=$i?>">EMPTY</span><button type="button" data-clear="<?=$i?>">CLR</button></div>
<?php endfor;?>
</div>
<div class="dc-keys">
<button type="button" data-digit="1">1</button><button type="button" data-digit="2">2</button><button type="button" data-digit="3">3</button>
<button type="button" data-digit="4">4</button><button type="button" data-digit="5">5</button><button type="button" data-digit="6">6</button>
<button type="button" data-digit="7">7</button><button type="button" data-digit="8">8</button><button type="button" data-digit="9">9</button>
<button type="button" data-action="program" class="dc-action">PROG</button><button type="button" data-digit="0">0</button><button type="button" data-action="back" class="dc-action">DEL</button>
<button type="button" data-action="enter" class="dc-action">ENTER</button>
<button type="button" data-action="execute" class="dc-action">EXECUTE</button>
<button type="button" data-action="cancel" class="dc-action">CANCEL</button>
</div>
<div class="dc-message" data-message></div>
<div class="dc-foot">PROGRAM / ENTER / REGISTER / ENTER / EXECUTE</div>
</div>
<script>
(()=>{
 const el=document.getElementById(<?=json_encode($dcId)?>),craft=Number(el.dataset.craft);
 let data=null,mode='program',digits='',busy=false;
 const $=sel=>el.querySelector(sel);
 const pad=()=>digits.padStart(4,'0');
 const showInput=()=>{$('[data-pending]').textContent=digits?pad():'----';};
 const message=text=>{$('[data-message]').textContent=text;};
 async function request(action,value){
  if(busy)return false;busy=true;
  try{
   const response=await fetch('dsky_api.php?craft='+craft,{
    method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({csrf:data?.csrf,action,value})
   });
   const next=await response.json();
   if(!response.ok)throw Error(next.error||'COMMAND FAILED');
   data=next;render();message('');return true;
  }catch(e){message(e.message||'COMMAND ERROR');return false;}
  finally{busy=false;}
 }
 async function refresh(){
  if(busy)return;
  try{
   const response=await fetch('dsky_api.php?craft='+craft,{cache:'no-store'});
   if(!response.ok)throw Error('DSKY STATUS UNAVAILABLE');
   data=await response.json();render();
  }catch(e){message(e.message);}
 }
 function render(){
  const p=data?.program,d=data?.draft;
  $('[data-program]').textContent=p?String(p.program_no).padStart(2,'0'):'--';
  $('[data-title]').textContent=p?p.title:'NO PROGRAM';
  for(let i=1;i<=4;i++){
   const label=p?.['field'+i+'_label']||null;
   const value=d?.['field'+i];
   const row=el.querySelector('[data-field="'+i+'"]');
   row.querySelector('button').disabled=!label;
   const scale=Number(p?.config_json?JSON.parse(p.config_json).scale:1)||1;
   const raw=value===null||value===undefined?null:String(Math.round(Number(value))).padStart(4,'0');
   row.querySelector('[data-value]').textContent=raw===null?'----':scale===10?raw.slice(0,-1)+'.'+raw.slice(-1):raw;
   row.querySelector('[data-label]').textContent=label||'';
   row.querySelector('[data-required]').classList.toggle('lit',!!label&&value===null);
   row.classList.toggle('selected',mode==='field'+i);
  }
  for(const r of data?.registers||[]){
   const i=r.register_no;
   $('[data-slot-program="'+i+'"]').textContent=r.program_no===null?'----':String(r.program_no).padStart(2,'0');
   $('[data-slot-state="'+i+'"]').textContent=r.state.toUpperCase()+(r.message?' / '+r.message:'');
  }
  showInput();
 }
 el.querySelectorAll('[data-digit]').forEach(b=>b.addEventListener('click',()=>{
  if(digits.length>=4)return;digits+=b.dataset.digit;showInput();
 }));
 el.querySelectorAll('[data-select]').forEach(b=>b.addEventListener('click',async()=>{
  if(b.disabled||busy)return;
  const i=Number(b.dataset.select),toggleOff=mode==='field'+i;
  const ok=await request(toggleOff?'deselect_field':'select_field',toggleOff?null:i);
  if(ok){mode=toggleOff?'program':'field'+i;digits='';render();message('');}
 }));
 el.querySelectorAll('[data-clear]').forEach(b=>b.addEventListener('click',()=>{
  if(confirm('CLEAR EXECUTION REGISTER '+b.dataset.clear+'? Active commands will be aborted.'))request('clear',Number(b.dataset.clear));
 }));
 el.querySelector('[data-action="program"]').addEventListener('click',()=>{mode='program';digits='';render();message('PROGRAM NUMBER');});
 el.querySelector('[data-action="back"]').addEventListener('click',()=>{digits=digits.slice(0,-1);showInput();});
 el.querySelector('[data-action="cancel"]').addEventListener('click',()=>{digits='';showInput();message('');});
 el.querySelector('[data-action="enter"]').addEventListener('click',async()=>{
  if(!digits){message('ENTER A NUMBER');return;}
  const action=mode==='program'?'program':'enter';
  const ok=await request(action,Number(digits));
  if(ok){digits='';mode='program';render();}
 });
 el.querySelector('[data-action="execute"]').addEventListener('click',async()=>{const ok=await request('execute',null);if(ok){digits='';render();}});
 refresh();const timer=setInterval(()=>{if(!document.hidden)refresh();},2000);
 window.addEventListener('pagehide',()=>clearInterval(timer),{once:true});
})();
</script>
