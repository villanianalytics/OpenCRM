<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$pdo=db();$pdo->beginTransaction();
try{
    $admin=(int)$pdo->query('SELECT id FROM users WHERE is_admin=1 LIMIT 1')->fetchColumn();
    foreach(['products','quotes','quote_items','workflows','resource_portals','email_suppressions','site_conversions'] as $table)if(!(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=".$pdo->quote($table))->fetchColumn())throw new RuntimeException("Missing table: $table");
    $pdo->prepare("INSERT INTO contacts(first_name,last_name,email,active,created_by,owner_id) VALUES('Expanded','Smoke','expanded-smoke@example.invalid',1,?,?)")->execute([$admin,$admin]);$contact=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO products(name,unit_price,currency,created_by,updated_by) VALUES('Smoke Product',25,'USD',?,?)")->execute([$admin,$admin]);$product=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO quotes(quote_number,contact_id,owner_user_id,title,public_token,created_by,updated_by) VALUES(?,?,?,?,?,?,?)")->execute(['SMOKE-'.bin2hex(random_bytes(4)),$contact,$admin,'Smoke proposal',bin2hex(random_bytes(32)),$admin,$admin]);$quote=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO quote_items(quote_id,product_id,description,quantity,unit_price,line_total) VALUES(?,?,?,?,?,?)")->execute([$quote,$product,'Smoke Product',2,25,50]);if(!$pdo->lastInsertId())throw new RuntimeException('Quote item creation failed');
    $pdo->prepare("INSERT INTO email_suppressions(email,reason,source) VALUES(?,'manual','smoke')")->execute(['expanded-smoke@example.invalid']);if(!(int)$pdo->query("SELECT COUNT(*) FROM email_suppressions WHERE email='expanded-smoke@example.invalid' AND released_at IS NULL")->fetchColumn())throw new RuntimeException('Email suppression failed');
    $pdo->rollBack();echo "Expanded smoke tests passed.\n";
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}

