<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$path='';
$method='CLI';
require dirname(__DIR__).'/src/calendar_integrations.php';

$userIds=db()->query('SELECT DISTINCT user_id FROM calendar_connections WHERE active=1')->fetchAll(PDO::FETCH_COLUMN);
$from=new DateTimeImmutable('now',new DateTimeZone('UTC'));
$to=$from->modify('+1 day');
foreach($userIds as $userId)calendar_busy_ranges((int)$userId,$from,$to);
echo 'Checked '.count($userIds)." calendar account owner(s).\n";
