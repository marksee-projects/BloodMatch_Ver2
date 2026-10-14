<?php
// User-run repair for imported 021–023 tables. Default mode is SELECT-only.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/backend/src/autoload.php';
require __DIR__ . '/operational_checkpoint_support.php';

function importSnapshot(PDO $pdo, array $tables): array {
    $result = [];
    foreach ($tables as $table => $spec) {
        $hash = hash_init('sha256'); $count = 0;
        $rows = $pdo->query('SELECT * FROM ' . $table . ' ORDER BY ' . $spec['key']);
        while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
            hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR) . "\n"); $count++;
        }
        $result[$table] = ['count' => $count, 'sha256' => hash_final($hash)];
    }
    return $result;
}
function normalizedImportDefault($value) {
    return $value === null || strtoupper((string)$value) === 'NULL' ? null : trim((string)$value, "'");
}


const ORPHAN_ARCHIVE = 'email_outbox_orphan_archive_20261013';
const REVIEWED_ORPHAN_SHA256 = 'e9ed0bbb95090826b97687e3b02347c5ffe0c657be5741a0e2ebd5827e49ccda';
function assertReviewedOrphans(array $rows, string $expectedHash = REVIEWED_ORPHAN_SHA256): void {
    $wanted = [[1,475,'sent'],[2,480,'cancelled'],[3,481,'cancelled'],[4,482,'cancelled'],[5,483,'cancelled'],[6,484,'cancelled'],[7,485,'pending']];
    $actual = array_map(static fn(array $r): array => [(int)$r['id'],(int)$r['notification_id'],$r['status']], $rows);
    if ($actual !== $wanted || !hash_equals($expectedHash, hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)))) {
        throw new LogicException('STOP: orphan count, IDs, notification references, statuses or original fields differ from the reviewed seven.');
    }
}
function readImportOrphans(PDO $pdo, bool $lock = false): array {
    return $pdo->query('SELECT e.* FROM email_outbox e LEFT JOIN notifications n ON n.id=e.notification_id WHERE n.id IS NULL ORDER BY e.id'.($lock ? ' FOR UPDATE' : ''))->fetchAll(PDO::FETCH_ASSOC);
}
function archiveExists(PDO $pdo): bool {
    $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $q->execute([ORPHAN_ARCHIVE]); return (int)$q->fetchColumn()!==0;
}
function unrelatedImportSnapshot(PDO $pdo): array {
    $result=[];
    $names=$pdo->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE=\'BASE TABLE\' ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($names as $name) {
        if (in_array($name,['email_outbox','schema_migrations',ORPHAN_ARCHIVE],true)) { continue; }
        // Identifiers are obtained from information_schema, never user input.
        $quote=static fn(string $s): string => chr(96).str_replace(chr(96),chr(96).chr(96),$s).chr(96);
        $cols=$pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
        $cols->execute([$name]); $order=implode(',',array_map($quote,$cols->fetchAll(PDO::FETCH_COLUMN)));
        $hash=hash_init('sha256'); $count=0;
        $rows=$pdo->query('SELECT * FROM '.$quote($name).' ORDER BY '.$order);
        while ($row=$rows->fetch(PDO::FETCH_ASSOC)) { hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n"); $count++; }
        $result[$name]=['count'=>$count,'sha256'=>hash_final($hash)];
    }
    return $result;
}
function unrelatedImportLedger(PDO $pdo): array {
    return $pdo->query("SELECT * FROM schema_migrations WHERE name NOT IN ('021_email_outbox_accounting.sql','022_non_response_opportunities.sql','023_email_donor_reservations.sql') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
}
function mergedOutboxSnapshot(PDO $pdo): array {
    $rows=$pdo->query('SELECT * FROM email_outbox UNION ALL SELECT * FROM '.ORPHAN_ARCHIVE.' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $hash=hash_init('sha256');
    foreach ($rows as $row) { hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n"); }
    return ['count'=>count($rows),'sha256'=>hash_final($hash)];
}
function verifyArchivedImport(PDO $pdo, array $review, bool $requireLedger): array {
    if (($review['database']??null)!=='bloodmatch' || ($review['port']??null)!==3306 || ($review['orphan_sha256']??null)!==REVIEWED_ORPHAN_SHA256) { throw new LogicException('Unexpected saved review target/fingerprint.'); }
    if (!archiveExists($pdo)) { throw new LogicException('Archive is missing.'); }
    $archived=$pdo->query('SELECT * FROM '.ORPHAN_ARCHIVE.' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    assertReviewedOrphans($archived);
    if (readImportOrphans($pdo)!==[]) { throw new LogicException('Active queue still has orphan jobs.'); }
    if ($pdo->query('SELECT 1 FROM email_outbox WHERE id IN (1,2,3,4,5,6,7) LIMIT 1')->fetchColumn()!==false) { throw new LogicException('An archived job remains in the active queue.'); }
    if (mergedOutboxSnapshot($pdo)!==$review['rows']['email_outbox']) { throw new LogicException('Combined archive/active records differ from the reviewed original queue.'); }
    if (unrelatedImportSnapshot($pdo)!==$review['unrelated_rows'] || unrelatedImportLedger($pdo)!==$review['unrelated_ledger']) { throw new LogicException('Unrelated data changed; stop and inspect.'); }
    if ($requireLedger) {
        $next=$pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_outbox'")->fetchColumn();
        if ((int)$next<(int)$review['next_outbox_id']) { throw new LogicException('Outbox identity sequence would reuse archived IDs.'); }
        if (emailCheckpointShape($pdo)!==['email_outbox'=>'ready','email_attempts'=>'ready'] || in_array('missing',nonResponseShape($pdo),true) || donorReservationShape($pdo)!=='ready') { throw new LogicException('Repaired schema is not ready.'); }
        $ledger=$pdo->query("SELECT name FROM schema_migrations WHERE name IN ('021_email_outbox_accounting.sql','022_non_response_opportunities.sql','023_email_donor_reservations.sql') ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        if ($ledger!==['021_email_outbox_accounting.sql','022_non_response_opportunities.sql','023_email_donor_reservations.sql']) { throw new LogicException('Repair migration ledger is incomplete.'); }
    }
    return ['database'=>'bloodmatch','port'=>3306,'mode'=>'verify','writes'=>false,'archive_table'=>ORPHAN_ARCHIVE,'archived_count'=>7,'original_fields_and_ids_preserved'=>true,'active_orphan_count'=>0,'unrelated_data_preserved'=>true,'schema_ready'=>$requireLedger];
}
if (defined('BLOODMATCH_REPAIR_FUNCTIONS_ONLY')) { return; }

$locked = false; $pdo = null;
try {
    $options = getopt('', ['apply', 'review-sha256:', 'backup:', 'backup-sha256:', 'maintenance-confirmed', 'verify', 'review-file:']);
    $pdo = operationalTarget('application');
    if (isset($options['verify'])) {
        if (isset($options['apply'])) { throw new LogicException('Choose verify or apply, never both.'); }
        $reviewPath=(string)($options['review-file']??'');
        if (!is_file($reviewPath)) { throw new LogicException('Saved read-only review file required.'); }
        $review=json_decode(preg_replace('/^\xEF\xBB\xBF/','',file_get_contents($reviewPath)),true,512,JSON_THROW_ON_ERROR);
        echo json_encode(verifyArchivedImport($pdo,$review,true),JSON_PRETTY_PRINT).PHP_EOL; exit;
    }
    if (isset($options['apply'])) {
        if (!isset($options['maintenance-confirmed'])) { throw new LogicException('Stopped PHP/database/email writers required.'); }
        if ((int)$pdo->query("SELECT GET_LOCK('bloodmatch.import_schema_repair',0)")->fetchColumn()!==1) { throw new LogicException('Another repair is running.'); }
        $locked=true;
    }
    if (archiveExists($pdo)) { throw new LogicException('Archive already exists. Use --verify with saved review; do not reapply automatically.'); }
    $orphans=readImportOrphans($pdo);
    assertReviewedOrphans($orphans);
    foreach (['email_outbox','email_attempts','donor_response_state','donor_opportunities','email_donor_reservations'] as $table) {
        $q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND EVENT_OBJECT_TABLE=?'); $q->execute([$table]);
        if ((int)$q->fetchColumn()!==0) { throw new LogicException('Unexpected trigger on repair/archive target: '.$table); }
    }
    $tables = [
        'email_outbox' => ['migration'=>'021_email_outbox_accounting.sql','key'=>'id','auto'=>true,
            'indexes'=>['PRIMARY'=>[0,'id'],'uq_email_outbox_notification'=>[0,'notification_id'],'uq_email_outbox_delivery'=>[0,'delivery_key'],'idx_email_outbox_due'=>[1,'status,priority,next_attempt_at,id'],'idx_email_outbox_lease'=>[1,'status,lease_expires_at']],
            'fks'=>['notification_id'=>['fk_email_outbox_notification','notifications','id','CASCADE']]],
        'email_attempts' => ['migration'=>'021_email_outbox_accounting.sql','key'=>'id','auto'=>true,
            'indexes'=>['PRIMARY'=>[0,'id'],'idx_email_attempts_window'=>[1,'attempted_at,mail_class']], 'fks'=>[]],
        'donor_response_state' => ['migration'=>'022_non_response_opportunities.sql','key'=>'user_id','auto'=>false,
            'indexes'=>['PRIMARY'=>[0,'user_id']], 'fks'=>['user_id'=>['fk_response_state_user','users','id','CASCADE']]],
        'donor_opportunities' => ['migration'=>'022_non_response_opportunities.sql','key'=>'id','auto'=>true,
            'indexes'=>['PRIMARY'=>[0,'id'],'uq_opportunity_generation'=>[0,'request_id,donor_id,generation'],'idx_opportunity_due'=>[1,'status,due_at,id'],'idx_opportunity_donor'=>[1,'donor_id,status,due_at']],
            'fks'=>['request_id'=>['fk_opportunity_request','blood_requests','id','CASCADE'],'donor_id'=>['fk_opportunity_donor','users','id','CASCADE'],'notification_id'=>['fk_opportunity_notification','notifications','id','SET NULL']]],
        'email_donor_reservations' => ['migration'=>'023_email_donor_reservations.sql','key'=>'attempt_id','auto'=>false,
            'indexes'=>['PRIMARY'=>[0,'attempt_id'],'idx_donor_reservation_window'=>[1,'donor_id,reserved_at,outcome']],
            'fks'=>['attempt_id'=>['fk_donor_reservation_attempt','email_attempts','id','RESTRICT']]],
    ];
    $plan = []; $definitions = []; $schema = [];
    // Validate every table and all data before any DDL is allowed.
    foreach ($tables as $table => $spec) {
        $file = __DIR__ . '/migrations/' . $spec['migration'];
        $sql = file_get_contents($file);
        if ($sql === false || !preg_match('/CREATE TABLE ' . $table . ' \((.*?)\) ENGINE=/s', $sql, $match)) {
            throw new LogicException('Missing canonical definition: ' . $table);
        }
        $definitions[$spec['migration']] = hash('sha256', $sql);
        $expected = [];
        foreach (explode("\n", $match[1]) as $line) {
            if (!preg_match('/^\s*(\w+)\s+(ENUM\([^)]*\)|[A-Z]+(?:\(\d+\))?(?: UNSIGNED)?)\s+(NOT NULL|NULL)(.*)/', $line, $column)) { continue; }
            $default = null;
            if (preg_match("/DEFAULT\s+('[^']*'|[0-9]+|NULL)/", $column[4], $d)) { $default = normalizedImportDefault($d[1]); }
            $expected[$column[1]] = [strtolower($column[2]), $column[3] === 'NULL' ? 'YES' : 'NO', $default];
        }
        if (!$expected) { throw new LogicException('Could not read expected columns: ' . $table); }
        $q=$pdo->prepare('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $q->execute([$table]); $meta=$q->fetch();
        if (!$meta || $meta['ENGINE']!=='InnoDB' || $meta['TABLE_COLLATION']!=='utf8mb4_unicode_ci') { throw new LogicException('Unexpected/missing table: '.$table); }
        $q=$pdo->prepare('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
        $q->execute([$table]); $actual=[]; $extras=[];
        foreach ($q->fetchAll() as $col) {
            $type=preg_replace('/^(bigint|int|smallint)\(\d+\)/','$1',strtolower($col['COLUMN_TYPE']));
            $actual[$col['COLUMN_NAME']]=[$type,$col['IS_NULLABLE'],normalizedImportDefault($col['COLUMN_DEFAULT'])];
            $extras[$col['COLUMN_NAME']]=$col['EXTRA'];
            $allowed=($spec['auto'] && $col['COLUMN_NAME']===$spec['key']) ? ['', 'auto_increment'] : [''];
            if (!in_array($col['EXTRA'],$allowed,true) || ($col['COLLATION_NAME']!==null && $col['COLLATION_NAME']!=='utf8mb4_unicode_ci')) { throw new LogicException('Unexpected column attributes: '.$table); }
        }
        if ($actual!==$expected) { throw new LogicException('Column definition differs; no automatic conversion: '.$table); }
        $schema[$table]=$pdo->query('SHOW CREATE TABLE '.$table)->fetch(PDO::FETCH_NUM)[1];
        $q=$pdo->prepare('SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX');
        $q->execute([$table]); $indexes=[];
        foreach ($q->fetchAll() as $idx) {
            if ($idx['SUB_PART']!==null) { throw new LogicException('Unexpected prefix index: '.$table); }
            $name=$idx['INDEX_NAME'];
            if (!isset($indexes[$name])) { $indexes[$name]=[(int)$idx['NON_UNIQUE'],$idx['COLUMN_NAME']]; }
            else { $indexes[$name][1].=','.$idx['COLUMN_NAME']; }
        }
        $allowedIndexes=$spec['indexes'];
        if ($table==='donor_opportunities') { $allowedIndexes['fk_opportunity_notification']=[1,'notification_id']; }
        foreach ($indexes as $name=>$shape) {
            if (!isset($allowedIndexes[$name]) || $allowedIndexes[$name]!==$shape) { throw new LogicException('Unexpected index; no replacement: '.$table.'.'.$name); }
        }
        foreach ($spec['indexes'] as $name=>$shape) {
            if ($shape[0]===0) {
                $cols=$shape[1];
                if ($pdo->query('SELECT 1 FROM '.$table.' GROUP BY '.$cols.' HAVING COUNT(*)>1 LIMIT 1')->fetchColumn()!==false) { throw new LogicException('Duplicate unique key values: '.$table.'.'.$name); }
            }
            if (isset($indexes[$name])) { continue; }
            $clause=$name==='PRIMARY' ? 'PRIMARY KEY' : ($shape[0]===0 ? 'UNIQUE INDEX ' : 'INDEX ').$name;
            $plan[]='ALTER TABLE '.$table.' ADD '.$clause.' ('.$shape[1].')';
        }
        if ($spec['auto'] && $extras[$spec['key']]==='') {
            $plan[]='ALTER TABLE '.$table.' MODIFY COLUMN '.$spec['key'].' BIGINT UNSIGNED NOT NULL AUTO_INCREMENT';
        }
        if ($spec['auto'] && $pdo->query('SELECT 1 FROM '.$table.' WHERE '.$spec['key'].'=0 LIMIT 1')->fetchColumn()!==false) { throw new LogicException('Zero identity value requires manual review: '.$table); }
    }
    foreach ($tables as $table=>$spec) {
        $q=$pdo->prepare('SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=?');
        $q->execute([$table]); $existing=[];
        foreach ($q->fetchAll() as $fk) {
            $column=$fk['COLUMN_NAME']; $wanted=$spec['fks'][$column]??null;
            if ($wanted===null || isset($existing[$column]) || [$fk['REFERENCED_TABLE_NAME'],$fk['REFERENCED_COLUMN_NAME'],$fk['DELETE_RULE']]!==array_slice($wanted,1) || $fk['UPDATE_RULE']!=='RESTRICT') { throw new LogicException('Unexpected foreign key: '.$table); }
            $existing[$column]=true;
        }
        foreach ($spec['fks'] as $column=>$fk) {
            [$name,$parent,$parentCol,$rule]=$fk;
            if (!($table==='email_outbox' && $column==='notification_id') && $pdo->query('SELECT 1 FROM '.$table.' c LEFT JOIN '.$parent.' p ON c.'.$column.'=p.'.$parentCol.' WHERE c.'.$column.' IS NOT NULL AND p.'.$parentCol.' IS NULL LIMIT 1')->fetchColumn()!==false) { throw new LogicException('Orphan reference: '.$table.'.'.$column); }
            if (!isset($existing[$column])) { $plan[]='ALTER TABLE '.$table.' ADD CONSTRAINT '.$name.' FOREIGN KEY ('.$column.') REFERENCES '.$parent.' ('.$parentCol.') ON DELETE '.$rule; }
        }
    }
    // Preserve the outbox identity sequence even after its old jobs leave the queue.
    $maxId=(string)($pdo->query('SELECT COALESCE(MAX(id),0) FROM email_outbox')->fetchColumn());
    $q=$pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_outbox'");
    $oldNext=(string)($q->fetchColumn()?:'1');
    if (!ctype_digit($maxId) || strlen($maxId)>18 || !ctype_digit($oldNext) || strlen($oldNext)>18) { throw new LogicException('Outbox identity sequence requires manual review.'); }
    $nextId=max((int)$maxId+1,(int)$oldNext,8);
    $plan[]='ALTER TABLE email_outbox AUTO_INCREMENT='.$nextId;
    $snapshot=importSnapshot($pdo,$tables);
    $ledger=$pdo->query("SELECT name FROM schema_migrations WHERE name IN ('021_email_outbox_accounting.sql','022_non_response_opportunities.sql','023_email_donor_reservations.sql') ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    $unrelatedRows=unrelatedImportSnapshot($pdo); $unrelatedLedger=unrelatedImportLedger($pdo);
    $hash=hash('sha256',json_encode([$schema,$snapshot,$plan,$definitions,$ledger,$unrelatedRows,$unrelatedLedger,REVIEWED_ORPHAN_SHA256,hash_file('sha256',__FILE__)],JSON_THROW_ON_ERROR));
    $review=['database'=>'bloodmatch','port'=>3306,'mode'=>'review','writes'=>false,'archive_table'=>ORPHAN_ARCHIVE,'next_outbox_id'=>$nextId,'orphan_count'=>7,'orphan_sha256'=>REVIEWED_ORPHAN_SHA256,
        'orphan_jobs'=>array_map(static fn(array $r): array=>['id'=>(int)$r['id'],'notification_id'=>(int)$r['notification_id'],'status'=>$r['status']],$orphans),
        'rows'=>$snapshot,'unrelated_rows'=>$unrelatedRows,'unrelated_ledger'=>$unrelatedLedger,
        'archive_sql'=>['CREATE TABLE '.ORPHAN_ARCHIVE.' LIKE email_outbox','INSERT INTO '.ORPHAN_ARCHIVE.' SELECT * FROM email_outbox WHERE id IN (1,2,3,4,5,6,7)','DELETE FROM email_outbox WHERE id IN (1,2,3,4,5,6,7)'],
        'sql'=>$plan,'ledger'=>$ledger,'review_sha256'=>$hash];
    if (!isset($options['apply'])) {
        echo json_encode($review,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
        exit;
    }
    if (!isset($options['maintenance-confirmed']) || !hash_equals($hash,(string)($options['review-sha256']??''))) { throw new LogicException('Stopped writers and current reviewed SHA-256 required.'); }
    $backup=(string)($options['backup']??'');
    if (!is_file($backup) || filesize($backup)===0 || !hash_equals(strtolower((string)($options['backup-sha256']??'')),hash_file('sha256',$backup))) { throw new LogicException('Existing SQL backup and matching SHA-256 required.'); }
    $handle=fopen($backup,'rb');
    if (!$handle) { throw new LogicException('Cannot inspect backup.'); }
    $header=fread($handle,16384); fseek($handle,max(0,filesize($backup)-4096)); $tail=stream_get_contents($handle); fclose($handle);
    if (!str_contains($header,'Database: bloodmatch') || !str_contains($tail,'-- Dump completed on')) { throw new LogicException('Backup target/completion marker missing.'); }
    // CREATE TABLE LIKE deliberately copies original field definitions without foreign keys.
    // Archive creation and later repair DDL auto-commit; copy/removal is one transaction.
    $pdo->exec('CREATE TABLE '.ORPHAN_ARCHIVE.' LIKE email_outbox');
    $pdo->beginTransaction();
    assertReviewedOrphans(readImportOrphans($pdo,true));
    if (unrelatedImportSnapshot($pdo)!==$unrelatedRows || unrelatedImportLedger($pdo)!==$unrelatedLedger || importSnapshot($pdo,$tables)!==$snapshot) { throw new LogicException('Reviewed data changed before archival.'); }
    if ($pdo->exec('INSERT INTO '.ORPHAN_ARCHIVE.' SELECT * FROM email_outbox WHERE id IN (1,2,3,4,5,6,7)')!==7) { throw new LogicException('Archive insertion count differs from seven.'); }
    $archived=$pdo->query('SELECT * FROM '.ORPHAN_ARCHIVE.' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    assertReviewedOrphans($archived);
    if ($pdo->exec('DELETE FROM email_outbox WHERE id IN (1,2,3,4,5,6,7)')!==7) { throw new LogicException('Queue removal count differs from seven.'); }
    verifyArchivedImport($pdo,$review,false);
    $pdo->commit();
    foreach ($plan as $statement) { $pdo->exec($statement); }
    if (emailCheckpointShape($pdo)!==['email_outbox'=>'ready','email_attempts'=>'ready'] || in_array('missing',nonResponseShape($pdo),true) || donorReservationShape($pdo)!=='ready') { throw new LogicException('Post-repair schema verification failed.'); }
    verifyArchivedImport($pdo,$review,false);
    $pdo->beginTransaction();
    foreach (array_keys($definitions) as $migration) {
        $q=$pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE name=?'); $q->execute([$migration]);
        if ((int)$q->fetchColumn()===0) { $pdo->prepare('INSERT INTO schema_migrations(name) VALUES (?)')->execute([$migration]); }
    }
    $pdo->commit();
    $verified=verifyArchivedImport($pdo,$review,true);
    echo json_encode(array_replace($verified,['ready'=>true,'mode'=>'apply','writes'=>true,'statements_applied'=>count($plan),'recorded'=>array_keys($definitions),'backup_sha256'=>hash_file('sha256',$backup)]),JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    fwrite(STDERR,($e instanceof LogicException ? $e->getMessage() : 'Database repair failed; inspect partial DDL and retain backup.').PHP_EOL.'Stop here; DDL auto-commits. Do not drop tables or retry without a fresh review.'.PHP_EOL);
    exit(1);
} finally {
    if ($locked && $pdo instanceof PDO) { $pdo->query("SELECT RELEASE_LOCK('bloodmatch.import_schema_repair')"); }
}
