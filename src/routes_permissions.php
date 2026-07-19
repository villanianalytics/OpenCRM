<?php
declare(strict_types=1);
if($path!=='/admin/permissions')return;
if(!(user()['is_admin']??false)){http_response_code(403);exit('Forbidden');}
$catalog=[
 'contacts'=>['Contacts','View and edit CRM contacts'],
 'events'=>['Events','View and manage events'],
 'opportunities'=>['Opportunities','View and manage opportunities'],
 'reports'=>['Reports','View and build reports'],
 'lead_magnets'=>['Lead Magnet Generator','Generate, edit, publish, and download lead magnets'],
 'forms'=>['Form Generator','Build and publish forms'],
 'promotional_links'=>['QR codes and promotional links','Create tracked links, QR codes, and view analytics'],
 'sites'=>['Website and landing-page builder','Build, publish, and analyze websites'],
 'bookings'=>['Bookings','Manage calendars, meeting types, and appointments'],
 'communications'=>['Communications','Send email and manage contact conversations'],
 'workflows'=>['Workflows','Build and monitor lightweight CRM automations'],
 'resources'=>['Resources Library','Publish gated resource portals and review engagement'],
];
if($method==='POST'){
 verify_csrf();$roleId=(int)post('role_id');$role=db()->prepare('SELECT * FROM roles WHERE id=?');$role->execute([$roleId]);$row=$role->fetch();if(!$row){http_response_code(404);exit('Role not found');}
 $existing=json_decode((string)$row['permissions_json'],true)?:[];$managed=[];foreach(array_keys($catalog) as $key){$managed[]=$key.'.view';$managed[]=$key.'.edit';}
 $permissions=array_values(array_diff($existing,$managed));foreach((array)post('permissions',[]) as $permission)if(in_array($permission,$managed,true))$permissions[]=$permission;
 db()->prepare('UPDATE roles SET permissions_json=? WHERE id=?')->execute([json_encode(array_values(array_unique($permissions))),$roleId]);audit('update','role_permissions',$roleId,['permissions'=>$permissions]);flash('success','Role permissions updated. Users receive the changes on their next sign-in.');redirect('/admin/permissions?role_id='.$roleId);
}
$roles=db()->query('SELECT * FROM roles ORDER BY name')->fetchAll();$selected=(int)($_GET['role_id']??($roles[0]['id']??0));$current=[];foreach($roles as $role)if((int)$role['id']===$selected)$current=json_decode((string)$role['permissions_json'],true)?:[];
layout('Role permissions',function()use($roles,$selected,$current,$catalog){?><div class="actions"><h1 style="margin:0">Role permissions</h1></div><form class="card" method="get"><label>Role<select name="role_id" onchange="this.form.submit()"><?php foreach($roles as $role):?><option value="<?=$role['id']?>" <?=$selected==(int)$role['id']?'selected':''?>><?=e($role['name'])?></option><?php endforeach?></select></label></form><?php if($selected):?><form class="card" method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="role_id" value="<?=$selected?>"><table><thead><tr><th>Feature</th><th>View/use</th><th>Create/edit</th><th>Description</th></tr></thead><tbody><?php foreach($catalog as $key=>[$label,$description]):?><tr><td><strong><?=e($label)?></strong></td><td><input type="checkbox" name="permissions[]" value="<?=e($key)?>.view" <?=in_array($key.'.view',$current,true)||in_array($key.'.edit',$current,true)?'checked':''?>></td><td><input type="checkbox" name="permissions[]" value="<?=e($key)?>.edit" <?=in_array($key.'.edit',$current,true)?'checked':''?>></td><td class="muted"><?=e($description)?></td></tr><?php endforeach?></tbody></table><p class="muted">Create/edit permission automatically includes view access.</p><button>Save permissions</button></form><?php endif?><?php });exit;
