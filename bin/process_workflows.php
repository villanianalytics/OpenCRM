<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';

function wf_match(array $workflow,array $event):bool{
    $cfg=json_decode((string)$workflow['trigger_config'],true)?:[];
    $payload=json_decode((string)$event['payload_json'],true)?:[];
    foreach(['tag_id','form_id','meeting_type_id','to_status'] as $key)if(array_key_exists($key,$cfg)&&(string)$cfg[$key]!=(string)($payload[$key]??''))return false;
    return true;
}
function wf_merge(string $text,array $c):string{return strtr($text,['{{first_name}}'=>$c['first_name']??'','{{last_name}}'=>$c['last_name']??'','{{full_name}}'=>trim(($c['first_name']??'').' '.($c['last_name']??'')),'{{company}}'=>$c['company']??'','{{email}}'=>$c['email']??'']);}
function wf_log(int $enrollment,?int $step,string $action,string $status,string $detail=''):void{db()->prepare('INSERT INTO workflow_logs(enrollment_id,step_index,action_type,status,detail) VALUES(?,?,?,?,?)')->execute([$enrollment,$step,$action,$status,$detail]);}

$emit=db()->prepare('INSERT IGNORE INTO workflow_events(event_key,event_type,contact_id,entity_type,entity_id,payload_json) VALUES(?,?,?,?,?,?)');
foreach(db()->query('SELECT id FROM contacts ORDER BY id DESC LIMIT 10000')->fetchAll() as $r)$emit->execute(['contact:'.$r['id'],'contact_created',$r['id'],'contact',$r['id'],null]);
foreach(db()->query('SELECT contact_id,tag_id FROM contact_tags LIMIT 50000')->fetchAll() as $r)$emit->execute(['tag:'.$r['contact_id'].':'.$r['tag_id'],'tag_added',$r['contact_id'],'tag',$r['tag_id'],json_encode(['tag_id'=>(int)$r['tag_id']])]);
foreach(db()->query('SELECT id,form_id,contact_id FROM crm_form_submissions ORDER BY id DESC LIMIT 10000')->fetchAll() as $r)$emit->execute(['form_submission:'.$r['id'],'form_submitted',$r['contact_id'],'crm_form',$r['form_id'],json_encode(['form_id'=>(int)$r['form_id'],'submission_id'=>(int)$r['id']])]);
foreach(db()->query('SELECT id,meeting_type_id,contact_id FROM bookings ORDER BY id DESC LIMIT 10000')->fetchAll() as $r)if($r['contact_id'])$emit->execute(['booking:'.$r['id'],'booking_created',$r['contact_id'],'booking',$r['id'],json_encode(['meeting_type_id'=>(int)$r['meeting_type_id']])]);
foreach(db()->query("SELECT a.id,a.entity_id,a.details_json,o.contact_id FROM audit_logs a JOIN opportunities o ON o.id=a.entity_id WHERE a.entity_type='opportunity' AND a.action='pipeline_move' ORDER BY a.id DESC LIMIT 10000")->fetchAll() as $r){$details=json_decode((string)$r['details_json'],true)?:[];$emit->execute(['pipeline_audit:'.$r['id'],'opportunity_stage_changed',$r['contact_id'],'opportunity',$r['entity_id'],json_encode(['to_status'=>$details['status']??''])]);}

$events=db()->query('SELECT * FROM workflow_events WHERE processed_at IS NULL ORDER BY id LIMIT 500')->fetchAll();
$workflows=db()->query('SELECT * FROM workflows WHERE active=1')->fetchAll();
foreach($events as $event){
    if($event['contact_id'])foreach($workflows as $workflow)if($workflow['trigger_type']===$event['event_type']&&wf_match($workflow,$event))db()->prepare("INSERT INTO workflow_enrollments(workflow_id,contact_id,source_event_id,status,next_run_at) VALUES(?,?,?,'active',NOW())")->execute([$workflow['id'],$event['contact_id'],$event['id']]);
    db()->prepare('UPDATE workflow_events SET processed_at=NOW() WHERE id=?')->execute([$event['id']]);
}

