<?php require_once __DIR__.'/common.php'; ?>
<!doctype html><html><head><meta charset="utf-8"><title>Moon</title><style>
body{background:#777;color:#fff;font:18px Arial}main{max-width:440px;margin:8vh auto}
a{display:block;background:#555;color:white;padding:14px;margin:8px;text-decoration:none}
</style></head><body><main><h1>Moon</h1>
<?php foreach(STATIONS as $id=>$label):?><a href="console.php?console=<?=htmlspecialchars($id)?>"><?=htmlspecialchars($label)?></a><?php endforeach;?>
<a href="editor.php?console=cmc">Instrument layout editor</a></main></body></html>