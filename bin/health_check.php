<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';

$failures=[];$warnings=[];
try{db()->query('SELECT 1')->fetchColumn();}catch(Throwable $e){$failures[]='Database unavailable: '.$e->getMessage();}
$free=(int)disk_free_space(dirname(__DIR__));if($free<1024*1024*1024)$failures[]='Less than 1 GB disk space remains';elseif($free<5*1024*1024*1024)$warnings[]='Less than 5 GB disk space remains';
$backup=glob(dirname(__DIR__).'/storage/backups/opencrm_*_manifest.json')?:[];if(!$backup)$warnings[]='No application backup found';else{$latest=max(array_map('filemtime',$backup));if($latest<time()-36*3600)$failures[]='Latest application backup is older than 36 hours';}
$stale=(int)db()->query("SELECT COUNT(*) FROM calendar_connections WHERE active=1 AND (last_sync_at IS NULL OR last_sync_at<DATE_SUB(NOW(),INTERVAL 2 HOUR))")->fetchColumn();if($stale)$warnings[]="$stale calendar connection(s) have not synced recently";
$calendarErrors=(int)db()->query("SELECT COUNT(*) FROM calendar_connections WHERE active=1 AND sync_status='error'")->fetchColumn();if($calendarErrors)$warnings[]="$calendarErrors calendar connection(s) report errors";
$bookingSyncErrors=(int)db()->query("SELECT COUNT(*) FROM bookings WHERE calendar_sync_status='failed' AND created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();if($bookingSyncErrors)$warnings[]="$bookingSyncErrors booking calendar write-back(s) failed in 24 hours";
$workflowFailures=(int)db()->query("SELECT COUNT(*) FROM workflow_enrollments WHERE status='failed' AND started_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)")->fetchColumn();if($workflowFailures)$warnings[]="$workflowFailures workflow enrollment(s) failed in 24 hours";
$threshold=max(1,(int)app_setting('operational_webhook_failure_threshold','5'));$webhookFailures=(int)db()->query("SELECT COUNT(*) FROM application_logs WHERE created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR) AND level IN ('warning','error') AND (message LIKE 'HTTP POST /webhooks/stripe -> 4%' OR message LIKE 'HTTP POST /webhooks/ses -> 4%')")->fetchColumn();if($webhookFailures>=$threshold)$warnings[]="$webhookFailures rejected SES/Stripe webhook requests in one hour";
$context=['failures'=>$failures,'warnings'=>$warnings,'disk_free_bytes'=>$free,'calendar_errors'=>$calendarErrors,'booking_sync_errors'=>$bookingSyncErrors,'workflow_failures'=>$workflowFailures,'webhook_failures'=>$webhookFailures];$status=$failures?'error':($warnings?'warning':'healthy');app_log($failures?'error':($warnings?'warning':'info'),'Scheduled system health check',$context);operational_notify('system_health',$status,$status==='healthy'?'All scheduled operational checks are healthy.':implode("\n",array_merge($failures,$warnings)),$context);
echo json_encode(['healthy'=>!$failures]+$context,JSON_PRETTY_PRINT).PHP_EOL;exit($failures?1:0);

