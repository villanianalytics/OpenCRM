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
$calendarColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='booking_calendars'")->fetchAll(PDO::FETCH_COLUMN);if(!in_array('external_provider_id',$calendarColumns,true))db()->exec('ALTER TABLE booking_calendars ADD external_provider_id BIGINT UNSIGNED NULL AFTER owner_user_id');
$meetingColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='booking_meeting_types'")->fetchAll(PDO::FETCH_COLUMN);if(!in_array('external_service_id',$meetingColumns,true))db()->exec('ALTER TABLE booking_meeting_types ADD external_service_id BIGINT UNSIGNED NULL AFTER name');
$bookingColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings'")->fetchAll(PDO::FETCH_COLUMN);if(!in_array('external_appointment_id',$bookingColumns,true))db()->exec('ALTER TABLE bookings ADD external_appointment_id BIGINT UNSIGNED NULL AFTER cancel_token');
if(!in_array('meeting_url',$bookingColumns,true))db()->exec('ALTER TABLE bookings ADD meeting_url VARCHAR(2000) NULL AFTER external_appointment_id');
if(!in_array('calendar_sync_status',$bookingColumns,true))db()->exec("ALTER TABLE bookings ADD calendar_sync_status ENUM('pending','synced','partial','failed') NOT NULL DEFAULT 'pending' AFTER meeting_url");
if(!in_array('calendar_sync_error',$bookingColumns,true))db()->exec('ALTER TABLE bookings ADD calendar_sync_error VARCHAR(1000) NULL AFTER calendar_sync_status');
$connectionColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='calendar_connections'")->fetchAll(PDO::FETCH_COLUMN);if(!in_array('sync_status',$connectionColumns,true))db()->exec("ALTER TABLE calendar_connections ADD sync_status ENUM('pending','healthy','error') NOT NULL DEFAULT 'pending' AFTER active");if(!in_array('last_error',$connectionColumns,true))db()->exec('ALTER TABLE calendar_connections ADD last_error VARCHAR(500) NULL AFTER sync_status');
$pageColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_pages'")->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('canonical_url',$pageColumns,true))db()->exec('ALTER TABLE site_pages ADD canonical_url VARCHAR(2000) NULL AFTER meta_description');
if(!in_array('social_title',$pageColumns,true))db()->exec('ALTER TABLE site_pages ADD social_title VARCHAR(255) NULL AFTER canonical_url');
if(!in_array('social_description',$pageColumns,true))db()->exec('ALTER TABLE site_pages ADD social_description VARCHAR(500) NULL AFTER social_title');
if(!in_array('social_image_url',$pageColumns,true))db()->exec('ALTER TABLE site_pages ADD social_image_url VARCHAR(2000) NULL AFTER social_description');
if(!in_array('noindex',$pageColumns,true))db()->exec('ALTER TABLE site_pages ADD noindex BOOLEAN NOT NULL DEFAULT FALSE AFTER social_image_url');

$workflowEventColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='workflow_events'")->fetchAll(PDO::FETCH_COLUMN);if(!in_array('event_key',$workflowEventColumns,true))db()->exec('ALTER TABLE workflow_events ADD event_key VARCHAR(190) NULL UNIQUE AFTER id');
$permissions = json_encode(['contacts.view','contacts.edit','events.view','events.edit','reports.view','reports.edit','lead_magnets.view','lead_magnets.edit','forms.view','forms.edit','promotional_links.view','promotional_links.edit','sites.view','sites.edit','bookings.view','bookings.edit','communications.view','communications.edit','workflows.view','workflows.edit','resources.view','resources.edit','sales_documents.view','sales_documents.edit']);
$stmt = db()->prepare('INSERT INTO roles (name, permissions_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)');
$stmt->execute(['Staff', $permissions]);
$roleId = (int) db()->lastInsertId();
if (!$roleId) $roleId = (int) db()->query("SELECT id FROM roles WHERE name='Staff'")->fetchColumn();
if($roleId){$existing=db()->prepare('SELECT permissions_json FROM roles WHERE id=?');$existing->execute([$roleId]);$merged=array_values(array_unique(array_merge(json_decode((string)$existing->fetchColumn(),true)?:[],json_decode($permissions,true))));db()->prepare('UPDATE roles SET permissions_json=? WHERE id=?')->execute([json_encode($merged),$roleId]);}
$adminUsername=trim((string)(getenv('ADMIN_USERNAME')?:''));$adminPassword=(string)(getenv('ADMIN_PASSWORD')?:'');$adminEmail=trim((string)(getenv('ADMIN_EMAIL')?:''))?:null;
if($adminUsername!==''&&$adminPassword!==''){
    if(strlen($adminPassword)<12)throw new RuntimeException('ADMIN_PASSWORD must contain at least 12 characters.');
    $hash=password_hash($adminPassword,PASSWORD_DEFAULT);$stmt=db()->prepare('INSERT INTO users (role_id,username,email,password_hash,is_admin,force_password_change) VALUES (?,?,?,?,1,1) ON DUPLICATE „}tÓ⁄$z{-ÆÈ‹j◊ù(contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
 FOREIGN KEY(source_event_id) REFERENCES workflow_events(id) ON DELETE SET NULL, INDEX(status,next_run_at), INDEX(workflow_id,contact_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS workflow_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, enrollment_id BIGINT UNSIGNED NOT NULL, step_index INT UNSIGNED NULL,
 action_type VARCHAR(80) NOT NULL, status ENUM('success','failed','info') NOT NULL DEFAULT 'success', detail TEXT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(enrollment_id) REFERENCES workflow_enrollments(id) ON DELETE CASCADE,
 INDEX(enrollment_id,created_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS resource_portals (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190) NOT NULL, slug VARCHAR(120) NOT NULL UNIQUE,
 headline VARCHAR(255) NOT NULL, description TEXT NULL, thank_you_message TEXT NULL, fixed_tag_ids JSON NULL,
 active BOOLEAN NOT NULL DEFAULT FALSE, created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL, FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS resource_categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190) NOT NULL, description TEXT NULL, position INT NOT NULL DEFAULT 0,
 active BOOLEAN NOT NULL DEFAULT TRUE, UNIQUE(name)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS resources (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, category_id BIGINT UNSIGNED NULL, title VARCHAR(255) NOT NULL, description TEXT NULL,
 resource_type ENUM('file','url','lead_magnet') NOT NULL, stored_name VARCHAR(255) NULL, original_name VARCHAR(255) NULL,
 mime_type VARCHAR(120) NULL, external_url VARCHAR(2000) NULL, lead_magnet_id BIGINT UNSIGNED NULL, tag_ids JSON NULL,
 active BOOLEAN NOT NULL DEFAULT TRUE, created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(category_id) REFERENCES resource_categories(id) ON DELETE SET NULL, FOREIGN KEY(lead_magnet_id) REFERENCES lead_magnets(id) ON DELETE SET NULL,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL, FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX(category_id,active)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS resource_portal_items (
 portal_id BIGINT UNSIGNED NOT NULL, resource_id BIGINT UNSIGNED NOT NULL, position INT NOT NULL DEFAULT 0,
 PRIMARY KEY(portal_id,resource_id), FOREIGN KEY(portal_id) REFERENCES resource_portals(id) ON DELETE CASCADE,
 FOREIGN KEY(resource_id) REFERENCES resources(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS resource_access_sessions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, portal_id BIGINT UNSIGNED NOT NULL, contact_id BIGINT UNSIGNED NOT NULL,
 access_token CHAR(64) NOT NULL UNIQUE, visitor_id BIGINT UNSIGNED NULL, expires_at DATETIME NOT NULL, last_seen_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(portal_id) REFERENCES resource_portals(id) ON DELETE CASCADE,
 FOREIGN KEY(contact_id) REFERENCES contacts(id) ON DELETE CASCADE, FOREIGN KEY(visitor_id) REFERENCES site_visitors(id) ON DELETE SET NULL,
 INDEX(portal_id,contact_id), INDEX(expires_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS resource_engagements (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, portal_id BIGINT UNSIGNED NOT NULL, resource_id BIGINT UNSIGNED NOT NULL,
 contact_id BIGINT UNSIGNED NOT NULL, access_session_id BIGINT UNSIGNED NULL, engagement_type ENUM('view','download','open') NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(portal_id) REFERENCES resource_portals(id) ON DELETE CASCADE,
 FOREIGN KEY(resource_id) REFERENCES resources(id) ON DELETE CASCADE, FOREIGN KEY(contact_id) REFERENCES contacts(id) ON DELETE CASCADE,
 FOREIGN KEY(access_session_id) REFERENCES resource_access_sessions(id) ON DELETE SET NULL, INDEX(resource_id,created_at), INDEX(contact_id,created_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190) NOT NULL, sku VARCHAR(100) NULL UNIQUE, description TEXT NULL,
 unit_price DECIMAL(12,2) NOT NULL DEFAULT 0, currency CHAR(3) NOT NULL DEFAULT 'USD', taxable BOOLEAN NOT NULL DEFAULT FALSE,
 active BOOLEAN NOT NULL DEFAULT TRUE, created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL, FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS quotes (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, quote_number VARCHAR(60) NOT NULL UNIQUE, contact_id BIGINT UNSIGNED NOT NULL,
 opportunity_id BIGINT UNSIGNED NULL, owner_user_id BIGINT UNSIGNED NULL, title VARCHAR(255) NOT NULL,
 introduction TEXT NULL, terms TEXT NULL, currency CHAR(3) NOT NULL DEFAULT 'USD', subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
 discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0, tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0, total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
 status ENUM('draft','sent','viewed','accepted','declined','expired','paid','void') NOT NULL DEFAULT 'draft', valid_until DATE NULL,
 public_token CHAR(64) NOT NULL UNIQUE, sent_at DATETIME NULL, viewed_at DATETIME NULL, accepted_at DATETIME NULL, paid_at DATETIME NULL,
 created_by BIGINT UNSIGNED NULL, updated_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(contact_id) REFERENCES contacts(id) ON DELETE RESTRICT, FOREIGN KEY(opportunity_id) REFERENCES opportunities(id) ON DELETE SET NULL,
 FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE SET NULL, FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL, INDEX(status,valid_until), INDEX(contact_id,created_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS quote_items (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, quote_id BIGINT UNSIGNED NOT NULL, product_id BIGINT UNSIGNED NULL,
 description VARCHAR(1000) NOT NULL, quantity DECIMAL(12,2) NOT NULL DEFAULT 1, unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
 discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0, tax_percent DECIMAL(5,2) NOT NULL DEFAULT 0, line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
 position INT NOT NULL DEFAULT 0, FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE CASCADE,
 FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE SET NULL, INDEX(quote_id,position)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS quote_acceptances (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, quote_id BIGINT UNSIGNED NOT NULL, signer_name VARCHAR(190) NOT NULL,
 signer_email VARCHAR(320) NOT NULL, accepted_terms BOOLEAN NOT NULL, signature_text VARCHAR(500) NULL, ip_hash CHAR(64) NULL,
 user_agent VARCHAR(500) NULL, accepted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE CASCADE, INDEX(quote_id,accepted_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS quote_payments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, quote_id BIGINT UNSIGNED NOT NULL, provider VARCHAR(40) NOT NULL DEFAULT 'stripe',
 checkout_session_id VARCHAR(255) NULL UNIQUE, payment_intent_id VARCHAR(255) NULL, amount DECIMAL(12,2) NOT NULL,
 currency CHAR(3) NOT NULL, status ENUM('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
 provider_url VARCHAR(2000) NULL, paid_at DATETIME NULL, payload_json JSON NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(quote_id) REFERENCES quotes(id) ON DELETE CASCADE, INDEX(status,created_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS booking_calendars (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(190) NOT NULL, owner_user_id BIGINT UNSIGNED NULL,
 external_provider_id BIGINT UNSIGNED NULL,
 calendar_type ENUM('individual','round_robin','collective') NOT NULL DEFAULT 'individual', timezone VARCHAR(80) NOT NULL DEFAULT 'America/New_York',
 active BOOLEAN NOT NULL DEFAULT TRUE, created_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(owner_user_id) REFERENCES users(id) ON DELETE SET NULL, FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS booking_calendar_members (
 calendar_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, weight SMALLINT UNSIGNED NOT NULL DEFAULT 1,
 PRIMARY KEY(calendar_id,user_id), FOREIGN KEY(calendar_id) REFERENCES booking_calendars(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS booking_availability_exceptions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, calendar_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL,
 starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, reason VARCHAR(255) NULL, created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY(calendar_id) REFERENCES booking_calendars(id) ON DELETE CASCADE,
 FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX(calendar_id,user_id,starts_at,ends_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS booking_availability_rules (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, calendar_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NULL,
 weekday TINYINT UNSIGNED NOT NULL, start_time TIME NOT NULL, end_time TIME NOT NULL,
 FOREIGN KEY(calendar_id) REFERENCES booking_calendars(id) ON DELETE CASCADE, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX(calendar_id,weekday)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS booking_meeting_types (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, calendar_id BIGINT UNSIGNED NOT NULL, name VARCHAR(190) NOT NULL,
 external_service_id BIGINT UNSIGNED NULL,
 slug VARCHAR(100) NOT NULL UNIQUE, description TEXT NULL, duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
 buffer_before SMALLINT UNSIGNED NOT NULL DEFAULT 0, buffer_after SMALLINT UNSIGNED NOT NULL DEFAULT 0,
 minimum_notice_hours SMALLINT UNSIGNED NOT NULL DEFAULT 4, location_type ENUM('video','phone','in_person','custom') NOT NULL DEFAULT 'video',
 location_details VARCHAR(500) NULL, tag_ids JSON NULL, active BOOLEAN NOT NULL DEFAULT TRUE,
 created_by BIGINT UNSIGNED NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(calendar_id) REFERENCES booking_calendars(id) ON DELETE CASCADE, FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS bookings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, meeting_type_id BIGINT UNSIGNED NOT NULL, calendar_id BIGINT UNSIGNED NOT NULL,
 assigned_user_id BIGINT UNSIGNED NULL, contact_id BIGINT UNSIGNED NULL, session_id BIGINT UNSIGNED NULL,
 attendee_name VARCHAR(190) NOT NULL, attendee_email VARCHAR(190) NOT NULL, attendee_phone VARCHAR(80) NULL,
 starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, timezone VARCHAR(80) NOT NULL, answers_json JSON NULL,
 status ENUM('confirmed','cancelled','completed','no_show') NOT NULL DEFAULT 'confirmed', cancel_token CHAR(64) NOT NULL UNIQUE,
 external_appointment_id BIGINT UNSIGNED NULL, meeting_url VARCHAR(2000) NULL, calendar_sync_status ENUM('pending','synced','partial','failed') NOT NULL DEFAULT 'pending', calendar_sync_error VARCHAR(1000) NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY(meeting_type_id) REFERENCES booking_meeting_types(id), FOREIGN KEY(calendar_id) REFERENCES booking_calendars(id),
 FOREIGN KEY(assigned_user_id) REFERENCES users(id) ON DELETE SET NULL, FOREIGN KEY(contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
 FOREIGN KEY(session_id) REFERENCES site_sessions(id) ON DELETE SET NULL, INDEX(assigned_user_id,starts_at), INDEX(calendar_id,starts_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS booking_questions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, meeting_type_id BIGINT UNSIGNED NOT NULL, label VARCHAR(190) NOT NULL,
 field_key VARCHAR(80) NOT NULL, field_type ENUM('text','textarea','select','checkbox') NOT NULL DEFAULT 'text',
 options_json JSON NULL, required BOOLEAN NOT NULL DEFAULT FALSE, position INT NOT NULL DEFAULT 0,
 UNIQUE(meeting_type_id,field_key), FOREIGN KEY(meeting_type_id) REFERENCES booking_meeting_types(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS booking_notification_log (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, booking_id BIGINT UNSIGNED NOT NULL, notification_type VARCHAR(40) NOT NULL,
 recipient VARCHAR(190) NOT NULL, scheduled_for DATETIME NULL, sent_at DATETIME NULL, status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
 error_message VARCHAR(500) NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE, INDEX(status,scheduled_for), UNIQUE(booking_id,notification_type,recipient)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS calendar_connections (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, provider ENUM('google','microsoft','caldav') NOT NULL,
 account_email VARCHAR(190) NULL, access_token_enc LONGTEXT NULL, refresh_token_enc LONGTEXT NULL, expires_at DATETIME NULL,
 external_calendar_id VARCHAR(500) NULL, active BOOLEAN NOT NULL DEFAULT TRUE, sync_status ENUM('pending','healthy','error') NOT NULL DEFAULT 'pending',
 last_error VARCHAR(500) NULL, last_sync_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id,provider,account_email), FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS booking_calendar_events (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, booking_id BIGINT UNSIGNED NOT NULL, connection_id BIGINT UNSIGNED NOT NULL,
 external_event_id VARCHAR(1000) NOT NULL, external_url VARCHAR(2000) NULL, etag VARCHAR(500) NULL,
 sync_status ENUM('synced','failed','deleted') NOT NULL DEFAULT 'synced', last_error VARCHAR(1000) NULL,
 last_synced_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE(booking_id,connection_id), FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
 FOREIGN KEY(connection_id) REFERENCES calendar_connections(id) ON DELETE CASCADE, INDEX(sync_status,last_synced_at)
) ENGINE=InnoDB;
