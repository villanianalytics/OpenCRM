<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

$sql = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
foreach (array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql))) as $statement) db()->exec($statement);

$eventColumns = db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events'")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('presenter_contact_id', $eventColumns, true)) db()->exec('ALTER TABLE events ADD presenter_contact_id BIGINT UNSIGNED NULL AFTER slides_original_name');
if (!in_array('presenter_name', $eventColumns, true)) db()->exec('ALTER TABLE events ADD presenter_name VARCHAR(190) NULL AFTER presenter_contact_id');
if (!in_array('booking_url', $eventColumns, true)) db()->exec('ALTER TABLE events ADD booking_url VARCHAR(500) NULL AFTER abstract');
if (!in_array('venue_address', $eventColumns, true)) db()->exec('ALTER TABLE events ADD venue_address TEXT NULL AFTER booking_url');
if (!in_array('presentation_title', $eventColumns, true)) db()->exec('ALTER TABLE events ADD presentation_title VARCHAR(190) NULL AFTER starts_at');
if (!in_array('conference_url', $eventColumns, true)) db()->exec('ALTER TABLE events ADD conference_url VARCHAR(500) NULL AFTER booking_url');
$opportunityColumns = db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='opportunities'")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('source_type', $opportunityColumns, true)) db()->exec("ALTER TABLE opportunities ADD source_type ENUM('none','event','other') NOT NULL DEFAULT 'none' AFTER status");
if (!in_array('source_event_id', $opportunityColumns, true)) db()->exec('ALTER TABLE opportunities ADD source_event_id BIGINT UNSIGNED NULL AFTER source_type');
if (!in_array('source_note', $opportunityColumns, true)) db()->exec('ALTER TABLE opportunities ADD source_note TEXT NULL AFTER source_event_id');
db()->exec('INSERT IGNORE INTO opportunity_contacts(opportunity_id,contact_id) SELECT id,contact_id FROM opportunities');
db()->exec('ALTER TABLE events MODIFY event_type VARCHAR(60) NOT NULL');
db()->exec("ALTER TABLE opportunities MODIFY score VARCHAR(60) NOT NULL DEFAULT 'medium', MODIFY status VARCHAR(60) NOT NULL DEFAULT 'open', MODIFY source_type VARCHAR(60) NOT NULL DEFAULT 'none'");
$lists=[
 'opportunity_score'=>['Opportunity scores','Priority or qualification score',['high'=>'High','medium'=>'Medium','low'=>'Low','keep_in_touch'=>'Keep in touch','not_buyer'=>'Not a buyer']],
 'opportunity_status'=>['Opportunity statuses','Pipeline status',['open'=>'Open','won'=>'Won','lost'=>'Lost']],
 'opportunity_source'=>['Opportunity sources','Where an opportunity originated',['none'=>'None','event'=>'Event','other'=>'Other']],
 'event_type'=>['Event types','Types of events',['webinar'=>'Webinar','conference'=>'Conference','presentation'=>'Presentation','other'=>'Other']],
 'lead_magnet_type'=>['Lead magnet types','Artifact formats available in the Lead Magnet Generator',['flyer'=>'1 Page Flyer','ebook'=>'EBook (DOCX + PDF)','checklist'=>'Checklist','other'=>'Other']],
];
foreach($lists as $key=>[$name,$description,$values]){
    $stmt=db()->prepare('INSERT INTO option_lists(list_key,name,description) VALUES(?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description)');$stmt->execute([$key,$name,$description]);
    $stmt=db()->prepare('SELECT id FROM option_lists WHERE list_key=?');$stmt->execute([$key]);$listId=(int)$stmt->fetchColumn();$position=0;
    $insert=db()->prepare('INSERT INTO option_values(list_id,code,label,position) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE code=VALUES(code)');foreach($values as $code=>$label)$insert->execute([$listId,$code,$label,$position+=10]);
}
$contactColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contacts'")->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('company_id',$contactColumns,true))db()->exec('ALTER TABLE contacts ADD company_id BIGINT UNSIGNED NULL AFTER company');
if(!in_array('owner_id',$contactColumns,true))db()->exec('ALTER TABLE contacts ADD owner_id BIGINT UNSIGNED NULL AFTER created_by');
if(!in_array('active',$contactColumns,true))db()->exec('ALTER TABLE contacts ADD active BOOLEAN NOT NULL DEFAULT TRUE AFTER website');
$opportunityColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='opportunities'")->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('owner_id',$opportunityColumns,true))db()->exec('ALTER TABLE opportunities ADD owner_id BIGINT UNSIGNED NULL AFTER contact_id');
if(!in_array('active',$opportunityColumns,true))db()->exec('ALTER TABLE opportunities ADD active BOOLEAN NOT NULL DEFAULT TRUE AFTER probability');
if(!in_array('closed_at',$opportunityColumns,true))db()->exec('ALTER TABLE opportunities ADD closed_at DATETIME NULL AFTER active');db()->exec("UPDATE opportunities SET closed_at=created_at WHERE status IN ('won','lost') AND closed_at IS NULL");
$userColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users'")->fetchAll(PDO::FETCH_COLUMN);if(!in_array('display_name',$userColumns,true))db()->exec('ALTER TABLE users ADD display_name VARCHAR(120) NULL AFTER username');db()->exec("UPDATE users SET display_name=username WHERE display_name IS NULL OR display_name=''");
if(!in_array('partner_sales_access',$userColumns,true))db()->exec('ALTER TABLE users ADD partner_sales_access BOOLEAN NOT NULL DEFAULT FALSE AFTER active');
$tagColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tags'")->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('tag_group_id',$tagColumns,true))db()->exec('ALTER TABLE tags ADD tag_group_id BIGINT UNSIGNED NULL AFTER id');
db()->exec("INSERT IGNORE INTO companies(name) SELECT DISTINCT company FROM contacts WHERE company IS NOT NULL AND company<>''");
db()->exec("UPDATE contacts c JOIN companies co ON LOWER(co.name)=LOWER(c.company) SET c.company_id=co.id WHERE c.company_id IS NULL");
$apiColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='api_users'")->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('access_mode',$apiColumns,true))db()->exec("ALTER TABLE api_users ADD access_mode VARCHAR(30) NOT NULL DEFAULT 'upsert' AFTER token_prefix");
$customColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='custom_fields'")->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('custom_field_group_id',$customColumns,true))db()->exec('ALTER TABLE custom_fields ADD custom_field_group_id BIGINT UNSIGNED NULL AFTER id');
if(!in_array('required',$customColumns,true))db()->exec('ALTER TABLE custom_fields ADD required BOOLEAN NOT NULL DEFAULT FALSE AFTER position');
if(!in_array('validation_pattern',$customColumns,true))db()->exec('ALTER TABLE custom_fields ADD validation_pattern VARCHAR(255) NULL AFTER required');
db()->exec("ALTER TABLE custom_fields MODIFY field_type VARCHAR(30) NOT NULL DEFAULT 'text'");
$companyColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='companies'")->fetchAll(PDO::FETCH_COLUMN);if(!in_array('created_by',$companyColumns,true))db()->exec('ALTER TABLE companies ADD created_by BIGINT UNSIGNED NULL AFTER owner_id');
if(!in_array('created_by',$opportunityColumns,true))db()->exec('ALTER TABLE opportunities ADD created_by BIGINT UNSIGNED NULL AFTER owner_id');if(!in_array('expected_close_date',$opportunityColumns,true))db()->exec('ALTER TABLE opportunities ADD expected_close_date DATE NULL AFTER source_note');if(!in_array('probability',$opportunityColumns,true))db()->exec('ALTER TABLE opportunities ADD probability TINYINT UNSIGNED NULL AFTER expected_close_date');
$eventColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='events'")->fetchAll(PDO::FETCH_COLUMN);if(!in_array('owner_id',$eventColumns,true))db()->exec('ALTER TABLE events ADD owner_id BIGINT UNSIGNED NULL AFTER created_by');
db()->exec('UPDATE contacts SET owner_id=created_by WHERE owner_id IS NULL AND created_by IS NOT NULL');db()->exec('UPDATE companies SET owner_id=created_by WHERE owner_id IS NULL AND created_by IS NOT NULL');db()->exec('UPDATE opportunities SET owner_id=created_by WHERE owner_id IS NULL AND created_by IS NOT NULL');db()->exec('UPDATE events SET owner_id=created_by WHERE owner_id IS NULL AND created_by IS NOT NULL');

$formColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='crm_forms'")->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('submit_label',$formColumns,true))db()->exec("ALTER TABLE crm_forms ADD submit_label VARCHAR(120) NOT NULL DEFAULT 'Submit' AFTER thank_you_message");
$promoColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='promotional_links'")->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('campaign_name',$promoColumns,true))db()->exec('ALTER TABLE promotional_links ADD campaign_name VARCHAR(190) NULL AFTER destination_url');
if(!in_array('channel',$promoColumns,true))db()->exec('ALTER TABLE promotional_links ADD channel VARCHAR(120) NULL AFTER campaign_name');
if(!in_array('variant',$promoColumns,true))db()->exec('ALTER TABLE promotional_links ADD variant VARCHAR(120) NULL AFTER channel');
$roleRows=db()->query('SELECT id,permissions_json FROM roles')->fetchAll();$saveRole=db()->prepare('UPDATE roles SET permissions_json=? WHERE id=?');foreach($roleRows as $roleRow){$rp=json_decode((string)$roleRow['permissions_json'],true)?:[];$rp=array_values(array_unique(array_merge($rp,['promotional_links.view','promotional_links.edit','events.view'])));$saveRole->execute([json_encode($rp),$roleRow['id']]);}

$permissions = json_encode(['contacts.view','contacts.edit','events.view','events.edit','reports.view','reports.edit','lead_magnets.view','lead_magnets.edit','forms.view','forms.edit','promotional_links.view','promotional_links.edit','sites.view','sites.edit','bookings.view','bookings.edit']);
$stmt = db()->prepare('INSERT INTO roles (name, permissions_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)');
$stmt->execute(['Staff', $permissions]);
$roleId = (int) db()->lastInsertId();
if (!$roleId) $roleId = (int) db()->query("SELECT id FROM roles WHERE name='Staff'")->fetchColumn();
if($roleId){$existing=db()->prepare('SELECT permissions_json FROM roles WHERE id=?');$existing->execute([$roleId]);$merged=array_values(array_unique(array_merge(json_decode((string)$existing->fetchColumn(),true)?:[],json_decode($permissions,true))));db()->prepare('UPDATE roles SET permissions_json=? WHERE id=?')->execute([json_encode($merged),$roleId]);}
$adminUsername=trim((string)(getenv('ADMIN_USERNAME')?:''));$adminPassword=(string)(getenv('ADMIN_PASSWORD')?:'');$adminEmail=trim((string)(getenv('ADMIN_EMAIL')?:''))?:null;
if($adminUsername!==''&&$adminPassword!==''){
    if(strlen($adminPassword)<12)throw new RuntimeException('ADMIN_PASSWORD must contain at least 12 characters.');
    $hash=password_hash($adminPassword,PASSWORD_DEFAULT);$stmt=db()->prepare('INSERT INTO users (role_id,username,email,password_hash,is_admin,force_password_change) VALUES (?,?,?,?,1,1) ON DUPLICATE KEY UPDATE username=username');$stmt->execute([$roleId,$adminUsername,$adminEmail,$hash]);
}elseif(!(int)db()->query('SELECT COUNT(*) FROM users WHERE is_admin=1')->fetchColumn()){
    fwrite(STDERR,"No administrator created. Set ADMIN_USERNAME and ADMIN_PASSWORD, then run this migration again.\n");
}
echo "Migration complete.\n";
