<?php
declare(strict_types=1);

if ($path !== '/admin/settings') return;
if (!(user()['is_admin'] ?? false)) { http_response_code(403); exit('Forbidden'); }

if ($method === 'POST') {
    verify_csrf();
    $name = trim((string)post('app_name'));
    $primary = strtolower(trim((string)post('primary_color')));
    $accent = strtolower(trim((string)post('accent_color')));
    if ($name === '' || mb_strlen($name) > 80) {
        flash('error', 'Application name is required and must be 80 characters or fewer.');
        redirect('/admin/settings');
    }
    if (!preg_match('/^#[0-9a-f]{6}$/', $primary) || !preg_match('/^#[0-9a-f]{6}$/', $accent)) {
        flash('error', 'Choose valid six-digit colors.'); redirect('/admin/settings');
    }
    $loggingLevel=in_array(post('logging_level'),['debug','info','warning','error','off'],true)?post('logging_level'):'info';$timezone=(string)post('app_timezone');if(!in_array($timezone,timezone_identifiers_list(),true)){$timezone='America/New_York';flash('error','Invalid timezone; New York time was retained.');}
    $values = ['app_name'=>$name,'primary_color'=>$primary,'accent_color'=>$accent,'logging_level'=>$loggingLevel,'app_timezone'=>$timezone];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $upload = $_FILES['logo'];
        $allowed = ['image/png'=>'png','image/jpeg'=>'jpg','image/gif'=>'gif','image/webp'=>'webp'];
        $mime = $upload['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']) : '';
        if ($upload['error'] !== UPLOAD_ERR_OK || $upload['size'] > 3*1024*1024 || !isset($allowed[$mime])) {
            flash('error', 'Logo must be a PNG, JPEG, GIF, or WebP image no larger than 3 MB.'); redirect('/admin/settings');
        }
        $filename = 'brand-'.bin2hex(random_bytes(16)).'.'.$allowed[$mime];
        if (!move_uploaded_file($upload['tmp_name'], dirname(__DIR__).'/storage/uploads/'.$filename)) throw new RuntimeException('Logo upload failed.');
        $values['logo_path']=$filename; $values['logo_mime']=$mime;
    }
    if (post('remove_logo')) { $values['logo_path']=''; $values['logo_mime']=''; }
    $stmt=db()->prepare('INSERT INTO app_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    foreach($values as $key=>$value) $stmt->execute([$key,$value]);
    audit('update','app_settings',null,['keys'=>array_keys($values)]);
    flash('success','Application branding updated.'); redirect('/admin/settings');
}

$settings=app_settings();
layout('Application settings',function()use($settings){?>
<div class="actions"><h1 style="margin:0">Application settings</h1></div>
<form class="card" method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?=csrf()?>">
<div class="form-grid"><label>Application name<input name="app_name" value="<?=e($settings['app_name'])?>" maxlength="80" required></label><div></div>
<label>Primary color<input type="color" name="primary_color" value="<?=e($settings['primary_color'])?>"></label>
<label>Accent color<input type="color" name="accent_color" value="<?=e($settings['accent_color'])?>"></label>
<label>Logging level<select name="logging_level"><option value="debug" <?=$settings['logging_level']==='debug'?'selected':''?>>Debug — everything</option><option value="info" <?=$settings['logging_level']==='info'?'selected':''?>>Info — normal activity</option><option value="warning" <?=$settings['logging_level']==='warning'?'selected':''?>>Warning — warnings and errors</option><option value="error" <?=$settings['logging_level']==='error'?'selected':''?>>Error — errors only</option><option value="off" <?=$settings['logging_level']==='off'?'selected':''?>>Off</option></select></label><div></div>
<label>Application timezone<select name="app_timezone"><?php foreach(timezone_identifiers_list() as $timezone):?><option value="<?=e($timezone)?>" <?=$settings['app_timezone']===$timezone?'selected':''?>><?=e(str_replace('_',' ',$timezone))?></option><?php endforeach?></select><span class="muted">Used for events, reminders, logs, and reports. Defaults to America/New York.</span></label><div></div>
<label class="full">Logo (PNG, JPEG, GIF, or WebP; maximum 3 MB)<input type="file" name="logo" accept="image/png,image/jpeg,image/gif,image/webp"></label>
<?php if($settings['logo_path']):?><div class="full"><img src="/branding/logo" alt="Current logo" style="max-width:240px;max-height:120px"><label><input style="width:auto" type="checkbox" name="remove_logo" value="1"> Remove current logo</label></div><?php endif?>
</div><button>Save application settings</button></form><?php });exit;
