<?php
/** User-run maintenance. Default is a SELECT-only review; never runs via HTTP. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Config\Env;
Env::load(dirname(__DIR__).'/.env');

function ident(string $value): string { return '`'.str_replace('`','``',$value).'`'; }
function scalar(PDO $pdo, string $sql): int { return (int)$pdo->query($sql)->fetchColumn(); }
function privateFile(string $name, string $contents): string {
    $dir=dirname(__DIR__).'/backend/storage/maintenance';
    if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Cannot create private maintenance directory.');
    $path=$dir.'/'.$name;
    if (file_exists($path) || file_put_contents($path,$contents,LOCK_EX)===false) throw new RuntimeException('Cannot write new maintenance file.');
    return $path;
}
function snapshot(PDO $pdo): array {
    $result=[];
    foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $table) {
        $name=$table[0]; $q=ident($name);
        $columns=$pdo->query('SHOW FULL COLUMNS FROM '.$q)->fetchAll();
        $order=implode(',',array_map(fn($c)=>ident($c['Field']),$columns));
        $hash=hash_init('sha256'); $count=0;
        $rows=$pdo->query('SELECT * FROM '.$q.' ORDER BY '.$order);
        while ($row=$rows->fetch()) { hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n"); $count++; }
        $result[$name]=['rows'=>$count,'data_sha256'=>hash_final($hash),
            'ddl'=>$pdo->query('SHOW CREATE TABLE '.$q)->fetch(PDO::FETCH_NUM)[1], 'columns'=>$columns];
    }
    ksort($result); return $result;
}
function chooseUsers(PDO $pdo): array {
    $rows=$pdo->query("SELECT u.id,u.email,u.full_name,u.role,u.verification_status,u.blood_type,u.chapter_id,u.donor_enrolled_at,
        (SELECT COUNT(*) FROM member_documents d WHERE d.user_id=u.id AND d.doc_type='national_id') identity_documents,
        (SELECT COUNT(*) FROM blood_requests r WHERE r.requester_id=u.id) requests,
        (SELECT COUNT(*) FROM donation_reports d WHERE d.donor_id=u.id) reports,
        (SELECT COUNT(*) FROM matches m WHERE m.donor_id=u.id AND m.status IN ('RESPONDED','COMPLETED')) responses
        FROM users u WHERE u.account_status='active' ORDER BY u.id")->fetchAll();
    $admin=array_values(array_filter($rows,fn($r)=>strtolower($r['email'])==='markseejr@gmail.com' && $r['role']==='admin'));
    if (count($admin)!==1) throw new LogicException('Expected exactly one active admin markseejr@gmail.com. No account changes allowed.');
    $selected=[(int)$admin[0]['id']=>$admin[0]];
    $members=array_values(array_filter($rows,fn($r)=>$r['role']==='member'));
    $score=fn($m)=>[(int)$m['reports'],(int)$m['responses'],(int)($m['donor_enrolled_at']!==null),(int)$m['identity_documents'],(int)!str_ends_with($m['email'],'@test.local'),(int)$m['requests'],-(int)$m['id']];
    usort($members,fn($a,$b)=>$score($b)<=>$score($a));
    foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $type) {
        foreach ($members as $m) if ($m['verification_status']==='verified' && $m['blood_type']===$type && !isset($selected[(int)$m['id']])) { $selected[(int)$m['id']]=$m; break; }
    }
    foreach (['pending'=>2,'rejected'=>1] as $status=>$limit) {
        foreach ($members as $m) if ($limit>0 && $m['verification_status']===$status && !isset($selected[(int)$m['id']])) { $selected[(int)$m['id']]=$m; $limit--; }
    }
    // Prefer chapter coverage, then existing requester/donor activity.
    foreach ($pdo->query('SELECT id FROM chapters ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $chapter) {
        if (count($selected)>=15) break;
        if (array_filter($selected,fn($m)=>(int)$m['chapter_id']===(int)$chapter && $m['role']==='member')) continue;
        foreach ($members as $m) if ($m['verification_status']==='verified' && (int)$m['chapter_id']===(int)$chapter && !isset($selected[(int)$m['id']])) { $selected[(int)$m['id']]=$m; break; }
    }
    foreach ($members as $m) if (count($selected)<15 && $m['verification_status']==='verified' && !isset($selected[(int)$m['id']])) $selected[(int)$m['id']]=$m;
    if (count($selected)!==15) throw new LogicException('Cannot select 15 representative existing accounts; review manually.');
    return array_values($selected);
}
function backup(PDO $pdo, string $db, int $port): string {
    $dump='C:/xampp/mysql/bin/mysqldump.exe';
    if (!is_file($dump)) throw new RuntimeException('mysqldump.exe missing at C:/xampp/mysql/bin; make an external full backup and adapt this path before applying.');
    $escape=fn($s)=>'"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','\\n','\\r'],(string)$s).'"';
    $config=privateFile('dump-'.bin2hex(random_bytes(8)).'.cnf',"[client]\nhost=".$escape(Env::get('DB_HOST','127.0.0.1'))."\nport=$port\nuser=".$escape(Env::get('DB_USER','root'))."\npassword=".$escape(Env::get('DB_PASS',''))."\n");
    $path=privateFile($db.'-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql','');
    try {
        $process=proc_open([$dump,'--defaults-extra-file='.$config,'--single-transaction','--routines','--events','--triggers','--hex-blob','--result-file='.$path,$db],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot start backup.');
        fclose($pipes[0]); stream_get_contents($pipes[1]); fclose($pipes[1]); $error=stream_get_contents($pipes[2]); fclose($pipes[2]);
        if (proc_close($process)!==0 || filesize($path)===0) throw new RuntimeException('Backup failed; no maintenance applied. '.$error);
    } finally { unlink($config); }
    return $path;
}
function chatIntegrity(PDO $p): array {
    return [
        'orphan_participants'=>scalar($p,'SELECT COUNT(*) FROM conversation_participants cp LEFT JOIN users u ON u.id=cp.user_id LEFT JOIN conversations c ON c.id=cp.conversation_id WHERE u.id IS NULL OR c.id IS NULL'),
        'orphan_messages'=>scalar($p,'SELECT COUNT(*) FROM messages m LEFT JOIN users u ON u.id=m.sender_id LEFT JOIN conversations c ON c.id=m.conversation_id WHERE u.id IS NULL OR c.id IS NULL'),
        'invalid_review_status'=>scalar($p,"SELECT COUNT(*) FROM blood_requests WHERE review_status=''"),
        'invalid_blood_type_source'=>scalar($p,"SELECT COUNT(*) FROM users WHERE blood_type_source=''"),
    ];
}
function chatSchemaReady(PDO $p): bool {
    foreach (['conversations','conversation_participants','messages'] as $table) {
        $s=$p->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='id' AND COLUMN_KEY='PRI' AND EXTRA LIKE '%auto_increment%'");$s->execute([$table]);
        if ((int)$s->fetchColumn()!==1) return false;
    }
    if (scalar($p,"SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='conversation_participants' AND COLUMN_NAME='last_read_at' AND DATETIME_PRECISION=3")!==1) return false;
    foreach (['conversation_participants'=>['conversation_id'=>'conversations','user_id'=>'users'],'messages'=>['conversation_id'=>'conversations','sender_id'=>'users']] as $table=>$references) {
        foreach ($references as $column=>$parent) {
            $s=$p->prepare("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.COLUMN_NAME=? AND k.REFERENCED_TABLE_NAME=? AND k.REFERENCED_COLUMN_NAME='id' AND r.DELETE_RULE='CASCADE'");$s->execute([$table,$column,$parent]);
            if ((int)$s->fetchColumn()!==1) return false;
        }
    }
    return scalar($p,"SELECT COUNT(*) FROM (SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='conversation_participants' AND NON_UNIQUE=0 GROUP BY INDEX_NAME HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX)='conversation_id,user_id') x")===1;
}
function triggerSnapshot(PDO $p): array {
    $rows=$p->query('SHOW TRIGGERS')->fetchAll();
    usort($rows,fn($a,$b)=>strcmp($a['Trigger'],$b['Trigger']));
    return $rows;
}
function repairImportArtifacts(PDO $p): void {
    // Do not infer historical approval from an account's current verification.
    // Unknown imported enum values are conservatively flagged for review.
    $p->beginTransaction();
    try {
        $ids=$p->query("SELECT id FROM blood_requests WHERE review_status='' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
        $p->exec("UPDATE blood_requests SET review_status='pending_review' WHERE review_status=''");
        $log=$p->prepare("INSERT INTO audit_log(actor_id,action,target_type,target_id,context) VALUES (NULL,'maintenance.import.review_status_repaired','blood_request',?,?)");
        foreach ($ids as $id) $log->execute([(string)$id,json_encode(['previous'=>'','new'=>'pending_review','reason'=>'Invalid imported enum; historical review status cannot be established.'],JSON_THROW_ON_ERROR)]);
        $users=$p->query("SELECT id,blood_type,blood_type_verified FROM users WHERE blood_type_source='' ORDER BY id")->fetchAll();
        $p->exec("UPDATE users SET blood_type_source=CASE WHEN blood_type IS NULL THEN NULL ELSE 'self_reported' END,blood_type_verified=0 WHERE blood_type_source=''");
        $provenanceLog=$p->prepare("INSERT INTO audit_log(actor_id,action,target_type,target_id,context) VALUES (NULL,'maintenance.import.provenance_repaired','user',?,?)");
        foreach ($users as $user) $provenanceLog->execute([(string)$user['id'],json_encode(['previous_source'=>'','previous_verified'=>(int)$user['blood_type_verified'],'new_source'=>$user['blood_type']===null?null:'self_reported','new_verified'=>false,'reason'=>'Invalid imported provenance; preserve blood type without claiming documentary verification.'],JSON_THROW_ON_ERROR)]);
        $p->commit();
    } catch (Throwable $e) { $p->rollBack(); throw $e; }
    foreach (triggerSnapshot($p) as $trigger) if ($trigger['Trigger']==='trg_force_fail') {
        $normalize=fn($s)=>preg_replace('/\s+/',' ',trim($s));
        $expected="BEGIN IF NEW.report_note LIKE '%FORCE_FAIL%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'forced test failure'; END IF; END";
        if ($trigger['Table']!=='donation_reports' || $trigger['Event']!=='UPDATE' || $trigger['Timing']!=='BEFORE' || $normalize($trigger['Statement'])!==$normalize($expected)) throw new LogicException('Unexpected test trigger definition; do not drop it automatically. Chat repair may already be committed.');
        $p->exec('DROP TRIGGER trg_force_fail');
    }
}
function repairChat(PDO $p): void {
    $nextIds=[];
    foreach (['conversations'=>'id','messages'=>'id','conversation_participants'=>'id'] as $table=>$key) {
        if (scalar($p,'SELECT COUNT(*) FROM '.ident($table).' WHERE id<=0')) throw new LogicException('Nonpositive imported chat IDs require manual review.');
        $s=$p->prepare('SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);
        $nextIds[$table]=max(scalar($p,'SELECT COALESCE(MAX(id),0)+1 FROM '.ident($table)),(int)$s->fetchColumn());
        if (scalar($p,'SELECT COUNT(*) FROM (SELECT '.ident($key).' FROM '.ident($table).' GROUP BY '.ident($key).' HAVING COUNT(*)>1) x')!==0) throw new LogicException('Duplicate IDs: stop without repair.');
    }
    if (scalar($p,'SELECT COUNT(*) FROM (SELECT conversation_id,user_id FROM conversation_participants GROUP BY conversation_id,user_id HAVING COUNT(*)>1) x')) throw new LogicException('Duplicate participant pairs: stop.');
    $bad=$p->query('SELECT DISTINCT cp.conversation_id FROM conversation_participants cp LEFT JOIN users u ON u.id=cp.user_id LEFT JOIN conversations c ON c.id=cp.conversation_id WHERE u.id IS NULL OR c.id IS NULL
        UNION SELECT DISTINCT m.conversation_id FROM messages m LEFT JOIN users u ON u.id=m.sender_id LEFT JOIN conversations c ON c.id=m.conversation_id WHERE u.id IS NULL OR c.id IS NULL
        UNION SELECT c.id FROM conversations c LEFT JOIN conversation_participants cp ON cp.conversation_id=c.id GROUP BY c.id HAVING COUNT(cp.id)<>2')->fetchAll(PDO::FETCH_COLUMN);
    $p->beginTransaction();
    try {
        foreach ($bad as $id) foreach (['messages'=>'conversation_id','conversation_participants'=>'conversation_id','conversations'=>'id'] as $table=>$column) {
            $p->prepare('DELETE FROM '.ident($table).' WHERE '.ident($column).'=?')->execute([$id]);
        }
        $p->commit();
    } catch (Throwable $e) { $p->rollBack(); throw $e; }
    // Backup preserves invalid imported conversations. DDL commits separately.
    foreach (['conversations','conversation_participants','messages'] as $table) {
        $indexes=$p->query('SHOW INDEX FROM '.ident($table))->fetchAll();
        if (!array_filter($indexes,fn($i)=>$i['Key_name']==='PRIMARY')) $p->exec('ALTER TABLE '.ident($table).' ADD PRIMARY KEY (id)');
        $p->exec('ALTER TABLE '.ident($table).' MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
        $p->exec('ALTER TABLE '.ident($table).' AUTO_INCREMENT='.$nextIds[$table]);
    }
    $p->exec('ALTER TABLE conversation_participants MODIFY last_read_at DATETIME(3) NULL');
    $indexes=[
        'conversations'=>['idx_conversations_updated'=>'INDEX idx_conversations_updated (updated_at)'],
        'conversation_participants'=>['uq_conversation_user'=>'UNIQUE KEY uq_conversation_user (conversation_id,user_id)','idx_participant_user'=>'INDEX idx_participant_user (user_id,last_read_at)'],
        'messages'=>['idx_conv_messages'=>'INDEX idx_conv_messages (conversation_id,id)','idx_messages_created'=>'INDEX idx_messages_created (created_at)'],
    ];
    foreach ($indexes as $table=>$wanted) {
        $existing=array_column($p->query('SHOW INDEX FROM '.ident($table))->fetchAll(),'Key_name');
        foreach ($wanted as $name=>$definition) if (!in_array($name,$existing,true)) $p->exec('ALTER TABLE '.ident($table).' ADD '.$definition);
    }
    foreach (['conversation_participants'=>['fk_part_conv'=>['conversation_id','conversations'],'fk_part_user'=>['user_id','users']],
        'messages'=>['fk_msg_conv'=>['conversation_id','conversations'],'fk_msg_sender'=>['sender_id','users']]] as $table=>$wanted) {
        $s=$p->prepare('SELECT COLUMN_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL');$s->execute([$table]);$existing=$s->fetchAll();
        foreach ($wanted as $name=>[$column,$reference]) if (!array_filter($existing,fn($r)=>$r['COLUMN_NAME']===$column && $r['REFERENCED_TABLE_NAME']===$reference))
            $p->exec('ALTER TABLE '.ident($table).' ADD CONSTRAINT '.ident($name).' FOREIGN KEY ('.ident($column).') REFERENCES '.ident($reference).'(id) ON DELETE CASCADE');
    }
    if (!chatSchemaReady($p)) throw new LogicException('Chat schema verification failed; inspect committed DDL before retrying.');
    $p->prepare('INSERT IGNORE INTO schema_migrations(name) VALUES (?)')->execute(['021_create_live_chat.sql']);
    $p->prepare('INSERT IGNORE INTO schema_migrations(name) VALUES (?)')->execute(['demo_maintenance:repair-chat:v1']);
}
function cleanDemo(PDO $p, array $review): string {
    if (!chatSchemaReady($p) || array_sum(chatIntegrity($p))!==0) throw new LogicException('Complete the import repair before account cleanup.');
    $keep=array_map(fn($r)=>(int)$r['id'],$review['retained_users']); $list=implode(',',$keep);
    $admin=array_values(array_filter($review['retained_users'],fn($r)=>$r['role']==='admin'));
    if (count($keep)!==15 || count($admin)!==1 || $admin[0]['email']!=='markseejr@gmail.com') throw new LogicException('Unexpected retained manifest.');
    $identity=$p->prepare('SELECT email,role FROM users WHERE id=?');
    foreach ($review['retained_users'] as $user) {
        $identity->execute([(int)$user['id']]);$current=$identity->fetch();
        if (!$current || $current['email']!==$user['email'] || $current['role']!==$user['role']) throw new LogicException('Retained account identity mismatch.');
    }
    foreach (['conversations','conversation_participants','messages'] as $table) {
        if (!array_filter($p->query('SHOW INDEX FROM '.ident($table))->fetchAll(),fn($i)=>$i['Key_name']==='PRIMARY')) throw new LogicException('Repair chat before account cleanup.');
    }
    $credentials=[];
    foreach ($review['retained_users'] as $r) $credentials[]=['id'=>(int)$r['id'],'email'=>$r['email'],'role'=>$r['role'],'password'=>'Demo-'.bin2hex(random_bytes(10))];
    // Save recoverable credentials before the transaction; failed applies are labelled uncommitted.
    $credentialPath=privateFile($review['database'].'-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'-credentials.json',json_encode(['status'=>'UNCOMMITTED','accounts'=>$credentials],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    $p->beginTransaction();
    try {
        // Delete complete conversations if either participant will be removed.
        $p->exec('DELETE c FROM conversations c JOIN conversation_participants cp ON cp.conversation_id=c.id WHERE cp.user_id NOT IN ('.$list.')');
        // This accounting table deliberately has no donor FK. Keep attempt history.
        $p->exec('DELETE FROM email_donor_reservations WHERE donor_id NOT IN ('.$list.')');
        $p->exec('DELETE FROM users WHERE id NOT IN ('.$list.')');
        $p->exec('DELETE FROM password_resets');
        $p->exec("DELETE FROM auth_throttle WHERE identifier LIKE 'login:%' OR identifier LIKE 'chat_send:%'");
        $p->exec("UPDATE email_outbox SET status='cancelled',cancel_reason='demo_cleanup',lease_token=NULL,lease_expires_at=NULL,updated_at=UTC_TIMESTAMP(6) WHERE status IN ('pending','leased')");
        $update=$p->prepare('UPDATE users SET password_hash=?,email_code_hash=NULL,email_code_expires_at=NULL WHERE id=?');
        foreach ($credentials as $r) $update->execute([password_hash($r['password'],PASSWORD_BCRYPT),$r['id']]);
        $p->prepare("INSERT INTO audit_log(actor_id,action,target_type,target_id,context) VALUES (?,'maintenance.demo.accounts_cleaned','system',NULL,?)")
            ->execute([(int)$admin[0]['id'],json_encode(['retained_ids'=>$keep,'removed_users'=>$review['remove_users'],'passwords_reset'=>true],JSON_THROW_ON_ERROR)]);
        if (scalar($p,'SELECT COUNT(*) FROM users')!==15 || scalar($p,"SELECT COUNT(*) FROM users WHERE role='admin'")!==1) throw new LogicException('Unexpected final account counts.');
        if (chatIntegrity($p)['orphan_participants'] || chatIntegrity($p)['orphan_messages']) throw new LogicException('Chat integrity failed.');
        $p->commit();
    } catch (Throwable $e) { if ($p->inTransaction()) $p->rollBack(); throw $e; }
    if (file_put_contents($credentialPath,json_encode(['status'=>'COMMITTED','accounts'=>$credentials],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new RuntimeException('Cleanup committed; credentials are present in the UNCOMMITTED-labelled file. Check the database before retrying.');
    return $credentialPath;
}

try {
    $args=getopt('',['target:','action:','apply','review:','sha256:','offline-ack']);
    $db=$args['target']??'bloodmatch_test';
    if (!in_array($db,['bloodmatch','bloodmatch_test'],true)) throw new LogicException('Only bloodmatch or bloodmatch_test supported.');
    $port=(int)($db==='bloodmatch_test'?Env::get('TEST_DB_PORT','3306'):Env::get('DB_PORT','3306'));
    $p=new PDO('mysql:host='.Env::get('DB_HOST','127.0.0.1').';port='.$port.';dbname='.$db.';charset=utf8mb4',Env::get('DB_USER','root'),Env::get('DB_PASS',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $actual=$p->query('SELECT DATABASE() db,@@port port')->fetch();
    if ($actual['db']!==$db || (int)$actual['port']!==$port) throw new LogicException('Resolved target mismatch; no fallback allowed.');
    $p->exec("SET time_zone='+00:00'");
    $action=$args['action']??'review';
    if ($action==='backup' && !isset($args['apply'])) { echo 'BACKUP '.backup($p,$db,$port).PHP_EOL; exit; }
    $state=['tables'=>snapshot($p),'triggers'=>triggerSnapshot($p)]; $fingerprint=hash('sha256',json_encode($state,JSON_THROW_ON_ERROR));
    if (!isset($args['apply'])) {
        if ($action!=='review') throw new LogicException('Apply actions require --apply and a reviewed fingerprint.');
        $retained=chooseUsers($p);$ids=implode(',',array_column($retained,'id'));
        $review=['database'=>$db,'port'=>$port,'fingerprint'=>$fingerprint,'tables'=>$state['tables'],'triggers'=>$state['triggers'],'chat_integrity'=>chatIntegrity($p),'retained_users'=>$retained,
            'remove_users'=>scalar($p,'SELECT COUNT(*) FROM users WHERE id NOT IN ('.$ids.')'),
            'remove_requests'=>scalar($p,'SELECT COUNT(*) FROM blood_requests WHERE requester_id NOT IN ('.$ids.')'),
            'remove_matches'=>scalar($p,'SELECT COUNT(*) FROM matches m JOIN blood_requests r ON r.id=m.request_id WHERE m.donor_id NOT IN ('.$ids.') OR r.requester_id NOT IN ('.$ids.')'),
            'remove_reports'=>scalar($p,'SELECT COUNT(*) FROM donation_reports d JOIN matches m ON m.id=d.match_id JOIN blood_requests r ON r.id=m.request_id WHERE d.donor_id NOT IN ('.$ids.') OR m.donor_id NOT IN ('.$ids.') OR r.requester_id NOT IN ('.$ids.')')];
        $file=privateFile($db.'-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.review.json',json_encode($review,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
        echo json_encode(['target'=>$actual,'review'=>$file,'sha256'=>hash_file('sha256',$file),'chat_schema_ready'=>chatSchemaReady($p),'integrity'=>$review['chat_integrity'],'retained_users'=>$retained,'remove_users'=>$review['remove_users'],'remove_requests'=>$review['remove_requests'],'remove_matches'=>$review['remove_matches'],'remove_reports'=>$review['remove_reports']],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
        exit;
    }
    if (!in_array($action,['repair-chat','repair-import','cleanup-demo'],true) || !isset($args['offline-ack'],$args['review'],$args['sha256'])) throw new LogicException('Apply requires action, --offline-ack, --review and --sha256. Stop web servers and workers first.');
    $reviewPath=realpath($args['review']);$privateRoot=realpath(dirname(__DIR__).'/backend/storage/maintenance');
    if ($reviewPath===false || $privateRoot===false || !str_starts_with(str_replace('\\','/',$reviewPath),str_replace('\\','/',$privateRoot).'/') || !hash_equals(hash_file('sha256',$reviewPath),$args['sha256'])) throw new LogicException('Review path/hash mismatch.');
    $review=json_decode(file_get_contents($reviewPath),true,512,JSON_THROW_ON_ERROR);
    if ($review['database']!==$db || $review['port']!==$port || !hash_equals($review['fingerprint'],$fingerprint)) throw new LogicException('Database changed since review; create a fresh review.');
    $backup=backup($p,$db,$port); echo 'BACKUP '.$backup.PHP_EOL;
    if (in_array($action,['repair-chat','repair-import'],true)) { repairChat($p); if ($action==='repair-import') repairImportArtifacts($p); }
    else echo 'PRIVATE_CREDENTIALS '.cleanDemo($p,$review).PHP_EOL;
    echo 'DONE '.$action.' '.json_encode(chatIntegrity($p)).PHP_EOL;
} catch (Throwable $e) { fwrite(STDERR,'STOP: '.$e->getMessage().PHP_EOL); exit(1); }
