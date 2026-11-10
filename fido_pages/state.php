<?php ?>
<h2>002 / STATE VECTORS</h2>
<p class="fd-note">INERTIAL SIMULATION XYZ. POSITION KM / VELOCITY M/S. NO ESTIMATION FILTER.</p>
<table class="fd-table"><thead><tr><th>OBJECT</th><th>X</th><th>Y</th><th>Z</th><th>VX</th><th>VY</th><th>VZ</th></tr></thead><tbody>
<?php foreach([1,2,3,6] as $id): if(!isset($objects[$id]))continue;$o=$objects[$id]; ?>
<tr><td><?=htmlspecialchars($o['name'])?></td><?php foreach(['x','y','z','delta_x','delta_y','delta_z'] as $field): ?><td><?=fidoN((float)$o[$field]/(in_array($field,['x','y','z'],true)?1000:1),3)?></td><?php endforeach;?></tr>
<?php endforeach;?></tbody></table>
<p class="fd-note">ACTIVE STATE AND ATTACHMENTS ARE READ DIRECTLY FROM OBJECTS.</p>
