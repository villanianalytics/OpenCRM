<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
set_exception_handler(function(Throwable $e):never{operational_notify('backup','error','Automated backup failed: '.$e->getMessage());fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);});

$root=dirname(__DIR__);$dir=$root.'/storage/backups';if(!is_dir($dir)&&!mkdir($dir,0770,true))throw new RuntimeException('Could not create backup directory.');
$stamp=(new DateTimeImmutable())->format('Ymd_His');$prefix=$dir.'/opencrm_'.$stamp;
$dbConfig=config('db');$defaults=tempnam(sys_get_temp_dir(),'opencrm-mysql-');
file_put_contents($defaults,"[client]\nhost={$dbConfig['host']}\nport={$dbConfig['port']}\nuser={$dbConfig['user']}\npassword={$dbConfig['pass']}\n");chmod($defaults,0600);
try{
    $sql=$prefix.'.sql';$command='mysqldump --defaults-extra-file='.escapeshellarg($defaults).' --single-transaction --routines --triggers --hex-blob '.escapeshellarg((string)$dbConfig['name']).' > '.escapeshellarg($sql).' 2>&1';
    exec($command,$output,$code);if($code!==0||!is_file($sql)||filesize($sql)<100)throw new RuntimeException('Database backup failed: '.implode("\n",$output));
    $in=fopen($sql,'rb');$gz=gzopen($sql.'.gz','wb9');while(!feof($in))gzwrite($gz,(string)fread($in,1024*1024));fclose($in);gzclose($gz);unlink($sql);
    $uploads=$root.'/storage/uploads';$archive=$prefix.'_uploads.tar.gz';$tarCommand='tar -czf '.escapeshellarg($archive).' -C '.escapeshellarg($root.'/storage').' uploads 2>&1';exec($tarCommand,$tarOut,$tarCode);if($tarCode!==0)throw new RuntimeException('Upload backup failed: '.implode("\n",$tarOut));
    $manifest=['created_at'=>(new DateTimeImmutable())->format(DateTimeInterface::ATOM),'database'=>basename($sql.'.gz'),'database_sha256'=>hash_file('sha256',$sql.'.gz'),'uploads'=>basename($archive),'uploads_sha256'=>hash_file('sha256',$archive)];
    file_put_contents($prefix.'_manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    foreach(glob($dir.'/opencrm_*')?:[] as $file)if(filemtime($file)<time()-30*86400)unlink($file);
    app_log('info','Automated backup completed',['manifest'=>basename($prefix.'_manifest.json')]);operational_notify('backup','healthy','Automated backup completed successfully.',['manifest'=>basename($prefix.'_manifest.json')]);echo $prefix.'_manifest.json'.PHP_EOL;
}finally{@unlink($defaults);}

