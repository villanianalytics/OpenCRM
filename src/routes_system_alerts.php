<?php
declare(strict_types=1);

if($path!=='/admin/system-alerts')return;
if(!(user()['is_admin']??false)){http_response_code(403);exit('Forbidden');}
if($method==='POST'){
    verify_csrf();$action=(string)post('action');
    if($action==='create'){
        $message=trim((string)post('message'));$due=str_replace('T',' ',(string)post('due_at'));
        if($message===''||!strtotime($due)){flash('error','Message and target date are required.');redirect('/admin/system-alerts');}
        db()->prepare('INSERT INTO system_alerts(created_by,message,due_at) VALUES(?,?,?)')->execute([user()['id'],$message,$due]);
        audit('create','system_alert',(int)db()->lastInsertId());flash('success','System alert created for all users.');
    }elseif($action==='deactivate'){
        db()->prepare('UPDATE system_alerts SET active=0 WHERE id=?')->execute([(int)post('alert_id')]);audit('deactivate','system_alert',(int)post('alert_id'));flash('success','System alert deactivated.');
    }
    redirect('/admin/system-alerts');
}
$alerts=db()->query('SELECT a.*,u.username FROM system_alerts a LEFT JOIN users u ON u.id=a.created_by ORDER BY a.due_at DESC LIMIT 200')->fetchAll();
layout('System alerts',function()use($alerts){?><div class="actions"><h1 style="margin:0">System alerts</h1><span class="spacer"></span><a class="btn secondary" href="/admin/settings">Application settings</a></div><form class="card" method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="create"><h2>Create an alert for everyone</h2><div class="form-grid"><label>Message<input name="message" placeholder="Office closed Friday" required></label><label>Target date and time<input type="datetime-local" name="due_at" required></label></div><button>Create system alert</button></form><div class="card"><h2>System alert history</h2><table><thead><tr><th>Message</th><th>Target time</th><th>Created by</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($alerts as $a):?><tr><td><?=e($a['message'])?></td><td><?=e($a['due_at'])?></td><td><?=e($a['username'])?></td><td><?=$a['active']?'Active':'Inactive'?></td><td><?php if($a['active']):?><form method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="alert_id" value="<?=$a['id']?>"><button class="danger">Deactivate</button></form><?php endif?></td></tr><?php endforeach?></tbody></table></div><?php });exit;
