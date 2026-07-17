<?php
declare(strict_types=1);

if($path!=='/admin/tags')return;
if(!(user()['is_admin']??false)){http_response_code(403);exit('Forbidden');}
if($method==='POST'){
    verify_csrf();$action=(string)post('action');$id=(int)post('tag_id');
    if(in_array($action,['create','update'],true)){
        $name=trim((string)post('name'));$color=strtolower(trim((string)post('color')));
        if($name===''||mb_strlen($name)>100||!preg_match('/^#[0-9a-f]{6}$/',$color)){flash('error','Enter a tag name and valid color.');redirect('/admin/tags');}
        try{if($action==='create'){db()->prepare('INSERT INTO tags(name,color) VALUES(?,?)')->execute([$name,$color]);$id=(int)db()->lastInsertId();}else db()->prepare('UPDATE tags SET name=?,color=? WHERE id=?')->execute([$name,$color,$id]);audit($action,$action==='create'?'tag':'tag',$id);flash('success','Tag saved.');}
        catch(PDOException $e){if(($e->errorInfo[1]??0)===1062)flash('error','A tag with that name already exists.');else throw $e;}
    }elseif($action==='delete'){
        $s=db()->prepare('SELECT name FROM tags WHERE id=?');$s->execute([$id]);$name=$s->fetchColumn();if($name){db()->prepare('DELETE FROM tags WHERE id=?')->execute([$id]);audit('delete','tag',$id,['name'=>$name]);flash('success','Tag deleted and removed from associated contacts.');}
    }
    redirect('/admin/tags');
}
$tags=db()->query('SELECT t.*,COUNT(DISTINCT ct.contact_id) contact_count,COUNT(DISTINCT tp.role_id) security_rule_count FROM tags t LEFT JOIN contact_tags ct ON ct.tag_id=t.id LEFT JOIN tag_permissions tp ON tp.tag_id=t.id GROUP BY t.id ORDER BY t.name')->fetchAll();
layout('Tags',function()use($tags){?><div class="actions"><h1 style="margin:0">Tags</h1><span class="spacer"></span><a class="btn secondary" href="/admin/tag-groups">Tag groups</a><a class="btn secondary" href="/admin/settings">Application settings</a></div><form class="card" method="post"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="create"><h2>Add tag</h2><div class="form-grid"><label>Name<input name="name" required maxlength="100"></label><label>Color<input type="color" name="color" value="#1565c0"></label></div><button>Add tag</button></form><div class="card"><h2>All tags</h2><table><thead><tr><th>Tag</th><th>Contacts</th><th>Security rules</th><th>Actions</th></tr></thead><tbody><?php foreach($tags as $t):?><tr><td><form method="post" class="actions"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="update"><input type="hidden" name="tag_id" value="<?=$t['id']?>"><input style="max-width:260px" name="name" value="<?=e($t['name'])?>" required maxlength="100"><input style="width:54px;height:42px" type="color" name="color" value="<?=e($t['color'])?>"><button>Save</button></form></td><td><?=$t['contact_count']?></td><td><?=$t['security_rule_count']?></td><td><form method="post" onsubmit="return confirm('Delete this tag and remove it from all contacts?')"><input type="hidden" name="_csrf" value="<?=csrf()?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="tag_id" value="<?=$t['id']?>"><button class="danger">Delete</button></form></td></tr><?php endforeach?></tbody></table></div><?php });exit;
