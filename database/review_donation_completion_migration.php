<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require dirname(__DIR__).'/backend/src/autoload.php';
require __DIR__.'/operational_checkpoint_support.php';
$options=getopt('',['target:','apply','reviewed-sha:','backup-confirmed']);
try {
    $target=$options['target']??'test';$p=operationalTarget($target);
    $file=__DIR__.'/migrations/030_donation_completion_workflow.sql';$sha=hash_file('sha256',$file);
    $s=$p->prepare('SELECT 1 FROM schema_migrations WHERE name=?');$s->execute([basename($file)]);$applied=$s->fetchColumn()!==false;
    $newTables=(int)$p->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('donation_report_events','donation_report_holds','donation_credits')")->fetchColumn();
    $newColumns=(int)$p->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='donation_reports' AND COLUMN_NAME='occurred_at') OR (TABLE_NAME='matches' AND COLUMN_NAME='responded_at'))")->fetchColumn();
    $result=['target'=>$p->query('SELECT DATABASE() AS database_name,@@port AS port')->fetch(),'migration'=>basename($file),'sha256'=>$sha,'recorded'=>$applied,'new_tables'=>$newTables,'new_columns'=>$newColumns,'writes'=>isset($options['apply'])];
    if (isset($options['apply'])) {
        if ($applied) throw new LogicException('Already recorded; do not rerun this migration.');
        if ($newTables!==0 || $newColumns!==0) throw new LogicException('Partial schema detected. Restore/review the backup; do not blindly retry auto-committed DDL.');
        if (!isset($options['backup-confirmed']) || !hash_equals($sha,(string)($options['reviewed-sha']??''))) throw new LogicException('Provide the reviewed SHA and confirm an external backup first.');
        // Stop HTTP/task writers yourself before invoking this explicit apply mode.
        foreach(explode(';',file_get_contents($file)) as $statement) if (trim($statement)!=='') $p->exec($statement);
        $p->prepare('INSERT INTO schema_migrations(name) VALUES(?)')->execute([basename($file)]);
        $result['status']='applied';
    } else $result['status']=$applied && $newTables===3 && $newColumns===2?'ready':($newTables || $newColumns?'review_partial_schema':'prepared_not_applied');
    echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
} catch(Throwable $e) { fwrite(STDERR,'Donation migration stopped: '.$e->getMessage().PHP_EOL);exit(1); }
