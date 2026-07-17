<?php
declare(strict_types=1);

function api_response(array $data,int $status=200): never { http_response_code($status);header('Content-Type: application/json');header('Cache-Control: no-store');echo json_encode($data,JSON_UNESCAPED_SLASHES);exit; }
function api_token(): string {
    $header=$_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
    if(preg_match('/^Bearer\s+(.+)$/i',$header,$m))return trim($m[1]);
    return trim((string)($_SERVER['HTTP_X_API_TOKEN']??''));
}
$token=api_token();if($token==='')api_response(['error'=>'Missing authentication token.'],401);
$stmt=db()->prepare('SELECT * FROM api_users WHERE token_hash=? AND active=1');$stmt->execute([hash('sha256',$token)]);$apiUser=$stmt->fetch();
if(!$apiUser)api_response(['error'=>'Invalid or revoked authentication token.'],401);
db()->prepare('UPDATE api_users SET last_used_at=NOW() WHERE id=?')->execute([$apiUser['id']]);

if($path!=='/api/v1/contacts')api_response(['error'=>'API endpoint not found.'],404);
if($method!=='POST')api_response(['error'=>'Method not allowed.'],405);
$contentType=strtolower($_SERVER['CONTENT_TYPE']??'');if(!str_contains($contentType,'application/json'))api_response(['error'=>'Content-Type must be application/json.'],415);
$payload=json_decode(file_get_contents('php://input')?:'',true);if(!is_array($payload))api_response(['error'=>'Request body must be valid JSON.'],400);

