<?php
declare(strict_types=1);

if($path!=='/promotions/attribution')return;
require_permission('promotional_links.view');

$model=in_array($_GET['model']??'linear',['first','last','linear'],true)?(string)$_GET['model']:'linear';
$days=max(1,min(730,(int)($_GET['days']??90)));
$since=(new DateTimeImmutable("-$days days"))->format('Y-m-d H:i:s');

$sessionStmt=db()->prepare("SELECT ss.id,ss.started_at,ss.utm_source,ss.utm_medium,ss.utm_campaign,ss.referrer
 FROM site_sessions ss JOIN site_visitors sv ON sv.id=ss.visitor_id
 WHERE sv.contact_id=? AND ss.started_at<=? AND ss.started_at>=DATE_SUB(?,INTERVAL 90 DAY)
 ORDER BY ss.started_at");

$outcomes=db()->prepare("SELECT 'Conversion' outcome_type,sc.id outcome_id,sc.contact_id,sc.converted_at outcome_at,0 revenue,sc.conversion_type detail
 FROM site_conversions sc WHERE sc.contact_id IS NOT NULL AND sc.converted_at>=?
 UNION ALL
 SELECT 'Revenue',q.id,q.contact_id,q.paid_at,q.total_amount,q.quote_number
 FROM quotes q WHERE q.status='paid' AND q.paid_at>=?
 ORDER BY outcome_at");
$outcomes->execute([$since,$since]);
$aggregate=[];$details=[];
foreach($outcomes->fetchAll() as $outcome){
    $sessionStmt->execute([(int)$outcome['contact_id'],$outcome['outcome_at'],$outcome['outcome_at']]);
    $sequence=[];
    foreach($sessionStmt->fetchAll() as $session){
        $source=trim((string)$session['utm_source']);
        if($source===''){$host=parse_url((string)$session['referrer'],PHP_URL_HOST);$source=$host?:'Direct / Unknown';}
        $campaign=trim((string)$session['utm_campaign'])?:'Not set';
        $medium=trim((string)$session['utm_medium'])?:'Not set';
        $key=$source.'|'.$medium.'|'.$campaign;
        $sequence[]=['key'=>$key,'source'=>$source,'medium'=>$medium,'campaign'=>$campaign,'started_at'=>$session['started_at']];
    }
    if(!$sequence)$sequence=[['key'=>'Direct / Unknown|Not set|Not set','source'=>'Direct / Unknown','medium'=>'Not set','campaign'=>'Not set','started_at'=>$outcome['outcome_at']]];
    if($model==='first')$touches=[$sequence[0]];
    elseif($model==='last')$touches=[$sequence[count($sequence)-1]];
    else{$unique=[];foreach($sequence as $touch)$unique[$touch['key']]=$touch;$touches=array_values($unique);}
    $weight=1/count($touches);
    foreach($touches as $touch){
        $key=$touch['source'].'|'.$touch['medium'].'|'.$touch['campaign'];
        $aggregate[$key]??=['source'=>$touch['source'],'medium'=>$touch['medium'],'campaign'=>$touch['campaign'],'touches'=>0.0,'conversions'=>0.0,'revenue'=>0.0];
        $aggregate[$key]['touches']+=$weight;
        if($outcome['outcome_type']==='Conversion')$aggregate[$key]['conversions']+=$weight;
        $aggregate[$key]['revenue']+=(float)$outcome['revenue']*$weight;
        $details[]=$outcome+['source'=>$touch['source'],'medium'=>$touch['medium'],'campaign'=>$touch['campaign'],'credit'=>$weight];
    }
}
usort($aggregate,fn($a,$b)=>($b['revenue']<=>$a['revenue'])?:($b['conversions']<=>$a['conversions']));

layout('Marketing attribution',function()use($aggregate,$details,$model,$days){
    $totalRevenue=array_sum(array_column($aggregate,'revenue'));$totalConversions=array_sum(array_column($aggregate,'conversions'));
    ?><div class="actions"><h1 style="margin:0">Marketing attribution</h1><span class="spacer"></span><a class="btn secondary" href="/promotions/links/stats">Link analytics</a></div>
    <form class="card form-grid" method="get"><label>Attribution model<select name="model"><option value="first" <?=$model==='first'?'selected':''?>>First touch</option><option value="last" <?=$model==='last'?'selected':''?>>Last touch</option><option value="linear" <?=$model==='linear'?'selected':''?>>Linear multi-touch</option></select></label><label>Outcome window<select name="days"><?php foreach([30,60,90,180,365] as $n):?><option value="<?=$n?>" <?=$days===$n?'selected':''?>>Last <?=$n?> days</option><?php endforeach?></select></label><button>Apply</button></form>
    <div class="grid"><div class="card"><div class="metric"><?=number_format($totalConversions,2)?></div><span class="muted">Attributed conversions</span></div><div class="card"><div class="metric">$<?=number_format($totalRevenue,2)?></div><span class="muted">Attributed paid revenue</span></div></div>
    <div class="card"><h2>Channel and campaign performance</h2><div style="overflow:auto"><table><thead><tr><th>Source</th><th>Medium</th><th>Campaign</th><th>Touch credit</th><th>Conversions</th><th>Paid revenue</th></tr></thead><tbody><?php foreach($aggregate as $row):?><tr><td><?=e($row['source'])?></td><td><?=e($row['medium'])?></td><td><?=e($row['campaign'])?></td><td><?=number_format($row['touches'],2)?></td><td><?=number_format($row['conversions'],2)?></td><td>$<?=number_format($row['revenue'],2)?></td></tr><?php endforeach?></tbody></table></div><?php if(!$aggregate):?><p class="muted">No attributable conversions or paid quotes were found in this period.</p><?php endif?></div>
    <div class="card"><details><summary><strong>Attribution detail</strong> (<?=count($details)?> credited touches)</summary><div style="overflow:auto"><table><thead><tr><th>Outcome</th><th>Date</th><th>Source</th><th>Campaign</th><th>Credit</th><th>Revenue credit</th></tr></thead><tbody><?php foreach(array_reverse($details) as $row):?><tr><td><?=e($row['outcome_type'].' · '.$row['detail'])?></td><td><?=e($row['outcome_at'])?></td><td><?=e($row['source'])?></td><td><?=e($row['campaign'])?></td><td><?=number_format($row['credit']*100,1)?>%</td><td>$<?=number_format((float)$row['revenue']*$row['credit'],2)?></td></tr><?php endforeach?></tbody></table></div></details></div>
    <div class="card"><h2>How the models work</h2><p><strong>First touch</strong> credits the earliest known session, <strong>last touch</strong> credits the most recent session before the outcome, and <strong>linear multi-touch</strong> divides credit evenly across distinct source / medium / campaign touches in the preceding 90 days.</p></div><?php
});
exit;
