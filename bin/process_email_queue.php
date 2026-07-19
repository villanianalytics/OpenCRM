<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$limit=max(1,min(500,(int)($argv[1]??100)));$q=db()->prepare("SELECT * FROM email_queue WHERE status='queued' AND available_at<=NOW() ORDER BY created_at LIMIT $limit FOR UPDATE SKIP LOCKED");
$q->execute();$rows=$q->fetchAll();foreach($rows as $row){db()->prepare("UPDATE email_queue SET status='processing',attempts=attempts+1 WHERE id=? AND status='queued'")->execute([$row['id']]);try{crm_send_email($row['recipient'],$row['subject'],$row['body_text'],(bool)$row['transactional'],$row['mailbox_id']?(int)$row['mailbox_id']:null);db()->prepare("UPDATE email_queue SET status='sent',sent_at=NOW(),last_error=NULL WHERE id=?")->execute([$row['id']]);}catch(Throwable $e){$failed=(int)$row['attempts']+1>=5;db()->prepare("UPDATE email_queue SET status=?,available_at=DATE_ADD(NOW(),INTERVAL ? MINUTE),last_error=? WHERE id=?")->execute([$failed?'failed':'queued',min(1440,5*(2**(int)$row['attempts'])),mb_strimwidth($e->getMessage(),0,1000),$row['id']]);}}
echo count($rows)." queued email(s) processed.\n";

