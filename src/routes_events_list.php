<?php
declare(strict_types=1);

if($path!=='/events')return;
require_permission('events.view');
$timing=(string)($_GET['timing']??'upcoming');if(!in_array($timing,['upcoming','past','all'],true))$timing='upcoming';
$recordView=(string)($_GET['view']??'mine');$where=[];$args=[];
if($recordView!=='all'){$where[]='COALESCE(e.owner_id,e.created_by)=?';$args[]=user()['id'];}
if($timing==='upcoming')$where[]='e.starts_at>=NOW()';elseif($timing==='past')$where[]='e.starts_at<NOW()';
$sql='SELECT e.*,COUNT(ea.contact_id) attendees FROM events e LEFT JOIN event_attendees ea ON ea.event_id=e.id AND ea.attended=1'.($where?' WHERE '.implode(' AND ',$where):'').' GROUP BY e.id ORDER BY e.starts_at '.($timing==='past'?'DESC':'ASC');
$s=db()->prepare($sql);$s->execute($args);$rows=$s->fetchAll();
layout('Events',function()use($rows,$timing){?><div class="actions"><h1 style="margin:0">Events</h1><span class="spacer"></span><a class="btn <?=$timing==='upcoming'?'':'secondary'?>" href="/events?timing=upcoming">Upcoming</a><a class="btn <?=$timing==='past'?'':'secondary'?>" href="/events?timing=past">Past</a><a class="btn <?=$timing==='all'?'':'secondary'?>" href="/events?timing=all">All</a><?php if(can('events.edit')):?><a class="btn" href="/events/new">Add event</a><?php endif?></div><div class="card"><?php if(!$rows):?><p class="muted">No <?=e($timing)?> events found.</p><?php else:?><table><thead><tr><th>Event</th><th>Type</th><th>Date</th><th>Presentation</th><th>Presenter</th><th>Attendees</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><a href="/events/<?=$r['id']?>"><?=e($r['title'])?></a></td><td><?=e(option_label('event_type',$r['event_type']))?></td><td><?=e($r['starts_at'])?></td><td><?=e($r['presentation_title'])?></td><td><?=e($r['presenter_name'])?></td><td><?=$r['attendees']?></td></tr><?php endforeach?></tbody></table><?php endif?></div><?php });exit;
