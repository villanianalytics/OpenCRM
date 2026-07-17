<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$pdo=db();$token='crm_'.bin2hex(random_bytes(32));$external='smoke-'.bin2hex(random_bytes(6));$tag='API_Smoke_'.bin2hex(random_bytes(4));$tag2=$tag.'_Second';$tag3=$tag.'_Third';$apiId=0;$contactId=0;
try{
    $admin=(int)$pdo->query('SELECT id FROM users WHERE is_admin=1 LIMIT 1')->fetchColumn();
    $pdo->prepare('INSERT INTO api_users(name,token_hash,token_prefix,created_by) VALUES(?,?,?,?)')->execute(['API smoke test',hash('sha256',$token),substr($token,0,12),$admin]);$apiId=(int)$pdo->lastInsertId();
    $payload=json_encode(['external_id'=>$external,'first_name'=>'API','last_name'=>'Smoke','email'=>$external.'@example.invalid','tag'=>$tag,'tag1'=>$tag2,'tag3'=>$tag3]);
    $curl=curl_init(config('url').'/api/v1/contacts');curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>$payload]);
    $body=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);if($body===false)throw new RuntimeException(curl_error($curl));curl_close($curl);$result=json_decode($body,true);
    if($status!==201||!($result['success']??false)||($result['tags']??[])!==[$tag,$tag2,$tag3])throw new RuntimeException("API create failed with HTTP $status.");$contactId=(int)$result['contact_id'];
    echo "API smoke test passed.\n";
}finally{
    if($apiId){$pdo->prepare("DELETE FROM audit_logs WHERE details_json->>'$.api_user_id'=?")->execute([(string)$apiId]);$pdo->prepare('DELETE FROM api_users WHERE id=?')->execute([$apiId]);}
    if($contactId)$pdo->prepare('DELETE FROM contacts WHERE id=?')->execute([$contactId]);
    $cleanup=$pdo->prepare('DELETE FROM tags WHERE name IN (?,?,?)');$cleanup->execute([$tag,$tag2,$tag3]);
}
