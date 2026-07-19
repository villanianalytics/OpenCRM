<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
set_exception_handler(function(Throwable $e):never{operational_notify('backup_verification','error','Backup verification failed: '.$e->getMessage());fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);});

$dir=dirname(__DIR__).'/storage/backups';$manifestPath=$argv[1]??'';
if($manifestPath===''){$files=glob($dir.'/opencrm_*_manifest.json')?:[];usort($files,fn($a,$b)=>filemtime($b)<=>filemtime($a));$manifestPath=$files[0]??'';}
if($manifestPath===''||!is_file($manifestPath))throw new RuntimeException('No backup manifest found.');
$manifest=json_decode((string)file_get_contents($manifestPath),true,512,JSON_THROW_ON_ERROR);$base=dirname($manifestPath);$dbFile=$base.'/'.$manifest['database'];$uploadsFile=$base.'/'.$manifest['uploads'];
foreach([[$dbFile,$manifest['database_sha256']],[$uploadsFile,$manifest['uploads_sha256']]] as [$file,$hash])if(!is_file($file)||!hash_equals($hash,hash_file('sha256',$file)))throw new RuntimeException('Backup checksum failed for '.basename($file));
$gz=gzopen($dbFile,'rb');$sample='';while(!gzeof($gz)&&strlen($sample)<2_000_000)$sample.=gzread($gz,131072);gzclose($gz);
foreach(['CREATE TABLE `contacts`','CREATE TABLE `users`','CREATE TABLE `opportunities`'] as $marker)if(!str_contains($sample,$marker))throw new RuntimeException('Database dump is missing '.$marker);
exec('tar -tzf '.escapeshellarg($uploadsFile).' 2>&1',$listing,$code);if($code!==0||!in_array('uploads/',$listing,true))throw new RuntimeException('Upload archive verification failed.');
file_put_contents($base.'/last_verified.json',json_encode(['manifest'=>basename($manifestPath),'verified_at'=>(new DateTimeImmutable())->format(DateTimeInterface::ATOM)],JSON_PRETTY_PRINT));
app_log('info','Backup verification passed',['manifest'=>basename($manifestPath)]);operational_notify('backup_verification','healthy','Backup integrity verification passed.',['manifest'=>basename($manifestPath)]);echo 'Backup verified: '.basename($manifestPath).PHP_EOL;

