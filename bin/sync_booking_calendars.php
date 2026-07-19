<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$path='';$method='CLI';
require dirname(__DIR__).'/src/calendar_integrations.php';
require dirname(__DIR__).'/src/booking_calendar_sync.php';
$rows=db()->query("SELECT id,status FROM bookings WHERE calendar_sync_status IN ('pending','partial','failed') AND created_at>=DATE_SUB(NOW(),INTERVAL 90 DAY) ORDER BY id LIMIT 200")->fetchAll();
foreach($rows as $row)try{booking_sync_calendars((int)$row['id'],$row['status']==='cancelled'?'delete':'upsert');}catch(Throwable $e){app_log('error','Scheduled booking sync retry failed',['booking_id'=>$row['id'],'error'=>$e->getMessage()]);}
echo 'Processed '.count($rows)." booking calendar sync item(s).\n";
