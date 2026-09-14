<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$path='/__security_smoke__';$method='GET';
require_once dirname(__DIR__).'/src/calendar_integrations.php';

$dirty='<section onclick="alert(1)"><script>alert(2)</script><a href="javascript:alert(3)">Unsafe</a><a href="https://example.com">Safe</a><img src="/site-assets/example.png" onerror="alert(4)"></section>';
$clean=sanitize_site_html($dirty);
foreach(['<script','onclick=','onerror=','javascript:'] as $needle)if(stripos($clean,$needle)!==false)throw new RuntimeException('Site HTML sanitizer retained '.$needle);
if(!str_contains($clean,'https://example.com')||!str_contains($clean,'/site-assets/example.png'))throw new RuntimeException('Site HTML sanitizer removed safe URLs.');

foreach(['http://example.com/calendar','https://127.0.0.1/calendar','https://169.254.169.254/latest/meta-data/'] as $unsafe){
    $rejected=false;try{calendar_safe_endpoint($unsafe);}catch(InvalidArgumentException $e){$rejected=true;}
    if(!$rejected)throw new RuntimeException('Unsafe calendar endpoint was accepted: '.$unsafe);
}

$schema=file_get_contents(dirname(__DIR__).'/database/schema.sql');
if(!str_contains($schema,'UNIQUE(calendar_id,reserved_start)'))throw new RuntimeException('Booking collision constraint is absent.');
$index=file_get_contents(dirname(__DIR__).'/public/index.php');
if(!str_contains($index,"if(preg_match('#^/contacts/(\\d+)$#',\$path,\$m)){require_permission('contacts.view');"))throw new RuntimeException('Direct contact route permission guard is absent.');
if(!str_contains($index,'id="contact-filters"')||!str_contains($index,'Apply filters'))throw new RuntimeException('Server-rendered contact filter panel is absent.');
if(!str_contains($index,'WHERE LOWER(u.username)=? AND u.active=1')||!str_contains($index,'$loginKey=mb_strtolower($loginName)'))throw new RuntimeException('Case-insensitive login lookup is absent.');
if(!str_contains($index,"require dirname(__DIR__) . '/src/routes_entra_sso.php'"))throw new RuntimeException('Microsoft SSO routes are not loaded.');
if(!str_contains($index,'/assets/mobile-ui.js')||!is_file(dirname(__DIR__).'/public/assets/mobile-ui.js'))throw new RuntimeException('Responsive table and navigation behavior is absent.');
$sso=file_get_contents(dirname(__DIR__).'/src/routes_entra_sso.php');
foreach(['code_challenge_method','S256','entra_oauth','hash_equals','graph.microsoft.com/v1.0/me','entra_object_id'] as $needle)if(!str_contains($sso,$needle))throw new RuntimeException('Microsoft SSO security control is absent: '.$needle);
foreach(["/admin/sso/users","unlink_microsoft_sso","That Entra Object ID is already linked"] as $needle)if(!str_contains($sso,$needle))throw new RuntimeException('Microsoft SSO user-link administration is absent: '.$needle);

$columns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bookings'")->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('reserved_start',$columns,true))throw new RuntimeException('Booking collision migration is absent.');
$submissionColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='crm_form_submissions'")->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('matched_existing',$submissionColumns,true))throw new RuntimeException('Public form protection migration is absent.');
$userColumns=db()->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users'")->fetchAll(PDO::FETCH_COLUMN);
if(!in_array('entra_object_id',$userColumns,true))throw new RuntimeException('Microsoft identity link column is absent.');
$probe='security-smoke-'.bin2hex(random_bytes(8));if(!rate_limit_hit('security_smoke',$probe,1,60)||rate_limit_hit('security_smoke',$probe,1,60))throw new RuntimeException('Rate limiter did not enforce its limit.');rate_limit_clear('security_smoke',$probe);
if(class_exists(Composer\InstalledVersions::class)&&version_compare(ltrim((string)Composer\InstalledVersions::getPrettyVersion('dompdf/dompdf'),'v'),'3.1.6','<'))throw new RuntimeException('Dompdf security update is absent.');

echo "Security smoke tests passed.\n";
