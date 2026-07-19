<?php
declare(strict_types=1);

if($path==='/alerts'){
    require_login();$upcoming=($_GET['view']??'due')==='upcoming';
    $window=$upcoming?'r.due_at>NOW() AND r.due_at<=DATE_ADD(NOW(),INTERVAL 30 DAY)':'r.due_at<=NOW()';
    $s=db()->prepare("SELECT r.id,r.contact_id target_id,r.message,r.due_at,'contact' alert_type,CONCAT(c.first_name,' ',c.last_name) target_name,c.company detail FROM reminders r JOIN contacts c ON c.id=r.contact_id WHERE r.user_id=? AND r.completed_at IS NULL AND $window");$s->execute([user()['id']]);
    $alerts=array_values(array_filter($s->fetchAll(),fn($r)=>contact_access((int)$r['target_id'])!=='hidden'));
    if(can('events.view')){$s=db()->prepare("SELECT r.id,r.event_id target_id,r.message,r.due_at,'event' alert_type,e.title target_name,CONCAT(e.event_type,IF(e.presentation_title IS NULL OR e.presentation_title='','',CONCAT(' - ',e.presentation_title))) detail FROM event_reminders r JOIN events e ON e.id=r.event_id WHERE r.user_id=? AND r.completed_at IS NULL AND $window");$s->execute([user()['id']]);$alerts=array_merge($alerts,$s->fetchAll());}
    $systemWindow=$upcoming?'COALESCE(st.snoozed_until,a.due_at)>NOW() AND COALESCE(st.snoozed_until,a.due_at)<=DATE_ADD(NOW(),INTERVAL 30 DAY)':'COALESCE(st.snoozed_until,a.due_at)<=NOW()';
    $s=db()->prepare("SELECT a.id,0 target_id,a.message,COALESCE(st.snoozed_until,a.due_at) due_at,'system' alert_type,'System alert' target_name,'For all users' detail FROM system_alerts a LEFT JOIN system_alert_states st ON st.system_alert_id=a.id AND st.user_id=? WHERE a.active=1 AND $systemWindow AND st.completed_at IS NULL");$s->execute([user()['id']]);$alerts=array_merge($alerts,$s->fetchAll());
    usort($alerts,fn($a,$b)=>strcmp($a['due_at'],$b['due_at']));
    layout('Alerts',function()use($alerts,$upcoming){?><div class="actions"><h1 style="margin:0">Alerts</h1><span class="spacer"></span><a class="btn <?=$upcoming?'secondary':''?>" href="/alerts">Due alerts</a><a class="btn <?=$upcoming?'':'secondary'?>" href="/alerts?view=upcoming">Next 30 days</a></div><div class="card"><?php if(!$alerts):?><p class="muted"><?=$upcoming?'No alerts are scheduled in the next 30 days.':'No alerts are due.'?></p><?php endif?><?php foreach($alerts as $a):?><div class="alert-item"><div><strong><?=e($a['message'])?></strong><br><?php if($a['alert_type']==='system'):?><span><?=e($a['target_name'])?></span><?php else:?><a href="/<?=$a['alert_type']==='event'?'events':'contacts'?>/<?=$a['target_id']?>"><?=e($a['target_name'])?></a><?php endif?> Â· <?=e($a['detail'])?><br><span class="muted">Due <?=e($a['due_at'])?></span></div><form method="post" action="/alerts/<?=$a['alert_type']?>/<?=$a['id']?>"><input type="hidden" name="_csrf" value="<?=csrf()?>"><button name="action" value="snooze" class="secondary">Snooze 1 day</button><button name="action" value="complete">Complete</button></form></div><?php endforeach?></div><?php });exit;
}
if(preg_match('#^/alerts/(contact|event)/(\d+)$#',$path,$m)&&$method==='POST'){
    require_login();verify_csrf();$table=$m[1]==='event'?'event_reminders':'reminders';$id=(int)$m[2];
    if(post('action')==='complete')db()->prepare("UPDATE $table SET completed_at=NOW() WHERE id=? AND user_id=?")->execute([$id,user()['id']]);
    elseif(post('action')==='snooze')db()->prepare("UPDATE $table SET due_at=DATE_ADD(NOW(),INTERVAL 1 DAY) WHERE id=? AND user_id=?")->execute([$id,user()['id']]);
    audit(post('action')==='complete'?'complete':'snooze',$m[1].'_reminder',$id);redirect('/alerts');
}
if(preg_match('#^/alerts/system/(\d+)$#',$path,$m)&&$method==='POST'){
    require_login();verify_csrf();$id=(int)$m[1];
    if(post('action')==='complete')db()->prepare('INSERT INTO system_alert_states(system_alert_id,user_id,completed_at) VALUES(?,?,NOW()) ON DUPLICATE KEY UPDATE completed_at=NOW()')->execute([$id,user()['id']]);
    elseif(post('action')==='snooze')db()->prepare('INSERT INTO system_alert_states(system_alert_id,user_id,snoozed_until) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 1 DAY)) ON DUPLICATE KEY UPDATE snoozed_until=DATE_ADD(NOW(),INTERVAL 1 DAY)')->execute([$id,user()['id']]);
    audit(post('action')==='complete'?'complete':'snooze','system_alert',$id);redirect('/alerts');
}
if(preg_match('#^/events/(\d+)/reminders$#',$path,$m)&&$method==='POST'){
    require_permission('events.view');verify_csrf();$eventId=(int)$m[1];$message=trim((string)post('message'));$due=str_replace('T',' ',(string)post('due_at'));
    require_permission('alerts.edit');
    if($message===''||!strtotime($due)){flash('error','Alert message and date are required.');redirect('/events/'.$eventId);}
    db()->prepare('INSERT INTO event_reminders(event_id,user_id,message,due_at) VALUES(?,?,?,?)')->execute([$eventId,user()['id'],$message,$due]);
    audit('create','event_reminder',(int)db()->lastInsertId(),['event_id'=>$eventId,'message'=>$message,'due_at'=>$due]);flash('success','Event alert created.');redirect('/events/'.$eventId);
}

