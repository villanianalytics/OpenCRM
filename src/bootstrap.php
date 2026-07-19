<?php
declare(strict_types=1);

$composerAutoload=dirname(__DIR__).'/vendor/autoload.php';
if(is_file($composerAutoload))require_once $composerAutoload;

$config = require dirname(__DIR__) . '/config.php';
date_default_timezone_set($config['timezone']);

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('opencrm_session');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'secure' => $config['secure_session'],
        'httponly' => true, 'samesite' => 'Lax',
    ]);
    session_start();
}

function config(?string $key = null): mixed {
    global $config;
    return $key === null ? $config : ($config[$key] ?? null);
}

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $c = config('db');
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone=".$pdo->quote(date('P')));
    }
    return $pdo;
}

function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $path): never { header('Location: ' . $path); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function verify_csrf(): void {
    if (!isset($_POST['_csrf']) || !hash_equals($_SESSION['csrf'] ?? '', (string) $_POST['_csrf'])) {
        http_response_code(419); exit('Your session expired. Please go back and try again.');
    }
}
function flash(string $type, string $message): void { $_SESSION['flash'][] = compact('type', 'message'); }
function user(): ?array { return $_SESSION['user'] ?? null; }
function require_login(): void { if (!user()) redirect('/login'); }
function can(string $permission): bool {
    $u = user();
    if(!$u)return false;if($u['is_admin'])return true;$permissions=$u['permissions']??[];if(in_array($permission,$permissions,true))return true;
    if(str_ends_with($permission,'.view')&&in_array(substr($permission,0,-5).'.edit',$permissions,true))return true;
    return false;
}
function require_permission(string $permission): void {
    require_login();
    if (!can($permission)) { http_response_code(403); exit('Forbidden'); }
}
function can_edit_record(array $record): bool {if(user()['is_admin']??false)return true;$uid=(int)(user()['id']??0);return $uid>0&&($uid===(int)($record['created_by']??0)||$uid===(int)($record['owner_id']??0));}
function require_record_edit(array $record): void {if(!can_edit_record($record)){http_response_code(403);exit('Only the record creator, assigned owner, or a system administrator can modify this record.');}}
function audit(string $action, string $entity, ?int $entityId = null, array $details = []): void {
    $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details_json, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([user()['id'] ?? null, $action, $entity, $entityId, json_encode($details), $_SERVER['REMOTE_ADDR'] ?? null]);
    app_log('info',ucwords(str_replace('_',' ',$action)).' '.$entity,['entity_id'=>$entityId]+$details);
}
function record_contact_changes(int $contactId,array $before,array $after): void {
    $labels=['first_name'=>'First name','last_name'=>'Last name','company'=>'Company','job_title'=>'Job title','email'=>'Email','phone'=>'Phone','address'=>'Address','website'=>'Website','active'=>'Status','owner_id'=>'Owner'];$stmt=db()->prepare('INSERT INTO contact_field_changes(contact_id,user_id,field_name,old_value,new_value) VALUES(?,?,?,?,?)');
    foreach($labels as $field=>$label){$old=(string)($before[$field]??'');$new=(string)($after[$field]??'');if($field==='active'){$old=$old==='1'?'Active':'Inactive';$new=$new==='1'?'Active':'Inactive';}if($old!==$new)$stmt->execute([$contactId,user()['id']??null,$label,$old,$new]);}
}
function app_log(string $level,string $message,array $context=[]): void {
    $weights=['debug'=>10,'info'=>20,'warning'=>30,'error'=>40,'off'=>100];$level=in_array($level,array_keys($weights),true)?$level:'info';$configured=app_setting('logging_level','info');if(($weights[$level]??20)<($weights[$configured]??20))return;
    try{$stmt=db()->prepare('INSERT INTO application_logs(level,message,context_json,user_id,ip_address) VALUES(?,?,?,?,?)');$stmt->execute([$level,mb_substr($message,0,500),$context?json_encode($context,JSON_UNESCAPED_SLASHES):null,user()['id']??null,$_SERVER['REMOTE_ADDR']??null]);}catch(Throwable $e){error_log('OpenCRM log write failed: '.$e->getMessage());}
}
function redact_request_data(mixed $value,?string $key=null): mixed {
    if($key!==null&&preg_match('/password|passwd|authorization|token|secret|api[_-]?key|cookie|csrf/i',$key))return '[REDACTED]';
    if(is_array($value)){foreach($value as $k=>$item)$value[$k]=redact_request_data($item,(string)$k);return $value;}
    if(is_string($value)){$value=preg_replace('/((?:password|passwd|authorization|token|secret|api[_-]?key|cookie|csrf)[\\\\"\'\s:=]*)[^,}&\r\n]*/i','$1[REDACTED]',$value);if(strlen($value)>4000)$value=substr($value,0,4000).'… [truncated]';}return $value;
}
function start_request_log(string $path,string $method): void {
    $started=microtime(true);$contentType=strtolower((string)($_SERVER['CONTENT_TYPE']??''));$raw='';$payload=[];
    if(!in_array($method,['GET','HEAD'],true)){$raw=(string)file_get_contents('php://input');if(str_contains($contentType,'application/json')){$decoded=json_decode($raw,true);$payload=is_array($decoded)?$decoded:['_raw'=>$raw,'_json_error'=>json_last_error_msg()];}elseif($_POST)$payload=$_POST;elseif($raw!=='')$payload=['_raw'=>$raw];}
    $context=['method'=>$method,'path'=>$path,'query'=>redact_request_data($_GET),'content_type'=>$contentType?:null,'content_length'=>(int)($_SERVER['CONTENT_LENGTH']??0),'user_agent'=>mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,300),'payload'=>redact_request_data($payload)];
    register_shutdown_function(function()use($started,$method,$path,$context){$context['status']=http_response_code();$context['duration_ms']=(int)round((microtime(true)-$started)*1000);$error=error_get_last();if($error&&in_array($error['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true))$context['fatal_error']=$error['message'];$level=$context['status']>=500?'error':($context['status']>=400?'warning':'info');try{$stmt=db()->prepare('INSERT INTO application_logs(level,message,context_json,user_id,ip_address) VALUES(?,?,?,?,?)');$stmt->execute([$level,"HTTP $method $path -> {$context['status']}",json_encode($context,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE),user()['id']??null,$_SERVER['REMOTE_ADDR']??null]);}catch(Throwable $e){error_log('Request log failed: '.$e->getMessage());}});
}
function all_tags(): array { return db()->query("SELECT t.*,tg.name group_name FROM tags t LEFT JOIN tag_groups tg ON tg.id=t.tag_group_id ORDER BY COALESCE(tg.position,999999),COALESCE(tg.name,'Ungrouped'),t.name")->fetchAll(); }
function tag_access(int $tagId): string {
    if (user()['is_admin'] ?? false) return 'write';
    $stmt = db()->prepare('SELECT access_level FROM tag_permissions WHERE tag_id=? AND role_id=?');
    $stmt->execute([$tagId, user()['role_id']]);
    return $stmt->fetchColumn() ?: 'write';
}
function contact_access(int $contactId): string {
    if (user()['is_admin'] ?? false) return 'write';
    $stmt = db()->prepare("SELECT tp.access_level FROM contact_tags ct JOIN tag_permissions tp ON tp.tag_id=ct.tag_id AND tp.role_id=? WHERE ct.contact_id=? ORDER BY FIELD(tp.access_level,'hidden','read','write') LIMIT 1");
    $stmt->execute([user()['role_id'], $contactId]);
    return $stmt->fetchColumn() ?: 'write';
}
function visible_contact_sql(string $alias = 'c'): string {
    if (user()['is_admin'] ?? false) return '1=1';
    $roleId = (int) (user()['role_id'] ?? 0);
    return "NOT EXISTS (SELECT 1 FROM contact_tags vct JOIN tag_permissions vtp ON vtp.tag_id=vct.tag_id AND vtp.role_id={$roleId} AND vtp.access_level='hidden' WHERE vct.contact_id={$alias}.id)";
}
function app_settings(bool $refresh=false): array {
    static $settings;
    if($refresh)$settings=null;
    if ($settings === null) {
        $settings = ['app_name'=>'OpenCRM','primary_color'=>'#1565c0','accent_color'=>'#43a047','logo_path'=>'','logo_mime'=>'','logging_level'=>'info','app_timezone'=>'America/New_York','mail_transport'=>'local','mail_from_address'=>'','mail_from_name'=>'','smtp_host'=>'','smtp_port'=>'587','smtp_encryption'=>'tls','smtp_username'=>'','smtp_password_enc'=>'','openai_api_key_enc'=>'','openai_model'=>'gpt-5.4-mini','booking_engine'=>'native','easyappointments_url'=>'','easyappointments_api_token_enc'=>'','google_calendar_client_id'=>'','google_calendar_client_secret_enc'=>'','microsoft_calendar_client_id'=>'','microsoft_calendar_client_secret_enc'=>'','microsoft_calendar_tenant'=>'common'];
        try {
            foreach (db()->query('SELECT setting_key,setting_value FROM app_settings')->fetchAll() as $row) $settings[$row['setting_key']]=$row['setting_value'];
        } catch (PDOException) {
            // Settings are optional until the migration has run.
        }
    }
    return $settings;
}
function app_setting(string $key, string $default = ''): string { return (string)(app_settings()[$key] ?? $default); }
function encrypt_secret(string $value): string {$key=hash('sha256',(string)config('key'),true);$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($value,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new RuntimeException('Could not encrypt the mail password.');return base64_encode($iv.$tag.$cipher);}
function decrypt_secret(string $value): string {if($value==='')return '';$raw=base64_decode($value,true);if($raw===false||strlen($raw)<29)return '';$key=hash('sha256',(string)config('key'),true);$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));return $plain===false?'':$plain;}
function smtp_read($socket): string {$response='';do{$line=fgets($socket,4096);if($line===false)throw new RuntimeException('SMTP server closed the connection.');$response.=$line;}while(isset($line[3])&&$line[3]==='-');return $response;}
function smtp_command($socket,string $command,array $expected): string {fwrite($socket,$command."\r\n");$response=smtp_read($socket);$code=(int)substr($response,0,3);if(!in_array($code,$expected,true))throw new RuntimeException('SMTP rejected a command: '.trim(preg_replace('/\s+/',' ',$response)));return $response;}
function crm_send_email(string $to,string $subject,string $body,bool $transactional=true): bool {
    app_settings(true);
    if(!$transactional){$email=strtolower(trim($to));$s=db()->prepare('SELECT COUNT(*) FROM email_suppressions WHERE email=? AND released_at IS NULL');$s->execute([$email]);if($s->fetchColumn())throw new RuntimeException('Recipient is on the email suppression list.');$s=db()->prepare("SELECT c.id contact_id,p.status,p.unsubscribe_token FROM contacts c LEFT JOIN contact_email_preferences p ON p.contact_id=c.id WHERE LOWER(c.email)=? LIMIT 1");$s->execute([$email]);$pref=$s->fetch();if($pref&&!$pref['unsubscribe_token']){db()->prepare("INSERT IGNORE INTO contact_email_preferences(contact_id,status,consent_source,consent_at,unsubscribe_token) VALUES(?,'subscribed','crm_existing_contact',NOW(),?)")->execute([$pref['contact_id'],bin2hex(random_bytes(32))]);$s->execute([$email]);$pref=$s->fetch();}if($pref&&in_array($pref['status'],['unsubscribed','transactional_only'],true))throw new RuntimeException('Recipient has not consented to marketing email.');if($pref&&$pref['unsubscribe_token'])$body=rtrim($body)."\n\nManage email preferences: ".rtrim((string)config('url'),'/').'/email/unsubscribe/'.$pref['unsubscribe_token'];}
    if(!filter_var($to,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Recipient email is invalid.');$from=app_setting('mail_from_address','notifications@'.(parse_url((string)config('url'),PHP_URL_HOST)?:'localhost'));$fromName=app_setting('mail_from_name',app_setting('app_name','OpenCRM'));$headers=['From: '.$fromName.' <'.$from.'>','MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','X-Auto-Response-Suppress: All'];
    if(app_setting('mail_transport','local')==='local')return @mail($to,$subject,$body,implode("\r\n",$headers));
    $host=app_setting('smtp_host');$port=(int)app_setting('smtp_port','587');$encryption=app_setting('smtp_encryption','tls');if($host===''||!filter_var($from,FILTER_VALIDATE_EMAIL))throw new RuntimeException('SMTP host and sender address are required.');$target=($encryption==='ssl'?'ssl://':'').$host.':'.$port;$socket=@stream_socket_client($target,$errno,$error,15,STREAM_CLIENT_CONNECT);if(!$socket)throw new RuntimeException("SMTP connection failed: $error ($errno)");stream_set_timeout($socket,15);try{$hello=smtp_read($socket);if((int)substr($hello,0,3)!==220)throw new RuntimeException('SMTP greeting failed.');smtp_command($socket,'EHLO '.(parse_url((string)config('url'),PHP_URL_HOST)?:'localhost'),[250]);if($encryption==='tls'){smtp_command($socket,'STARTTLS',[220]);if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('SMTP TLS negotiation failed.');smtp_command($socket,'EHLO '.(parse_url((string)config('url'),PHP_URL_HOST)?:'localhost'),[250]);}$username=app_setting('smtp_username');$password=decrypt_secret(app_setting('smtp_password_enc'));if($username!==''){smtp_command($socket,'AUTH LOGIN',[334]);smtp_command($socket,base64_encode($username),[334]);smtp_command($socket,base64_encode($password),[235]);}smtp_command($socket,'MAIL FROM:<'.$from.'>',[250]);smtp_command($socket,'RCPT TO:<'.$to.'>',[250,251]);smtp_command($socket,'DATA',[354]);$message='Subject: '.$subject."\r\n".'To: <'.$to.'>'."\r\n".implode("\r\n",$headers)."\r\n\r\n".$body;$message=preg_replace('/(?m)^\./','..',str_replace(["\r\n","\r"],"\n",$message));fwrite($socket,str_replace("\n","\r\n",$message)."\r\n.\r\n");$response=smtp_read($socket);if((int)substr($response,0,3)!==250)throw new RuntimeException('SMTP message delivery was rejected: '.trim($response));smtp_command($socket,'QUIT',[221]);return true;}finally{fclose($socket);}
}
$configuredTimezone=app_setting('app_timezone','America/New_York');date_default_timezone_set(in_array($configuredTimezone,timezone_identifiers_list(),true)?$configuredTimezone:'America/New_York');
function due_alerts_for_user(int $userId): array {
    $s=db()->prepare("SELECT r.id,r.contact_id target_id,r.message,r.due_at,'contact' alert_type,CONCAT(c.first_name,' ',c.last_name) target_name,c.company detail FROM reminders r JOIN contacts c ON c.id=r.contact_id WHERE r.user_id=? AND r.completed_at IS NULL AND r.due_at<=NOW()");$s->execute([$userId]);$alerts=array_values(array_filter($s->fetchAll(),fn($r)=>contact_access((int)$r['target_id'])!=='hidden'));
    if(can('events.view')){$s=db()->prepare("SELECT r.id,r.event_id target_id,r.message,r.due_at,'event' alert_type,e.title target_name,CONCAT(e.event_type,IF(e.presentation_title IS NULL OR e.presentation_title='','',CONCAT(' - ',e.presentation_title))) detail FROM event_reminders r JOIN events e ON e.id=r.event_id WHERE r.user_id=? AND r.completed_at IS NULL AND r.due_at<=NOW()");$s->execute([$userId]);$alerts=array_merge($alerts,$s->fetchAll());}
    $s=db()->prepare("SELECT a.id,0 target_id,a.message,COALESCE(st.snoozed_until,a.due_at) due_at,'system' alert_type,'System alert' target_name,'For all users' detail FROM system_alerts a LEFT JOIN system_alert_states st ON st.system_alert_id=a.id AND st.user_id=? WHERE a.active=1 AND a.due_at<=NOW() AND st.completed_at IS NULL AND (st.snoozed_until IS NULL OR st.snoozed_until<=NOW())");$s->execute([$userId]);$alerts=array_merge($alerts,$s->fetchAll());usort($alerts,fn($a,$b)=>strcmp($a['due_at'],$b['due_at']));return $alerts;
}
function event_tag_name(array $event): string {
    $name = preg_replace('/[^\pL\pN]+/u', '_', trim($event['title'].'_'.$event['event_type']));
    return mb_substr(trim((string)$name, '_'), 0, 100);
}
function add_event_attendee(int $eventId, int $contactId): void {
    $stmt=db()->prepare('SELECT id,title,event_type FROM events WHERE id=?'); $stmt->execute([$eventId]); $event=$stmt->fetch();
    if(!$event) throw new RuntimeException('Event not found.');
    db()->prepare('INSERT INTO event_attendees(event_id,contact_id,attended) VALUES(?,?,1) ON DUPLICATE KEY UPDATE attended=1')->execute([$eventId,$contactId]);
    $tagName=event_tag_name($event); $stmt=db()->prepare("INSERT INTO tags(name,color) VALUES(?,'#00838f') ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)");
    $stmt->execute([$tagName]); $tagId=(int)db()->lastInsertId();
    db()->prepare('INSERT IGNORE INTO contact_tags(contact_id,tag_id) VALUES(?,?)')->execute([$contactId,$tagId]);
}
function list_options(string $listKey,bool $includeInactive=false): array {
    $sql='SELECT ov.* FROM option_values ov JOIN option_lists ol ON ol.id=ov.list_id WHERE ol.list_key=?';if(!$includeInactive)$sql.=' AND ov.active=1';$sql.=' ORDER BY ov.position,ov.label';
    $stmt=db()->prepare($sql);$stmt->execute([$listKey]);return $stmt->fetchAll();
}
function option_codes(string $listKey): array { return array_column(list_options($listKey),'code'); }
function option_label(string $listKey,string $code): string { foreach(list_options($listKey,true) as $option)if($option['code']===$code)return $option['label'];return str_replace('_',' ',$code); }
function option_color(string $listKey,string $code,string $default='#546e7a'): string { foreach(list_options($listKey,true) as $option)if($option['code']===$code)return $option['color']?:$default;return $default; }
function sync_contact_company(int $contactId,string $companyName): ?int {
    $companyName=trim($companyName);if($companyName===''){db()->prepare('UPDATE contacts SET company_id=NULL WHERE id=?')->execute([$contactId]);return null;}
    $stmt=db()->prepare('INSERT INTO companies(name) VALUES(?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');$stmt->execute([$companyName]);$companyId=(int)db()->lastInsertId();
    db()->prepare('UPDATE contacts SET company_id=?,company=? WHERE id=?')->execute([$companyId,$companyName,$contactId]);return $companyId;
}
function apply_user_default_tags(int $contactId,int $userId): void {
    $stmt=db()->prepare('INSERT IGNORE INTO contact_tags(contact_id,tag_id) SELECT ?,tag_id FROM user_default_tags WHERE user_id=?');$stmt->execute([$contactId,$userId]);schedule_tag_notifications($contactId);
}
function schedule_tag_notifications(int $contactId): void {static $contacts=[],$registered=false;$contacts[$contactId]=true;if($registered)return;$registered=true;register_shutdown_function(function()use(&$contacts){foreach(array_keys($contacts) as $id)try{send_tag_notifications((int)$id);}catch(Throwable $e){app_log('error','Tag notification processing failed',['contact_id'=>$id,'error'=>$e->getMessage()]);}});}
function send_tag_notifications(int $contactId): void {
    $contactStmt=db()->prepare('SELECT * FROM contacts WHERE id=?');$contactStmt->execute([$contactId]);$contact=$contactStmt->fetch();if(!$contact)return;
    $stmt=db()->prepare("SELECT s.user_id,u.email,COALESCE(NULLIF(u.display_name,''),u.username) recipient,t.id tag_id,t.name tag_name FROM user_tag_subscriptions s JOIN users u ON u.id=s.user_id AND u.active=1 AND u.email IS NOT NULL AND u.email<>'' JOIN tags t ON t.id=s.tag_id JOIN contact_tags ct ON ct.tag_id=s.tag_id AND ct.contact_id=? LEFT JOIN tag_notification_deliveries d ON d.user_id=s.user_id AND d.contact_id=? AND d.tag_id=s.tag_id WHERE d.tag_id IS NULL ORDER BY s.user_id,t.name");$stmt->execute([$contactId,$contactId]);$byUser=[];foreach($stmt->fetchAll() as $row)$byUser[$row['user_id']][]=$row;
    $host=parse_url((string)config('url'),PHP_URL_HOST)?:'localhost';$from='notifications@'.$host;$subject=app_setting('app_name','OpenCRM').': new contact matching your tag subscription';$delivery=db()->prepare('INSERT IGNORE INTO tag_notification_deliveries(user_id,contact_id,tag_id) VALUES(?,?,?)');
    foreach($byUser as $userId=>$rows){$tagNames=array_column($rows,'tag_name');$body="Hello {$rows[0]['recipient']},\n\nA new contact was added with a tag you follow: ".implode(', ',$tagNames)."\n\nName: {$contact['first_name']} {$contact['last_name']}\nCompany: ".($contact['company']?:'—')."\nEmail: ".($contact['email']?:'—')."\nPhone: ".($contact['phone']?:'—')."\n\nView contact: ".rtrim((string)config('url'),'/').'/contacts/'.$contactId."\n";try{$sent=crm_send_email($rows[0]['email'],$subject,$body);}catch(Throwable $e){$sent=false;app_log('error','Tag subscription email transport failed',['contact_id'=>$contactId,'user_id'=>(int)$userId,'error'=>$e->getMessage()]);}if($sent){foreach($rows as $row)$delivery->execute([$userId,$contactId,$row['tag_id']]);app_log('info','Tag subscription email sent',['contact_id'=>$contactId,'user_id'=>(int)$userId,'tag_ids'=>array_map('intval',array_column($rows,'tag_id'))]);}else app_log('error','Tag subscription email could not be sent',['contact_id'=>$contactId,'user_id'=>(int)$userId,'email'=>$rows[0]['email']]);}
}
function custom_field_definitions(bool $activeOnly=true): array {
    $sql='SELECT cf.*,cfg.name group_name,cfg.position group_position FROM custom_fields cf LEFT JOIN custom_field_groups cfg ON cfg.id=cf.custom_field_group_id'.($activeOnly?' WHERE cf.active=1 AND (cfg.active=1 OR cfg.id IS NULL)':'').' ORDER BY COALESCE(cfg.position,999999),COALESCE(cfg.name,\'Other information\'),cf.position,cf.name';$fields=db()->query($sql)->fetchAll();
    $optionStmt=db()->prepare('SELECT * FROM custom_field_options WHERE custom_field_id=?'.($activeOnly?' AND active=1':'').' ORDER BY position,option_value');$tagStmt=db()->prepare('SELECT tag_id FROM custom_field_tags WHERE custom_field_id=?');$conditionStmt=db()->prepare('SELECT c.*,f.name depends_on_name FROM custom_field_conditions c JOIN custom_fields f ON f.id=c.depends_on_field_id WHERE c.custom_field_id=? ORDER BY c.id');
    foreach($fields as &$field){$optionStmt->execute([$field['id']]);$field['options']=$optionStmt->fetchAll();$tagStmt->execute([$field['id']]);$field['tag_ids']=array_map('intval',$tagStmt->fetchAll(PDO::FETCH_COLUMN));$conditionStmt->execute([$field['id']]);$field['conditions']=$conditionStmt->fetchAll();}unset($field);return $fields;
}
function custom_condition_matches(array $condition,string $value): bool {return match($condition['operator']){'equals'=>$value===(string)$condition['expected_value'],'not_equals'=>$value!==(string)$condition['expected_value'],'is_empty'=>$value==='','not_empty'=>$value!=='',default=>false};}
function save_contact_custom_values(int $contactId,array $values): void {
    $stmt=db()->prepare('SELECT tag_id FROM contact_tags WHERE contact_id=?');$stmt->execute([$contactId]);$contactTags=array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    $stmt=db()->prepare('SELECT custom_field_id,field_value FROM contact_custom_values WHERE contact_id=?');$stmt->execute([$contactId]);$combined=array_column($stmt->fetchAll(),'field_value','custom_field_id');foreach($values as $id=>$value)$combined[(int)$id]=trim((string)$value);
    $save=db()->prepare('INSERT INTO contact_custom_values(contact_id,custom_field_id,field_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE field_value=VALUES(field_value)');
    foreach(custom_field_definitions() as $field){$id=(int)$field['id'];if(!array_key_exists($id,$values))continue;if($field['tag_ids']&&!array_intersect($field['tag_ids'],$contactTags))continue;foreach($field['conditions'] as $condition)if(!custom_condition_matches($condition,(string)($combined[(int)$condition['depends_on_field_id']]??'')))continue 2;$value=trim((string)$values[$id]);if($field['field_type']==='list'&&!in_array($value,array_column($field['options'],'option_value'),true))$value='';if($field['field_type']==='email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL))continue;if($field['field_type']==='url'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_URL))continue;if(in_array($field['field_type'],['number','currency'],true)&&$value!==''&&!is_numeric($value))continue;if($field['validation_pattern']&&$value!==''&&@preg_match('/'.$field['validation_pattern'].'/u',$value)!==1)continue;$save->execute([$contactId,$id,$value]);}
}
