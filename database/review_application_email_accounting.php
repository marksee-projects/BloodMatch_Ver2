<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403);exit; }
require dirname(__DIR__).'/backend/src/autoload.php';
require __DIR__.'/email_checkpoint_support.php';

// Application-target proposal. Default SELECT-only. Agent must not apply this.
// No general migration runner, 020/022, backfill, worker, SMTP or environment-file edits.
use BloodMatch\Config\Database;
use BloodMatch\Config\Env;

function applicationEmailSnapshot(PDO $pdo): array
{
    $result=[];
    foreach (['notifications','donation_reports'] as $table) {
        // No dependency on the optional 020 rejection column. Raw rows never leave memory.
        $s=$pdo->query("SELECT * FROM $table ORDER BY id");$hash=hash_init('sha256');$count=0;
        while ($row=$s->fetch(PDO::FETCH_NUM)) { hash_update($hash,serialize($row));$count++; }
        $result[$table]=['count'=>$count,'digest'=>hash_final($hash)];
    }
    return $result;
}

try {
    $apply=false;$approvedHash=null;
    foreach (array_slice($argv,1) as $arg) {
        if ($arg==='--apply'&&!$apply) { $apply=true; }
        elseif (preg_match('/^--sql-sha256=([0-9a-f]{64})$/D',$arg,$m)&&$approvedHash===null) { $approvedHash=$m[1]; }
        else { throw new LogicException('Use no arguments for review; application requires --apply --sql-sha256=REVIEWED_HASH.'); }
    }
    if (!$apply&&$approvedHash!==null) { throw new LogicException('Review does not require a checksum argument.'); }
    foreach (['DB_HOST'=>'127.0.0.1','DB_PORT'=>'3306','DB_NAME'=>'bloodmatch'] as $key=>$value) {
        putenv($key.'='.$value);$_ENV[$key]=$value;
    }
    Env::load(dirname(__DIR__).'/.env');
    $pdo=Database::pdo();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn()!=='bloodmatch'||(int)$pdo->query('SELECT @@port')->fetchColumn()!==3306) {
        throw new LogicException('Refused: actual target must be bloodmatch:3306; fallback is not permitted.');
    }
    $name='021_email_outbox_accounting.sql';$sql=file_get_contents(__DIR__.'/migrations/'.$name);
    if ($sql===false||trim($sql)==='') { throw new LogicException('Migration file missing.'); }
    $checksum=hash('sha256',$sql);$shape=emailCheckpointShape($pdo);
    $ledgerExists=(bool)$pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='schema_migrations'")->fetchColumn();
    $recorded=false;
    if ($ledgerExists) {
        $s=$pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE name=?');$s->execute([$name]);$entries=(int)$s->fetchColumn();
        if ($entries>1) { throw new LogicException('Duplicate migration ledger entries; no repair attempted.'); }
        $recorded=$entries===1;
    }
    $s=$pdo->query("SELECT COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='id'");
    $parent=$s->fetch(PDO::FETCH_ASSOC);
    $parentReady=$parent!==false&&preg_replace('/^bigint\(\d+\)/','bigint',$parent['COLUMN_TYPE'])==='bigint unsigned'&&$parent['IS_NULLABLE']==='NO';
    $prerequisitesReady=$ledgerExists&&$parentReady;
    foreach (['notifications','donation_reports'] as $table) {
        $s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);
        if ((int)$s->fetchColumn()!==1) { $prerequisitesReady=false; }
    }
    echo json_encode(['database'=>'bloodmatch','host'=>'127.0.0.1','port'=>3306,'mode'=>$apply?'apply':'review','shape'=>$shape,
        'migration'=>$name,'recorded'=>$recorded,'ledger_present'=>$ledgerExists,'parent_notification_id_ready'=>$parentReady,
        'prerequisites_ready'=>$prerequisitesReady,'sql_sha256'=>$checksum],JSON_THROW_ON_ERROR).PHP_EOL;
    if (in_array('drift',$shape,true)||($recorded&&in_array('missing',$shape,true))) { throw new LogicException('Schema/ledger drift; no drop, conversion or repair attempted.'); }
    $statements=[];
    foreach (preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[] as $statement) {
        $statement=trim(preg_replace('/^\s*--.*$/m','',$statement)??'');
        if ($statement==='') { continue; }
        if (!preg_match('/^CREATE TABLE (email_outbox|email_attempts) \(/',$statement,$match)||isset($statements[$match[1]])) { throw new LogicException('Unexpected migration statement.'); }
        $statements[$match[1]]=$statement;
    }
    if (array_keys($statements)!==['email_outbox','email_attempts']) { throw new LogicException('Expected exactly the two approved 021 CREATE statements.'); }
    if (!$apply) {
        echo $sql.PHP_EOL."READ-ONLY REVIEW; no writes or SMTP. Application requires separate approval for bloodmatch:3306.\n";
        exit(0);
    }
    if ($approvedHash===null||!hash_equals($checksum,$approvedHash)) { throw new LogicException('Reviewed checksum required; no application performed.'); }
    if (!$prerequisitesReady) { throw new LogicException('Application prerequisites missing; no extra migration/ledger creation authorized.'); }
    // USER ONLY AFTER APPROVAL: stop app/mail/database writers and take a backup.
    // DDL auto-commits; valid partial tables remain for review, never dropped/rebuilt.
    $before=applicationEmailSnapshot($pdo);
    foreach ($statements as $table=>$statement) {
        if ($shape[$table]!=='missing') { continue; }
        $pdo->exec($statement);
        if ((int)$pdo->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()!==0) { throw new LogicException('New table contains data; stop writers and inspect.'); }
    }
    if (emailCheckpointShape($pdo)!==['email_outbox'=>'ready','email_attempts'=>'ready']) { throw new LogicException('Shape verification failed; migration ledger not changed.'); }
    if (applicationEmailSnapshot($pdo)!==$before) { throw new LogicException('Existing notifications/reports changed; stop writers and inspect.'); }
    if (!$recorded) { $pdo->prepare('INSERT INTO schema_migrations(name) VALUES (?)')->execute([$name]); }
    echo "021 ready/recorded on bloodmatch:3306; existing notifications/reports unchanged; no backfill or other migration.\n";
} catch (Throwable $e) {
    fwrite(STDERR,$e instanceof LogicException?$e->getMessage().PHP_EOL:"Application accounting review/application failed; raw errors withheld. No reset performed.\n");exit(1);
}
