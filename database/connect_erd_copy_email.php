<?php
/** Academic copy only: preserve email accounting and connect its donor reference. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit; }
$db = 'bloodmatcherd (13)';
$root = dirname(__DIR__);
$dir = $root.'/backend/storage/db-review';
$backup = $dir.'/bloodmatcherd-13-before-email-user-fk-20261021.sql';
if (is_file($dir.'/bloodmatcherd-13-email-user-result.json')) {
    throw new LogicException('Copy update already completed; keep the original backup and review its result report.');
}
if (!is_file($backup) || filesize($backup)<1000
    || !str_contains(file_get_contents($backup), '-- Dump completed on')) {
    throw new RuntimeException('Complete copy backup required.');
}
$p = new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$db.';charset=utf8mb4', 'root', '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$target = $p->query('SELECT DATABASE() db, @@port port')->fetch();
if ($target['db']!==$db || (int)$target['port']!==3306) { throw new LogicException('Copy target mismatch.'); }
$p->exec('SET SESSION lock_wait_timeout=5');
$snapshot = static function () use ($p): array {
    $result=[];
    foreach ($p->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $q='`'.str_replace('`','``',$table).'`';
        $rows=$p->query('SELECT * FROM '.$q)->fetchAll();
        $encoded=array_map(fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),$rows);
        sort($encoded,SORT_STRING);
        $result[$table]=['rows'=>count($rows),'sha256'=>hash('sha256',implode("\n",$encoded))];
    }
    return $result;
};
$before=$snapshot();
$orphan=(int)$p->query('SELECT COUNT(*) FROM email_donor_reservations r LEFT JOIN users u ON u.id=r.donor_id WHERE u.id IS NULL')->fetchColumn();
if ($orphan!==0) { throw new LogicException('Resolve orphan donor references first.'); }
$fk=$p->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_donor_reservations' AND COLUMN_NAME='donor_id' AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'")->fetchColumn();
if ($fk===false) {
    $p->exec('ALTER TABLE email_donor_reservations ADD CONSTRAINT fk_erd_email_reservation_donor FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE RESTRICT ON UPDATE RESTRICT');
}

// Verify enforced rejection, valid insertion and independent-connection locking.
// All inserted rows roll back; no mail transport or application worker is used.
$attempt=$p->query('SELECT a.id FROM email_attempts a LEFT JOIN email_donor_reservations r ON r.attempt_id=a.id WHERE r.attempt_id IS NULL ORDER BY a.id LIMIT 1')->fetchColumn();
$donor=$p->query('SELECT donor_id FROM email_donor_reservations ORDER BY attempt_id LIMIT 1')->fetchColumn();
if ($attempt===false || $donor===false) { throw new LogicException('Existing copy fixtures required for FK checks.'); }
$insert=$p->prepare("INSERT INTO email_donor_reservations(attempt_id,donor_id,reserved_at,updated_at) VALUES (?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
$p->beginTransaction();
try {
    $missing=(int)$p->query('SELECT MAX(id)+1 FROM users')->fetchColumn();
    try { $insert->execute([$attempt,$missing]); throw new LogicException('Orphan insert unexpectedly succeeded.'); }
    catch (PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1452) { throw $e; } }
} finally { $p->rollBack(); }
$other=new PDO('mysql:host=127.0.0.1;port=3306;dbname='.$db.';charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$other->exec('SET SESSION innodb_lock_wait_timeout=1');
$p->beginTransaction();
$lock=$p->prepare('SELECT id FROM users WHERE id=? FOR UPDATE'); $lock->execute([$donor]); $lock->fetchAll();
$other->beginTransaction();
try {
    $s=$other->prepare('INSERT INTO email_donor_reservations(attempt_id,donor_id,reserved_at,updated_at) VALUES (?,?,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))');
    try { $s->execute([$attempt,$donor]); throw new LogicException('Expected parent-lock wait was absent.'); }
    catch (PDOException $e) { if ((int)($e->errorInfo[1]??0)!==1205) { throw $e; } }
} finally {
    if ($other->inTransaction()) { $other->rollBack(); }
    $p->rollBack();
}
$p->beginTransaction();
try { $insert->execute([$attempt,$donor]); } finally { $p->rollBack(); }

// Save a complete expanded-table layout on this copy's default Designer page.
$schema=[];
foreach ($p->query('SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME,ORDINAL_POSITION') as $c) { $schema[$c['TABLE_NAME']][]=$c; }
$lanes=[
    ['member_documents','verification_decisions','account_closures','audit_log','pending_registrations','auth_throttle'],
    ['chat_requests','conversations','conversation_participants','messages','chat_reports','chat_report_evidence','user_presence'],
    ['users','chapters','bataan_locations','hospitals','donor_response_state'],
    ['blood_requests','matches','donation_chat_contexts','donation_coordination_events','compatibility_pairs'],
    ['donation_reports','donation_report_evidence','donation_report_events','donation_correction_requests'],
    ['donor_opportunities','notifications','email_outbox','email_donor_reservations','email_attempts','system_settings','schema_migrations'],
];
$planned=array_merge(...$lanes);
if (count($planned)!==count(array_unique($planned)) || array_diff(array_keys($schema),$planned) || array_diff($planned,array_keys($schema))) { throw new LogicException('Unexpected table inventory.'); }
$positions=[]; $x=45;
foreach ($lanes as $tables) {
    $width=0; $y=65;
    foreach ($tables as $table) {
        $w=100+strlen($db.' '.$table)*8;
        foreach ($schema[$table] as $c) { $w=max($w,70+strlen($c['COLUMN_NAME'].' : '.$c['COLUMN_TYPE'])*8); }
        $width=max($width,$w);
    }
    foreach ($tables as $table) {
        $positions[$table]=['x'=>$x,'y'=>$y];
        $y+=38+count($schema[$table])*24+55;
    }
    $x+=$width+100;
}
$s=$p->prepare('SELECT * FROM phpmyadmin.pma__pdf_pages WHERE db_name=?'); $s->execute([$db]); $pages=$s->fetchAll();
$s=$p->prepare('SELECT * FROM phpmyadmin.pma__table_coords WHERE db_name=?'); $s->execute([$db]); $coords=$s->fetchAll();
file_put_contents($dir.'/bloodmatcherd-13-designer-before.json',json_encode(['pages'=>$pages,'coordinates'=>$coords],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
$p->beginTransaction();
try {
    $s=$p->prepare('SELECT page_nr FROM phpmyadmin.pma__pdf_pages WHERE db_name=? AND page_descr=? ORDER BY page_nr LIMIT 1'); $s->execute([$db,$db]); $page=$s->fetchColumn();
    if ($page===false) { $p->prepare('INSERT INTO phpmyadmin.pma__pdf_pages(db_name,page_descr) VALUES (?,?)')->execute([$db,$db]); $page=$p->lastInsertId(); }
    $s=$p->prepare('INSERT INTO phpmyadmin.pma__table_coords(db_name,table_name,pdf_page_number,x,y) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE x=VALUES(x),y=VALUES(y)');
    foreach ($positions as $table=>$pos) { $s->execute([$db,$table,$page,$pos['x'],$pos['y']]); }
    $s=$p->prepare('SELECT table_name,x,y FROM phpmyadmin.pma__table_coords WHERE db_name=? AND pdf_page_number=?'); $s->execute([$db,$page]); $saved=$s->fetchAll();
    if (count($saved)!==count($positions)) { throw new LogicException('Saved layout inventory mismatch.'); }
    foreach ($saved as $row) { if ($positions[$row['table_name']]!==['x'=>(int)$row['x'],'y'=>(int)$row['y']]) { throw new LogicException('Saved coordinate mismatch.'); } }
    $p->commit();
} catch (Throwable $e) { if ($p->inTransaction()) { $p->rollBack(); } throw $e; }
$after=$snapshot();
if ($before!==$after) { throw new LogicException('Data changed; inspect backup and copy.'); }
$report=['database'=>$db,'backup_sha256'=>hash_file('sha256',$backup),'table_count'=>count($after),
    'all_table_data_unchanged'=>true,'orphan_insert_rejected'=>true,'valid_insert_rolled_back'=>true,
    'parent_lock_wait_confirmed'=>true,'designer_page'=>(int)$page,'saved_positions'=>count($saved),
    'relationship'=>'email_donor_reservations.donor_id -> users.id; RESTRICT delete/update',
    'scope'=>'Academic copy only. Main and application configuration untouched. No tables dropped.',
    'snapshots'=>$after];
file_put_contents($dir.'/bloodmatcherd-13-email-user-result.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
unset($report['snapshots']); echo json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
