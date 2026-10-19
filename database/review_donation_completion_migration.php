<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require dirname(__DIR__).'/backend/src/autoload.php';
require __DIR__.'/operational_checkpoint_support.php';
$options=getopt('',['target:','apply','diagnose','resume-response-column','reviewed-sha:','backup-confirmed']);
try {
    if (isset($options['diagnose'],$options['apply'])) throw new LogicException('Diagnosis is read-only; do not combine --diagnose with --apply.');
    if (isset($options['resume-response-column']) && !isset($options['apply'])) throw new LogicException('Resume requires explicit --apply and the backup/SHA confirmations.');
    $target=$options['target']??'test';$p=operationalTarget($target);
    $file=__DIR__.'/migrations/030_donation_completion_workflow.sql';$sha=hash_file('sha256',$file);
    $s=$p->prepare('SELECT 1 FROM schema_migrations WHERE name=?');$s->execute([basename($file)]);$applied=$s->fetchColumn()!==false;
    $newTables=(int)$p->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('donation_report_events','donation_report_holds','donation_credits')")->fetchColumn();
    $newColumns=(int)$p->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='donation_reports' AND COLUMN_NAME='occurred_at') OR (TABLE_NAME='matches' AND COLUMN_NAME='responded_at'))")->fetchColumn();
    $result=['target'=>$p->query('SELECT DATABASE() AS database_name,@@port AS port')->fetch(),'migration'=>basename($file),'sha256'=>$sha,'recorded'=>$applied,'new_tables'=>$newTables,'new_columns'=>$newColumns,'writes'=>isset($options['apply'])];
    $reportColumns=(int)$p->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='donation_reports' AND COLUMN_NAME IN ('occurred_at','content_version','workflow_state','requester_response','acknowledged_by','acknowledged_at','acknowledged_version','reviewed_at','completion_basis','reminder_sent_at','escalated_at')")->fetchColumn();
    $responseColumn=$p->query("SELECT DATA_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='matches' AND COLUMN_NAME='responded_at'")->fetch(PDO::FETCH_ASSOC);
    $resumeEligible=$target==='test' && !$applied && $newTables===0 && $newColumns===1 && $reportColumns===0
        && $responseColumn && $responseColumn['DATA_TYPE']==='datetime' && $responseColumn['IS_NULLABLE']==='YES'
        && in_array($responseColumn['COLUMN_DEFAULT'],[null,'NULL'],true) && $responseColumn['EXTRA']==='';
    $result['response_column_resume_eligible']=(bool)$resumeEligible;
    if (isset($options['diagnose'])) {
        $result['columns']=$p->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='matches' AND COLUMN_NAME IN ('id','request_id','donor_id','responded_at')) OR (TABLE_NAME='audit_log' AND COLUMN_NAME IN ('action','actor_id','target_type','target_id','context','created_at'))) ORDER BY TABLE_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
        // Evaluate the migration's backfill expression using SELECT only. No DDL,
        // row updates, migration ledger changes, or statement retries are allowed.
        try {
            $s=$p->query("SELECT m.id,(SELECT MAX(a.created_at) FROM audit_log a WHERE a.action='match.responded' AND a.actor_id=m.donor_id AND a.target_type='blood_request' AND a.target_id=CAST(m.request_id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci AND CONVERT(JSON_UNQUOTE(JSON_EXTRACT(a.context,'$.match_id')) USING utf8mb4) COLLATE utf8mb4_unicode_ci=CAST(m.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci) AS recovered_response FROM matches m");
            $rows=0;while ($s->fetch(PDO::FETCH_NUM)!==false) $rows++;
            $result['backfill_probe']=['status'=>'passed','rows_checked'=>$rows];
        } catch (PDOException $e) {
            $result['backfill_probe']=['status'=>'failed','error'=>$e->getMessage()];
        }
        $result['status']='read_only_diagnosis';
        echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
        exit;
    }
    if (isset($options['apply'])) {
        if ($applied) throw new LogicException('Already recorded; do not rerun this migration.');
        $resume=isset($options['resume-response-column']);
        if ($resume && !$resumeEligible) throw new LogicException('Resume refused: only the diagnosed test-target responded_at-only partial state is supported.');
        if (!$resume && ($newTables!==0 || $newColumns!==0 || $reportColumns!==0)) throw new LogicException('Partial schema detected. Restore/review the backup; do not blindly retry auto-committed DDL.');
        if (!isset($options['backup-confirmed']) || !hash_equals($sha,(string)($options['reviewed-sha']??''))) throw new LogicException('Provide the reviewed SHA and confirm an external backup first.');
        // Stop HTTP/task writers yourself before invoking this explicit apply mode.
        $statements=explode(';',file_get_contents($file));
        if ($resume) {
            $first=trim(preg_replace('/^\s*--[^\r\n]*(?:\r?\n|$)/m','',$statements[0]));
            if ($first!=='ALTER TABLE matches ADD COLUMN responded_at DATETIME NULL') throw new LogicException('Resume refused: migration opening statement changed.');
            array_shift($statements); // Skip only the already committed column addition.
        }
        foreach($statements as $statement) if (trim($statement)!=='') $p->exec($statement);
        $p->prepare('INSERT INTO schema_migrations(name) VALUES(?)')->execute([basename($file)]);
        $result['status']='applied';
    } else $result['status']=$applied && $newTables===3 && $newColumns===2?'ready':($newTables || $newColumns?'review_partial_schema':'prepared_not_applied');
    echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $e) { fwrite(STDERR,'Donation migration stopped: '.$e->getMessage().PHP_EOL);exit(1); }
