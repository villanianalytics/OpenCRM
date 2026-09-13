<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';

$suffix=bin2hex(random_bytes(8));$dependency='test:cache:'.$suffix;$key='test-cache-'.$suffix;$loads=0;
$first=smart_cache_remember($key,[$dependency],function()use(&$loads){$loads++;return ['value'=>'first'];},60);
$second=smart_cache_remember($key,[$dependency],function()use(&$loads){$loads++;return ['value'=>'unexpected'];},60);
if($first!==$second||$loads!==1)throw new RuntimeException('Smart cache did not reuse a valid entry.');
smart_cache_bump([$dependency]);
$third=smart_cache_remember($key,[$dependency],function()use(&$loads){$loads++;return ['value'=>'rebuilt'];},60);
if(($third['value']??null)!=='rebuilt'||$loads!==2)throw new RuntimeException('Dependency revision did not rebuild the cached entry.');
db()->prepare('DELETE FROM cache_revisions WHERE dependency_key=?')->execute([$dependency]);
if(smart_cache_backend()==='Filesystem')@unlink(smart_cache_dir().'/'.substr(smart_cache_storage_key($key),8).'.cache');else if(function_exists('apcu_delete'))apcu_delete(smart_cache_storage_key($key));
echo "Smart cache smoke test passed.\n";
