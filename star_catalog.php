<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';
require_once __DIR__.'/star_alignment.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode(['stars'=>moonStarCatalog(),'sextant'=>[
 'reference_stars'=>['A','B','C'],'packed_format'=>'XXYY',
 'angle_per_count_deg'=>0.5,'zero_count'=>50,
 'field_half_angle_deg'=>24.5
]],JSON_THROW_ON_ERROR);
