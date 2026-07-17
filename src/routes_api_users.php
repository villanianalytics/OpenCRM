<?php
declare(strict_types=1);

if($path!=='/admin/api-users')return;
if(!(user()['is_admin']??false)){http_response_code(403);exit('Forbidden');}
if($method==='POST'){
    verify_csrf();$action=(string)post('action');
    if($action==='create'){$name=trim((string)post('name'));if($name===''){flash('error','API user name is required.');redirect('/admin/api-users');}$mode=post('access_mode')==='create_only'?'create_only':'upsert';$token='crm_'.bin2hex(random_bytes(32));db()->prepare('INSERT INTO api_users(name,token_hash,token_prefix,access_mode,created_by) VALUES(?,?,?,?,?)')->execute([$name,hash('sha256',$token),substr($token,0,12),$mode,user()['id']]);$_SESSION['new_api_token']=$token;audit('create','api_user',(int)db()->lastInsertId());flash('success','API user created. Copy its token now; it will not be shown again.');}
    elseif($action==='revoke'){db()->prepare('UPDATE api_users SET active=0 WHERE id=?')->execute([(int)post('api_user_id')]);audit('revoke','api_user',(int)post('api_user_id'));flash('success','API user revoked.');}
    redirect('/admin/api-users');
}
$apiUsers=db()->query('SELECT * FROM api_users ORDER BY created_at DESC')->fetchAll();$newToken=$_SESSION['new_api_token']??null;unset($_SESSION['new_api_token']);
layout('API users',function()use($apiUsers,$newToken){?><div class="actions"><h1 style="margin:0">API users</h1><span class="spacer"></span><a class="btn secondary" href="/admin/settings">Application settings</a></div><?php if($newToken):?><div class="card"><h2>Copy this token now</h2><p class="danger-text">For security, this token will not be displayed again.</p><input readonly value="<?=e($newToken)?>" onclick="this.select()"></div><?php endif?><form class="card" method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="create"><h2>Create API user</h2><label>Name<input name="name" placeholder="GoHighLevel synchronization" required></label><label>Access mode<select name="access_mode"><option value="create_only">Create contacts only</option><option value="upsert">Create and update contacts</option></select></label><button>Create API user and token</button></form><div class="card"><h2>GoHighLevel request</h2><p>Send <code>POST <?=e(config('url'))?>/api/v1/contacts</code> with <code>Content-Type: application/json</code> and <code>Authorization: Bearer YOUR_TOKEN</code>.</p><pre style="overflow:auto;background:#f4f7fb;padding:14px;border-radius:4px">{
  "external_id": "{{contact.id}}",
  "first_name": "{{contact.first_name}}",
  "last_name": "{{contact.last_name}}",
  "email": "{{contact.email}}",
  "phone": "{{contact.phone}}",
  "company": "{{contact.company_name}}",
  "tags": ["GHL", "Web Lead"]
}</pre><p class="muted">Set <code>replace_tags</code> to true only if GoHighLevel should replace every existing CRM tag. Otherwise supplied tags are added.</p></div><div class="card"><h2>API users</h2><table><thead><tr><th>Name</th><th>Token</th><th>Access</th><th>Last used</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($apiUsers as $a):?><tr><td><?=e($a['name'])?></td><td><code><?=e($a['token_prefix'])?>…</code></td><td><?=e($a['access_mode']==='create_only'?'Create only':'Create + update')?></td><td><?=e($a['last_used_at']?:'Never')?></td><td><?=$a['active']?'Active':'Revoked'?></td><td><?php if($a['active']):?><form method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="revoke"><input type="hidden" name="api_user_id" value="<?=$a['id']?>"><button class="danger">Revoke</button></form><?php endif?></td></tr><?php endforeach?></tbody></table></div><?php });exit;
