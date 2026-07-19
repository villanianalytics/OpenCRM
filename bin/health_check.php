<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';

$failures=[];$warnings=[];
try{db()->query('SELECT 1')->fetchColumn();}catch(Throwable $e){$failures[]='Database unavailable: '.$e->getMessage();}
$free=(int)disk_free_space(dirname(__DIR__));if($free<1024*1024*1024)$failures[]='Less than 1 GB disk space remains';elseif($free<5*1024*1024*1024)$warnings[]='Less than 5 GB disk space remains';
$backup=glob(dirname(__DIR__).'/storage/backups/opencrm_*_manifest.json')?:[];if(!$backup)$warnings[]='No application backup found';else{$latest=max(array_map('filemtime',$backup));if($latest<time()-36*3600)$failures[]='Latest application backup is older than 36 hours';}
$stale=(int)db()->query("SELECT COUNT(*) FROM calendar_connections WHERE active=1 AND (last_sync_at IS NULL OR last_sync_at<DATE_SUB(NOW(),INTERVAL 2 HOUR))")->fetchColumn();if($stale)$warnings[]="$stale calendar connection(s) have not synced recently";
$workflowFailures=(int)db()->query("SELECT COUNT(*) FROM workflow_enrollments WHERE status='failed' AND started_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();if($workflowFailures)$warnings[]="$workflowFailures workflow enrollment(s) failed in 24 hours";
$context=['failures'=>$failures,'warnings'=>$warnings,'disk_free_bytes'=>$free];app_log($failures?'error':($warnings?'warning':'info'),'Scheduled system health check',$context);
echo json_encode(['healthy'=>!$failures]+$context,JSON_PRETTY_PRINT).PHP_EOL;exit($failures?1:0);

