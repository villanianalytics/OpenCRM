<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$path='';$method='CLI';require dirname(__DIR__).'/src/routes_legal.php';
$pdo=db();$pdo->beginTransaction();
try{
    if(!(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='operational_alert_states'")->fetchColumn())throw new RuntimeException('Operational alert state table is missing.');
    if(app_setting('operational_alerts_enabled')==='1'&&!filter_var(app_setting('operational_alert_email'),FILTER_VALIDATE_EMAIL))throw new RuntimeException('Operational alert recipient is not configured.');
    $save=$pdo->prepare('INSERT INTO app_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$save->execute(['operational_alerts_enabled','0']);app_settings(true);
    operational_notify('readiness_smoke','error','Synthetic incident',['test'=>true]);$q=$pdo->prepare('SELECT current_status,consecutive_failures FROM operational_alert_states WHERE alert_key=?');$q->execute(['readiness_smoke']);$state=$q->fetch();if($state['current_status']!=='error'||(int)$state['consecutive_failures']!==1)throw new RuntimeException('Operational incident state failed.');
    operational_notify('readiness_smoke','healthy','Synthetic recovery');$q->execute(['readiness_smoke']);$state=$q->fetch();if($state['current_status']!=='healthy'||(int)$state['consecutive_failures']!==0)throw new RuntimeException('Operational recovery state failed.');
    if(!str_contains(legal_default_privacy(),'collects contact information')||!str_contains(legal_footer_html(),'/legal/privacy'))throw new RuntimeException('Legal policy rendering failed.');
    $pdo->rollBack();app_settings(true);echo "Readiness smoke tests passed.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();app_settings(true);fwrite(STDERR,$e->getMessage()."\n");exit(1);}