$externalId=trim((string)($payload['external_id']??$payload['ghl_contact_id']??''));$email=strtolower(trim((string)($payload['email']??'')));
if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))api_response(['error'=>'Email is invalid.','field'=>'email'],422);
$pdo=db();$pdo->beginTransaction();
try{
    $contactId=0;
    if($externalId!==''){$s=$pdo->prepare('SELECT contact_id FROM api_contact_sources WHERE api_user_id=? AND external_id=?');$s->execute([$apiUser['id'],$externalId]);$contactId=(int)$s->fetchColumn();}
    if(!$contactId&&$email!==''){$s=$pdo->prepare('SELECT id FROM contacts WHERE LOWER(email)=? ORDER BY id LIMIT 1');$s->execute([$email]);$contactId=(int)$s->fetchColumn();}
    $created=!$contactId;
    if(!$created&&$apiUser['access_mode']==='create_only')throw new InvalidArgumentException('This API token can create contacts but cannot update existing records.');
    $fields=['first_name','last_name','company','job_title','email','phone','address','website'];
    if($created){$first=trim((string)($payload['first_name']??''));$last=trim((string)($payload['last_name']??''));if($first===''||$last==='')throw new InvalidArgumentException('first_name and last_name are required when creating a contact.');$pdo->prepare('INSERT INTO contacts(first_name,last_name,company,job_title,email,phone,address,website) VALUES(?,?,?,?,?,?,?,?)')->execute([$first,$last,trim((string)($payload['company']??''))?:null,trim((string)($payload['job_title']??''))?:null,$email?:null,trim((string)($payload['phone']??''))?:null,trim((string)($payload['address']??''))?:null,trim((string)($payload['website']??''))?:null]);$contactId=(int)$pdo->lastInsertId();}
    else{$sets=[];$values=[];foreach($fields as $field)if(array_key_exists($field,$payload)){$value=trim((string)$payload[$field]);if($field==='email')$value=strtolower($value);$sets[]="$field=?";$values[]=$value?:null;}if($sets){$values[]=$contactId;$pdo->prepare('UPDATE contacts SET '.implode(',',$sets).' WHERE id=?')->execute($values);}}
    if($externalId!=='')$pdo->prepare('INSERT INTO api_contact_sources(api_user_id,external_id,contact_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE contact_id=VALUES(contact_id)')->execute([$apiUser['id'],$externalId,$contactId]);
    $tagIds=[];$tagNames=[];
    $tagPayload=(array)($payload['tags']??[]);if(array_key_exists('tag',$payload))$tagPayload[]=$payload['tag'];$numberedTags=[];foreach($payload as $key=>$value)if(preg_match('/^tag(\d+)$/i',(string)$key,$match))$numberedTags[(int)$match[1]]=$value;ksort($numberedTags);array_push($tagPayload,...array_values($numberedTags));foreach($tagPayload as $tag){$name=is_array($tag)?trim((string)($tag['name']??'')):trim((string)$tag);$color=is_array($tag)?strtolower(trim((string)($tag['color']??'#546e7a'))):'#546e7a';if($name===''||mb_strlen($name)>100||in_array($name,$tagNames,true))continue;if(!preg_match('/^#[0-9a-f]{6}$/',$color))$color='#546e7a';$s=$pdo->prepare('INSERT INTO tags(name,color) VALUES(?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');$s->execute([$name,$color]);$tagIds[]=(int)$pdo->lastInsertId();$tagNames[]=$name;}
    if(!empty($payload['replace_tags'])&&$apiUser['access_mode']==='upsert')$pdo->prepare('DELETE FROM contact_tags WHERE contact_id=?')->execute([$contactId]);
    $s=$pdo->prepare('INSERT IGNORE INTO contact_tags(contact_id,tag_id) VALUES(?,?)');foreach(array_unique($tagIds) as $tagId)$s->execute([$contactId,$tagId]);
    if($created)schedule_tag_notifications($contactId);
    $company=trim((string)($payload['company']??''));if($company!==''){$s=$pdo->prepare('INSERT INTO companies(name) VALUES(?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');$s->execute([$company]);$pdo->prepare('UPDATE contacts SET company_id=? WHERE id=?')->execute([(int)$pdo->lastInsertId(),$contactId]);}
    $customSaved=[];$normalize=fn(string $name)=>trim(strtolower((string)preg_replace('/[^a-z0-9]+/i','_',trim($name))),'_');
    $customPayload=is_array($payload['custom_fields']??null)?$payload['custom_fields']:[];foreach($payload as $key=>$value)if(str_starts_with((string)$key,'custom_'))$customPayload[substr((string)$key,7)]=$value;if($customPayload){$definitions=custom_field_definitions();$byName=[];foreach($definitions as $field)$byName[$normalize((string)$field['name'])]=$field;$save=$pdo->prepare('INSERT INTO contact_custom_values(contact_id,custom_field_id,field_value) VALUES(?,?,?) ON DUPLICATE KEY UPDATE field_value=VALUES(field_value)');foreach($customPayload as $name=>$value){$field=$byName[$normalize((string)$name)]??null;if(!$field)throw new InvalidArgumentException('Unknown custom field: '.$name);$value=trim((string)$value);if($field['field_type']==='list'&&!in_array($value,array_column($field['options'],'option_value'),true))throw new InvalidArgumentException('Invalid value for custom field '.$field['name'].'.');$save->execute([$contactId,(int)$field['id'],$value]);$customSaved[]=$field['name'];}}
    $notePayload=$payload['notes']??($payload['note']??[]);$notes=is_array($notePayload)?$notePayload:[$notePayload];$noteStmt=$pdo->prepare('INSERT INTO notes(contact_id,user_id,body) VALUES(?,NULL,?)');$notesAdded=0;foreach($notes as $note){$body=trim((string)$note);if($body==='')continue;$noteStmt->execute([$contactId,$body]);$notesAdded++;}
    $pdo->prepare('INSERT INTO audit_logs(user_id,action,entity_type,entity_id,details_json,ip_address) VALUES(NULL,?,?,?,?,?)')->execute([$created?'api_create':'api_update','contact',$contactId,json_encode(['api_user_id'=>(int)$apiUser['id'],'tags'=>$tagNames,'custom_fields'=>$customSaved,'notes_added'=>$notesAdded,'external_id'=>$externalId]),$_SERVER['REMOTE_ADDR']??null]);
    $pdo->commit();api_response(['success'=>true,'action'=>$created?'created':'updated','contact_id'=>$contactId,'external_id'=>$externalId?:null,'tags'=>$tagNames,'custom_fields'=>$customSaved,'notes_added'=>$notesAdded],$created?201:200);
}catch(InvalidArgumentException $e){if($pdo->inTransaction())$pdo->rollBack();api_response(['error'=>$e->getMessage()],422);}
catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();app_log('error','API contact processing failed',['exception'=>get_class($e),'message'=>$e->getMessage()]);error_log($e);api_response(['error'=>'Unable to process contact.'],500);}
