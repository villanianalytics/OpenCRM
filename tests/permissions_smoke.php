<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';

$required=['contacts','companies','opportunities','events','alerts','partner_sales','reports','lead_magnets','forms','promotional_links','sites','bookings','communications','workflows','resources','sales_documents'];
$staff=db()->prepare("SELECT permissions_json FROM roles WHERE name='Staff'");$staff->execute();$permissions=json_decode((string)$staff->fetchColumn(),true)?:[];
foreach($required as $module)foreach(['view','edit'] as $action)if(!in_array("$module.$action",$permissions,true))throw new RuntimeException("Staff role is missing $module.$action");

$_SESSION['user']=['id'=>999999,'username'=>'permission-smoke','is_admin'=>false,'permissions'=>['companies.view','opportunities.edit','promotional_links.edit']];
$path='/companies';if(!can('contacts.view')||can('companies.edit'))throw new RuntimeException('Company permission isolation failed');
$path='/opportunities';if(!can('contacts.view')||!can('contacts.edit')||!can('opportunities.view'))throw new RuntimeException('Opportunity permission inheritance failed');
$path='/promotions/links';if(!can('events.edit')||!can('promotional_links.view'))throw new RuntimeException('Promotional-link permission isolation failed');
$path='/contacts';if(can('contacts.view'))throw new RuntimeException('Contact permission leaked from another module');

echo "Permission smoke tests passed.\n";