$due=db()->query("SELECT e.id enrollment_id,e.workflow_id,e.contact_id,e.current_step,w.steps_json,w.active workflow_active,c.first_name,c.last_name,c.company,c.email,c.owner_id,c.created_by FROM workflow_enrollments e JOIN workflows w ON w.id=e.workflow_id JOIN contacts c ON c.id=e.contact_id WHERE e.status='active' AND (e.next_run_at IS NULL OR e.next_run_at<=NOW()) ORDER BY e.id LIMIT 200")->fetchAll();
foreach($due as $e){
    $enrollmentId=(int)$e['enrollment_id'];
    try{
        if(!$e['workflow_active']){db()->prepare("UPDATE workflow_enrollments SET status='stopped',completed_at=NOW() WHERE id=?")->execute([$enrollmentId]);continue;}
        $steps=json_decode((string)$e['steps_json'],true)?:[];$index=(int)$e['current_step'];
        while(isset($steps[$index])){
            $step=$steps[$index];$type=(string)($step['type']??'');
            if($type==='wait'){$minutes=max(1,min(525600,(int)($step['minutes']??60)));db()->prepare('UPDATE workflow_enrollments SET current_step=?,next_run_at=DATE_ADD(NOW(),INTERVAL ? MINUTE) WHERE id=?')->execute([$index+1,$minutes,$enrollmentId]);wf_log($enrollmentId,$index,$type,'success','Waiting '.$minutes.' minutes');continue 2;}
            if($type==='add_tag')db()->prepare('INSERT IGNORE INTO contact_tags(contact_id,tag_id) VALUES(?,?)')->execute([$e['contact_id'],(int)$step['tag_id']]);
            elseif($type==='remove_tag')db()->prepare('DELETE FROM contact_tags WHERE contact_id=? AND tag_id=?')->execute([$e['contact_id'],(int)$step['tag_id']]);
            elseif($type==='assign_owner')db()->prepare('UPDATE contacts SET owner_id=? WHERE id=?')->execute([(int)$step['user_id'],$e['contact_id']]);
            elseif($type==='create_alert'){$uid=(int)($step['user_id']?:$e['owner_id']?:$e['created_by']);db()->prepare('INSERT INTO reminders(contact_id,user_id,message,due_at) VALUES(?,?,?,DATE_ADD(NOW(),INTERVAL ? MINUTE))')->execute([$e['contact_id'],$uid,wf_merge((string)($step['message']??'Workflow follow-up'),$e),max(0,(int)($step['delay_minutes']??0))]);}
            elseif($type==='send_email'){
                if(!filter_var($e['email'],FILTER_VALIDATE_EMAIL))throw new RuntimeException('Contact email is invalid.');
                $subject=wf_merge((string)($step['subject']??''),$e);$body=wf_merge((string)($step['body']??''),$e);send_mail($e['email'],$subject,$body);
                db()->prepare('INSERT INTO email_threads(contact_id,subject,last_message_at,created_by) VALUES(?,?,NOW(),?)')->execute([$e['contact_id'],$subject,$e['created_by']?:null]);$threadId=(int)db()->lastInsertId();
                db()->prepare("INSERT INTO email_messages(thread_id,direction,from_address,to_address,subject,body_text,delivery_status,sent_at) VALUES(?,'outbound',?,?,?,?, 'sent',NOW())")->execute([$threadId,app_setting('mail_from_address'),$e['email'],$subject,$body]);
            }else throw new RuntimeException('Unknown workflow action: '.$type);
            wf_log($enrollmentId,$index,$type,'success');$index++;db()->prepare('UPDATE workflow_enrollments SET current_step=?,next_run_at=NOW() WHERE id=?')->execute([$index,$enrollmentId]);
        }
        db()->prepare("UPDATE workflow_enrollments SET status='completed',completed_at=NOW(),next_run_at=NULL WHERE id=?")->execute([$enrollmentId]);wf_log($enrollmentId,null,'complete','info','Workflow completed');
    }catch(Throwable $error){db()->prepare("UPDATE workflow_enrollments SET status='failed',error_message=?,completed_at=NOW() WHERE id=?")->execute([mb_strimwidth($error->getMessage(),0,1000),$enrollmentId]);wf_log($enrollmentId,(int)$e['current_step'],'error','failed',$error->getMessage());app_log('error','Workflow enrollment failed',['enrollment_id'=>$enrollmentId,'error'=>$error->getMessage()]);}
}
echo 'Events '.count($events).'; enrollments '.count($due)." processed.\n";
