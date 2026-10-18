<?php
// Exact migrations only; never run unrelated pending migrations.
if (PHP_SAPI !== 'cli') { exit; }
require dirname(__DIR__).'/backend/src/autoload.php';
BloodMatch\Config\Env::load(dirname(__DIR__).'/.env');
$p=BloodMatch\Config\Database::pdo();
$target=$p->query('SELECT DATABASE() AS name,@@port AS port')->fetch();
if (!in_array($target['name'],['bloodmatch','bloodmatch_test'],true)) { throw new RuntimeException('Unexpected database.'); }
$files=['028_user_presence.sql','029_unique_hospitals.sql'];
echo json_encode(['target'=>$target,'apply'=>in_array('--apply',$argv,true),'migrations'=>$files]).PHP_EOL;
if (!in_array('--apply',$argv,true)) { exit; }
// Public facility data plus request associations, retained privately for recovery.
$backup=dirname(__DIR__).'/backend/storage/maintenance/notification-chat-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
$data=['target'=>$target,'hospitals'=>$p->query('SELECT * FROM hospitals')->fetchAll(),'request_hospitals'=>$p->query('SELECT id,hospital_id,updated_at FROM blood_requests WHERE hospital_id IS NOT NULL')->fetchAll()];
if (file_put_contents($backup,json_encode($data,JSON_THROW_ON_ERROR),LOCK_EX)===false) { throw new RuntimeException('Backup failed.'); }
foreach ($files as $file) {
    $s=$p->prepare('SELECT 1 FROM schema_migrations WHERE name=?');$s->execute([$file]);
    if ($s->fetchColumn()) { echo "Already applied: $file\n";continue; }
    $sql=file_get_contents(__DIR__.'/migrations/'.$file);
    // Permit resuming after DDL succeeded but ledger recording was interrupted.
    if ($file==='029_unique_hospitals.sql' && $p->query("SHOW INDEX FROM hospitals WHERE Key_name='uq_hospital_name_municipality'")->fetch()) { $sql=''; }
    foreach (explode(';',$sql) as $statement) { if (trim($statement)!=='') $p->exec($statement); }
    $p->prepare('INSERT INTO schema_migrations(name) VALUES(?)')->execute([$file]);
    echo "Applied: $file\n";
}
