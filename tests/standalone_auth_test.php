<?php
declare(strict_types=1);
// Source-level regression to guard Moon's standalone operation.
$root=dirname(__DIR__);
$common=file_get_contents($root.'/common.php');
$security=file_get_contents($root.'/shared/operator_security.php');
if($common===false||$security===false)throw new RuntimeException('Missing source files');
function expectStandalone(bool $condition,string $reason):void {
 if(!$condition)throw new RuntimeException($reason);
}
expectStandalone(!str_contains($common,'moonSimRequireOperator('),
 'Common bootstrap must not invoke Back Office 5 login');
expectStandalone(!str_contains($common,'moonSimLoadSwcAuth('),
 'Common bootstrap must not load Back Office 5 auth DB');
expectStandalone(str_contains($common,'moonSimRequireHttps('),
 'HTTPS guard stays enabled');
expectStandalone(str_contains($common,'moonSimRequireSameOriginMutation('),
 'Mutation origin guard stays enabled');
expectStandalone(str_contains($common,"new mysqli("),
 'Independent Moon database connection preserved');
expectStandalone(str_contains($security,'function moonSimRequireWorker('),
 'CLI and token-gated worker support preserved');
echo "Moon standalone auth regression passed\n";
