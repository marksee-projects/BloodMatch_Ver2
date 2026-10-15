<?php
/** Read-only integrity checks; rollback-only trigger probes on the test DB. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
define('BLOODMATCH_MAINTENANCE_FUNCTIONS_ONLY',true);
require dirname(__DIR__).'/database/demo_maintenance.php';
use BloodMatch\Config\Env;

try {
    $args=getopt('',['target:','read-only']);
    $target=$args['target']??'bloodmatch_test';
    if (!in_array($target,['bloodmatch_test','bloodmatch'],true) || ($target==='bloodmatch' && !isset($args['read-only']))) throw new LogicException('Application checks require explicit --read-only.');
    $p=new PDO('mysql:host='.Env::get('DB_HOST','127.0.0.1').';port=3306;dbname='.$target.';charset=utf8mb4',Env::get('DB_USER','root'),Env::get('DB_PASS',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $actual=$p->query('SELECT DATABASE() db,@@port port')->fetch();
    if ($actual['db']!==$target || (int)$actual['port']!==3306) throw new LogicException('Wrong target.');
    $checks=0;
    $check=function(bool $ok,string $label) use(&$checks): void {if (!$ok) throw new LogicException('FAIL '.$label);$checks++;echo 'PASS '.$label.PHP_EOL;};
    $check(scalar($p,'SELECT COUNT(*) FROM users')===15,'Fifteen retained accounts');
    $check(scalar($p,"SELECT COUNT(*) FROM users WHERE role='admin'")===1 && scalar($p,"SELECT COUNT(*) FROM users WHERE role='admin' AND id=3 AND email='markseejr@gmail.com' AND account_status='active'")===1,'Exactly the designated active administrator');
    $check(array_sum(chatIntegrity($p))===0,'Chat references, positive IDs and imported enums');
    $check(chatSchemaReady($p),'Chat keys, uniqueness, precision and cascading references');
    $check(scalar($p,"SELECT COUNT(*) FROM schema_migrations WHERE name IN ('025_chat_privacy_lifecycle.sql','026_audit_retention.sql','027_single_administrator.sql')")===3,'Three privacy migrations recorded');
    $references=$p->query("SELECT TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL")->fetchAll();
    foreach ($references as $fk) {
        $sql='SELECT COUNT(*) FROM '.ident($fk['TABLE_NAME']).' c LEFT JOIN '.ident($fk['REFERENCED_TABLE_NAME']).' p ON c.'.ident($fk['COLUMN_NAME']).'=p.'.ident($fk['REFERENCED_COLUMN_NAME']).' WHERE c.'.ident($fk['COLUMN_NAME']).' IS NOT NULL AND p.'.ident($fk['REFERENCED_COLUMN_NAME']).' IS NULL';
        $check(scalar($p,$sql)===0,'No orphan FK '.$fk['TABLE_NAME'].'.'.$fk['COLUMN_NAME']);
    }
    if (isset($args['read-only'])) { echo $checks.' read-only schema checks passed on '.$target.'; no trigger probes executed.'.PHP_EOL;exit; }
    $member=(int)$p->query("SELECT id FROM users WHERE role='member' ORDER BY id LIMIT 1")->fetchColumn();
    $probes=[
        'Member cannot be promoted to another administrator'=>"UPDATE users SET role='admin' WHERE id=$member",
        'Designated administrator cannot be demoted'=>"UPDATE users SET role='member' WHERE id=3",
        'Designated administrator cannot be deactivated'=>"UPDATE users SET account_status='deactivated' WHERE id=3",
        'Designated administrator cannot be deleted'=>'DELETE FROM users WHERE id=3',
        'Ordinary audit deletion remains blocked'=>'DELETE FROM audit_log ORDER BY id DESC LIMIT 1',
    ];
    foreach ($probes as $label=>$sql) {
        $p->beginTransaction();$denied=false;
        try { $p->exec($sql); }
        catch (PDOException $e) { if (($e->errorInfo[0]??'')!=='45000') throw $e;$denied=true; }
        finally { if ($p->inTransaction()) $p->rollBack(); }
        $check($denied,$label);
    }
    echo $checks.' live schema/trigger checks passed; probes rolled back.'.PHP_EOL;
} catch(Throwable $e) {fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
