<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/booking_helpers.php';
$rows=db()->query("SELECT n.id,n.booking_id,n.notification_type FROM booking_notification_log n JOIN bookings b ON b.id=n.booking_id WHERE n.status='pending' AND n.scheduled_for<=NOW() AND b.status='confirmed' ORDER BY n.scheduled_for LIMIT 200")->fetchAll();foreach($rows as $row)booking_send_confirmation((int)$row['booking_id'],(string)$row['notification_type']);echo count($rows)." reminder(s) processed.\n";
