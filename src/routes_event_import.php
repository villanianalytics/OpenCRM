<?php
declare(strict_types=1);

function attendee_rows(string $file, string $extension): array {
    if (in_array($extension, ['csv','tsv'], true)) {
        $handle=fopen($file,'rb'); if(!$handle) throw new RuntimeException('Could not read the import file.');
        $delimiter=$extension==='tsv'?"\t":','; $rows=[];
        while(($row=fgetcsv($handle,0,$delimiter))!==false)$rows[]=$row; fclose($handle); return $rows;
    }
    $zip=new ZipArchive(); if($zip->open($file)!==true)throw new RuntimeException('Could not open the Excel workbook.');
    $shared=[]; $sharedXml=$zip->getFromName('xl/sharedStrings.xml');
    if($sharedXml!==false){$xml=simplexml_load_string($sharedXml);foreach($xml->si??[] as $si)$shared[]=trim(implode('', $si->xpath('.//t')?:[]));}
    $sheet=$zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close();
    if($sheet===false)throw new RuntimeException('The workbook has no readable first worksheet.');
    $xml=simplexml_load_string($sheet);$rows=[];
    foreach($xml->sheetData->row as $row){$values=[];foreach($row->c as $cell){$ref=(string)$cell['r'];preg_match('/^[A-Z]+/',$ref,$m);$letters=$m[0]??'A';$column=0;foreach(str_split($letters) as $letter)$column=$column*26+(ord($letter)-64);$column--;$type=(string)$cell['t'];$value=$type==='inlineStr'?trim(implode('', $cell->is->xpath('.//t')?:[])):(string)$cell->v;if($type==='s')$value=$shared[(int)$value]??'';$values[$column]=$value;}if($values){$max=max(array_keys($values));$rows[]=array_replace(array_fill(0,$max+1,''),$values);}}
    return $rows;
}
function normalize_attendee_headers(array $headers): array {
    $aliases=['firstname'=>'first_name','first'=>'first_name','lastname'=>'last_name','last'=>'last_name','emailaddress'=>'email','email'=>'email','company'=>'company','organization'=>'company','phone'=>'phone','phonenumber'=>'phone'];$map=[];
    foreach($headers as $i=>$header){$key=preg_replace('/[^a-z]/','',strtolower(trim((string)$header)));if(isset($aliases[$key]))$map[$aliases[$key]]=$i;}return $map;
}

if(!preg_match('#^/events/(\d+)/import$#',$path,$match))return;
require_permission('events.edit'); if($method!=='POST'){http_response_code(405);exit;}
verify_csrf();$eventId=(int)$match[1];$eventStmt=db()->prepare('SELECT * FROM events WHERE id=?');$eventStmt->execute([$eventId]);$ownedEvent=$eventStmt->fetch();if(!$ownedEvent){http_response_code(404);exit('Event not found');}require_record_edit($ownedEvent);
if(!isset($_FILES['attendees'])||$_FILES['attendees']['error']!==UPLOAD_ERR_OK){flash('error','Choose an attendee file to import.');redirect('/events/'.$eventId);}
$upload=$_FILES['attendees'];$ext=strtolower(pathinfo($upload['name'],PATHINFO_EXTENSION));
if($upload['size']>5*1024*1024||!in_array($ext,['xlsx','csv','tsv'],true)){flash('error','Use an XLSX, CSV, or TSV file no larger than 5 MB.');redirect('/events/'.$eventId);}
try{$rows=attendee_rows($upload['tmp_name'],$ext);$headers=normalize_attendee_headers(array_shift($rows)??[]);if(!isset($headers['first_name'],$headers['last_name']))throw new RuntimeException('The file needs First Name and Last Name columns.');$result=['created'=>0,'matched'=>0,'skipped'=>0,'failed'=>0];
foreach($rows as $row){$get=fn(string $key)=>trim((string)($row[$headers[$key]??-1]??''));$first=$get('first_name');$last=$get('last_name');$email=strtolower($get('email'));if($first===''||$last===''){$result['skipped']++;continue;}try{$contactId=0;if($email!==''){$s=db()->prepare('SELECT id FROM contacts WHERE LOWER(email)=? LIMIT 1');$s->execute([$email]);$contactId=(int)$s->fetchColumn();}if($contactId){$result['matched']++;}else{db()->prepare('INSERT INTO contacts(first_name,last_name,email,company,phone,created_by) VALUES(?,?,?,?,?,?)')->execute([$first,$last,$email?:null,$get('company')?:null,$get('phone')?:null,user()['id']]);$contactId=(int)db()->lastInsertId();apply_user_default_tags($contactId,user()['id']);$result['created']++;}add_event_attendee($eventId,$contactId);}catch(Throwable){$result['failed']++;}}
audit('import_attendees','event',$eventId,$result);flash('success',"Import complete: {$result['created']} contacts created, {$result['matched']} matched, {$result['skipped']} skipped, {$result['failed']} failed.");}
catch(Throwable $e){flash('error','Import failed: '.$e->getMessage());}redirect('/events/'.$eventId);
