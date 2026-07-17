<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$path=''; $method='CLI';
require dirname(__DIR__) . '/src/routes_event_import.php';
require dirname(__DIR__) . '/src/routes_reports.php';

$pdo=db(); $pdo->beginTransaction();
try {
    $admin=$pdo->query('SELECT id,role_id,username FROM users WHERE is_admin=1 LIMIT 1')->fetch();
    if(!$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='display_name'")->fetchColumn())throw new RuntimeException('User display names migration failed.');
    if(!in_array('high',option_codes('opportunity_score'),true)||!in_array('conference',option_codes('event_type'),true))throw new RuntimeException('Configurable dropdown seed failed.');
    $_SESSION['user']=['id'=>(int)$admin['id'],'role_id'=>(int)$admin['role_id'],'username'=>$admin['username'],'is_admin'=>true,'permissions'=>[]];
    $_SESSION['user']['is_admin']=false;$_SESSION['user']['permissions']=['contacts.edit','events.edit','reports.edit'];if(!can('contacts.view')||!can('events.view')||!can('reports.view'))throw new RuntimeException('Edit permission inheritance failed.');$_SESSION['user']['is_admin']=true;$_SESSION['user']['permissions']=[];
    app_log('error','Smoke application log',['test'=>true]);$logId=(int)$pdo->lastInsertId();if(!$logId)throw new RuntimeException('Application logging failed.');$pdo->prepare('UPDATE application_logs SET created_at=DATE_SUB(NOW(),INTERVAL 100 DAY) WHERE id=?')->execute([$logId]);$cleanup=$pdo->prepare('DELETE FROM application_logs WHERE id=? AND created_at<DATE_SUB(NOW(),INTERVAL ? DAY)');$cleanup->execute([$logId,90]);if($cleanup->rowCount()!==1)throw new RuntimeException('Application log retention failed.');
    $pdo->exec("INSERT INTO tags(name,color) VALUES('Smoke_Default_Tag','#546e7a')");$defaultTagId=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO user_default_tags(user_id,tag_id) VALUES(?,?)')->execute([$admin['id'],$defaultTagId]);
    $pdo->prepare("INSERT INTO events(title,event_type,starts_at,booking_url) VALUES('Smoke Test','webinar',NOW(),'https://example.com/book')")->execute();$eventId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO contacts(first_name,last_name,email,created_by) VALUES('Smoke','Contact','smoke@example.invalid',?)")->execute([$admin['id']]);$contactId=(int)$pdo->lastInsertId();
    $pdo->prepare("UPDATE events SET presenter_contact_id=?,presenter_name='Smoke Presenter' WHERE id=?")->execute([$contactId,$eventId]);$stmt=$pdo->prepare('SELECT presenter_contact_id FROM events WHERE id=?');$stmt->execute([$eventId]);if((int)$stmt->fetchColumn()!==$contactId)throw new RuntimeException('Event presenter tracking failed.');
    record_contact_changes($contactId,['first_name'=>'Smoke','active'=>1],['first_name'=>'Smokey','active'=>0]);$stmt=$pdo->prepare('SELECT COUNT(*) FROM contact_field_changes WHERE contact_id=?');$stmt->execute([$contactId]);if((int)$stmt->fetchColumn()!==2)throw new RuntimeException('Contact field history failed.');
    $pdo->prepare("INSERT INTO contact_saved_views(user_id,name,filters_json,is_default) VALUES(?,'Smoke view','{\"contact_status\":\"active\"}',1)")->execute([$admin['id']]);if(!$pdo->lastInsertId())throw new RuntimeException('Saved contact views failed.');
    $pdo->prepare("INSERT INTO contacts(first_name,last_name,email,created_by) VALUES('Related','Contact','related@example.invalid',?)")->execute([$admin['id']]);$relatedId=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO contact_relationships(contact_id,related_contact_id,relationship_type,created_by) VALUES(?,?,'Colleague',?)")->execute([$contactId,$relatedId,$admin['id']]);if(!$pdo->lastInsertId())throw new RuntimeException('Contact relationships failed.');
    apply_user_default_tags($contactId,(int)$admin['id']);$stmt=$pdo->prepare('SELECT COUNT(*) FROM contact_tags WHERE contact_id=? AND tag_id=?');$stmt->execute([$contactId,$defaultTagId]);if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('User automatic tags failed.');
    $pdo->exec("INSERT INTO custom_field_groups(name,position) VALUES('Smoke details',10)");$customGroupId=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO custom_fields(custom_field_group_id,name,field_type) VALUES(?,'Smoke controller','text')")->execute([$customGroupId]);$controllerFieldId=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO custom_fields(custom_field_group_id,name,field_type) VALUES(?,'Smoke custom field','list')")->execute([$customGroupId]);$customFieldId=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO custom_field_options(custom_field_id,option_value) VALUES(?,'Choice A')")->execute([$customFieldId]);$pdo->prepare('INSERT INTO custom_field_tags(custom_field_id,tag_id) VALUES(?,?)')->execute([$customFieldId,$defaultTagId]);$pdo->prepare("INSERT INTO custom_field_conditions(custom_field_id,depends_on_field_id,operator,expected_value) VALUES(?,?,'equals','Yes')")->execute([$customFieldId,$controllerFieldId]);save_contact_custom_values($contactId,[$controllerFieldId=>'Yes',$customFieldId=>'Choice A']);$stmt=$pdo->prepare('SELECT field_value FROM contact_custom_values WHERE contact_id=? AND custom_field_id=?');$stmt->execute([$contactId,$customFieldId]);if($stmt->fetchColumn()!=='Choice A')throw new RuntimeException('Conditional custom field failed.');$definitions=custom_field_definitions();$definition=array_values(array_filter($definitions,fn($item)=>(int)$item['id']===$customFieldId))[0]??null;if(($definition['group_name']??'')!=='Smoke details'||count($definition['conditions']??[])!==1)throw new RuntimeException('Custom field grouping or field-value condition failed.');
    $_GET=['cf_'.$customFieldId=>'Choice A'];$reportParams=[];$reportWhere=report_contact_where($reportParams);$stmt=$pdo->prepare('SELECT COUNT(DISTINCT c.id) FROM contacts c LEFT JOIN contact_tags ct ON ct.contact_id=c.id WHERE '.implode(' AND ',$reportWhere));$stmt->execute($reportParams);if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Custom field report filter failed.');$_GET=[];
    add_event_attendee($eventId,$contactId);
    $stmt=$pdo->prepare("SELECT COUNT(*) FROM tags t JOIN contact_tags ct ON ct.tag_id=t.id WHERE ct.contact_id=? AND t.name='Smoke_Test_webinar'");$stmt->execute([$contactId]);
    if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Automatic event tag failed.');
    $pdo->prepare("INSERT INTO opportunities(contact_id,title,score,source_type,source_event_id) VALUES(?,'Smoke opportunity','high','event',?)")->execute([$contactId,$eventId]);$opportunityId=(int)$pdo->lastInsertId();
    $stmt=$pdo->prepare('SELECT active FROM opportunities WHERE id=?');$stmt->execute([$opportunityId]);if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Opportunity archive migration failed.');
    $pdo->prepare("UPDATE opportunities SET status='won',closed_at=NOW() WHERE id=?")->execute([$opportunityId]);$stmt=$pdo->prepare('SELECT closed_at FROM opportunities WHERE id=?');$stmt->execute([$opportunityId]);if(!$stmt->fetchColumn())throw new RuntimeException('Opportunity close tracking failed.');
    $pdo->prepare("INSERT INTO partner_performance_reviews(partner_user_id,reviewer_user_id,decision,rating,review_date) VALUES(?,?,'continue',5,CURDATE())")->execute([$admin['id'],$admin['id']]);if(!$pdo->lastInsertId())throw new RuntimeException('Partner performance history failed.');
    $pdo->prepare('INSERT INTO opportunity_contacts(opportunity_id,contact_id) VALUES(?,?)')->execute([$opportunityId,$contactId]);
    $stmt=$pdo->prepare('SELECT COUNT(*) FROM opportunity_contacts WHERE opportunity_id=? AND contact_id=?');$stmt->execute([$opportunityId,$contactId]);if((int)$stmt->fetchColumn()!==1)throw new RuntimeException('Opportunity contact link failed.');
    $pdo->prepare("INSERT INTO reminders(contact_id,user_id,message,due_at) VALUES(?,?, 'Follow up', NOW())")->execute([$contactId,$admin['id']]);
    if(!$pdo->lastInsertId())throw new RuntimeException('Reminder creation failed.');
    $pdo->prepare("INSERT INTO event_reminders(event_id,user_id,message,due_at) VALUES(?,?, 'Call for presentations', NOW())")->execute([$eventId,$admin['id']]);
    if(!$pdo->lastInsertId())throw new RuntimeException('Event alert creation failed.');
    $pdo->prepare("INSERT INTO system_alerts(created_by,message,due_at) VALUES(?, 'System smoke alert', NOW())")->execute([$admin['id']]);$systemAlertId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO system_alert_states(system_alert_id,user_id,snoozed_until) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 DAY))')->execute([$systemAlertId,$admin['id']]);
    if(!$pdo->lastInsertId() && !$systemAlertId)throw new RuntimeException('System alert state failed.');
    $tmp=tempnam(sys_get_temp_dir(),'crm');file_put_contents($tmp,"First Name,Last Name,Email\nAda,Lovelace,ada@example.test\n");
    $rows=attendee_rows($tmp,'csv');unlink($tmp);if(count($rows)!==2||normalize_attendee_headers($rows[0])['email']!==2)throw new RuntimeException('CSV parsing failed.');
    $pdo->rollBack(); echo "Smoke tests passed.\n";
} catch(Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);
}
