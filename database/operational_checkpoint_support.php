<?php
declare(strict_types=1);
require_once __DIR__.'/email_checkpoint_support.php';
require_once __DIR__.'/non_response_checkpoint_support.php';
function operationalTarget(string $target): PDO
{
    $database=match($target) {'test'=>'bloodmatch_test','application'=>'bloodmatch',default=>throw new LogicException('Invalid fixed target.')};
    foreach (['DB_HOST'=>'127.0.0.1','DB_NAME'=>$database,'DB_PORT'=>'3306'] as $key=>$value) { putenv($key.'='.$value);$_ENV[$key]=$value; }
    \BloodMatch\Config\Env::load(dirname(__DIR__).'/.env');
    $pdo=\BloodMatch\Config\Database::pdo();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn()!==$database || (int)$pdo->query('SELECT @@port')->fetchColumn()!==3306) { throw new LogicException('Fixed target mismatch; fallback refused.'); }
    return $pdo;
}
function donorReservationShape(PDO $pdo): string
{
    $table=$pdo->query("SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_donor_reservations'")->fetch(PDO::FETCH_ASSOC);
    if (!$table) { return 'missing'; }
    if ($table['ENGINE']!=='InnoDB' || $table['TABLE_COLLATION']!=='utf8mb4_unicode_ci') { throw new LogicException('023 table drift.'); }
    $rows=$pdo->query("SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_donor_reservations' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
    $expected=['attempt_id'=>['bigint unsigned','NO',null],'donor_id'=>['bigint unsigned','NO',null],'reserved_at'=>['datetime(6)','NO',null],
        'outcome'=>["enum('outstanding','accepted','uncertain','released')",'NO','outstanding'],'updated_at'=>['datetime(6)','NO',null]];
    $actual=[];
    foreach ($rows as $row) {
        if ($row['EXTRA']!=='') { throw new LogicException('023 extra column drift.'); }
        $default=$row['COLUMN_DEFAULT'];$default=$default===null || strtoupper((string)$default)==='NULL' ? null : trim($default,"'");
        $actual[$row['COLUMN_NAME']]=[preg_replace('/^bigint\(\d+\)/','bigint',$row['COLUMN_TYPE']),$row['IS_NULLABLE'],$default];
    }
    if ($actual!==$expected) { throw new LogicException('023 column drift.'); }
    $indexes=$pdo->query("SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='email_donor_reservations' GROUP BY INDEX_NAME,NON_UNIQUE")->fetchAll(PDO::FETCH_ASSOC);
    $actual=[];foreach ($indexes as $row) { $actual[$row['INDEX_NAME']]=[(int)$row['NON_UNIQUE'],$row['cols']]; }
    if (($actual['PRIMARY']??null)!==[0,'attempt_id'] || ($actual['idx_donor_reservation_window']??null)!==[1,'donor_id,reserved_at,outcome']) { throw new LogicException('023 index drift.'); }
    $fk=$pdo->query("SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME='email_donor_reservations'")->fetchAll(PDO::FETCH_NUM);
    if (count($fk)!==1 || $fk[0]!==['attempt_id','email_attempts','id','RESTRICT']) { throw new LogicException('023 FK drift.'); }
    return 'ready';
}
function operationalReadiness(PDO $pdo, bool $requireDonor=true): array
{
    $shape=emailCheckpointShape($pdo)+nonResponseShape($pdo);
    $shape['donation_report_rejection_reason']=rejectionReasonShape($pdo);
    if ($requireDonor) { $shape['email_donor_reservations']=donorReservationShape($pdo); }
    $ledger=$pdo->query("SELECT name FROM schema_migrations WHERE name IN ('020_donation_report_rejection_reason.sql','021_email_outbox_accounting.sql','022_non_response_opportunities.sql','023_email_donor_reservations.sql') ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    $required=['020_donation_report_rejection_reason.sql','021_email_outbox_accounting.sql','022_non_response_opportunities.sql'];
    if ($requireDonor) { $required[]='023_email_donor_reservations.sql'; }
    return ['shape'=>$shape,'recorded'=>$ledger,'ready'=>!in_array('missing',$shape,true) && array_diff($required,$ledger)===[]];
}
function rejectionReasonShape(PDO $pdo): string
{
    $column=$pdo->query("SELECT COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='donation_reports' AND COLUMN_NAME='rejection_reason'")->fetch(PDO::FETCH_NUM);
    if ($column!==false && $column!==['varchar(500)','YES']) { throw new LogicException('020 rejection-reason column drift.'); }
    return $column===false?'missing':'ready';
}
/** Read-only full-row preservation evidence; no report contents are emitted. */
function rejectionReportSnapshot(PDO $pdo, bool $hasReason): array
{
    $hash=hash_init('sha256');$count=0;$statuses=[];$reasons=$hasReason?0:null;
    $rows=$pdo->query('SELECT * FROM donation_reports ORDER BY id');
    while ($row=$rows->fetch(PDO::FETCH_ASSOC)) {
        if ($hasReason && $row['rejection_reason']!==null) { $reasons++; }
        unset($row['rejection_reason']);
        hash_update($hash,json_encode($row,JSON_THROW_ON_ERROR)."\n");$count++;
        $statuses[$row['status']]=($statuses[$row['status']]??0)+1;
    }
    ksort($statuses);
    return ['report_count'=>$count,'status_counts'=>$statuses,'existing_fields_sha256'=>hash_final($hash),'reports_with_rejection_reason'=>$reasons];
}
