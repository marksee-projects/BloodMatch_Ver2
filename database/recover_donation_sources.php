<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/backend/src/autoload.php';require __DIR__.'/donation_consolidation_support.php';
$o=getopt('',['target:','rehearse','apply','application-approved','writers-stopped','backup-file:','backup-sha:']);
try{
    $target=$o['target']??'test';$p=consolidationTarget($target);
    $name='032_consolidate_donation_storage.sql';$s=$p->prepare('SELECT 1 FROM schema_migrations WHERE name=?');$s->execute([$name]);if(!$s->fetchColumn())throw new LogicException('Recovery requires a recorded 032 and retained sources.');
    if(isset($o['apply'])&&isset($o['rehearse']))throw new LogicException('Choose apply or rehearsal.');
    if(!isset($o['apply'])&&!isset($o['rehearse'])){echo "Prepared recovery: rebuild retained sources from current authoritative reports/pairs, reconcile, then remove only the 032 marker. No schema/data changed.\n";exit;}
    if($target==='application'&&isset($o['rehearse']))throw new LogicException('Recovery rehearsal is test-only.');
    if(isset($o['apply'])){
        if(!isset($o['writers-stopped'])||($target==='application'&&!isset($o['application-approved'])))throw new LogicException('Writer fence and explicit application approval required.');
        if((int)$p->query('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB=DATABASE() AND ID<>CONNECTION_ID()')->fetchColumn())throw new LogicException('Other target connections remain.');
        consolidationBackup($o['backup-file']??'',(string)($o['backup-sha']??''),(string)$p->query('SELECT DATABASE()')->fetchColumn());
    }
    $p->beginTransaction();
    $p->exec('INSERT INTO donation_credits(report_id,match_id,donor_id,occurred_at,recorded_at,completion_basis) SELECT id,match_id,donor_id,occurred_at,credit_recorded_at,completion_basis FROM donation_reports WHERE credit_recorded_at IS NOT NULL ON DUPLICATE KEY UPDATE match_id=VALUES(match_id),donor_id=VALUES(donor_id),occurred_at=VALUES(occurred_at),recorded_at=VALUES(recorded_at),completion_basis=VALUES(completion_basis)');
    $p->exec('INSERT INTO donation_report_holds(report_id,donor_id,started_at,released_at,released_by,release_basis,release_type) SELECT id,donor_id,hold_started_at,hold_released_at,hold_released_by,hold_release_basis,hold_release_type FROM donation_reports WHERE hold_started_at IS NOT NULL ON DUPLICATE KEY UPDATE donor_id=VALUES(donor_id),started_at=VALUES(started_at),released_at=VALUES(released_at),released_by=VALUES(released_by),release_basis=VALUES(release_basis),release_type=VALUES(release_type)');
    $p->exec("INSERT INTO compatibility_matrix(recipient_type,allowed_donor_types) SELECT recipient_type,GROUP_CONCAT(donor_type ORDER BY preference_order SEPARATOR ',') FROM compatibility_pairs GROUP BY recipient_type ON DUPLICATE KEY UPDATE allowed_donor_types=VALUES(allowed_donor_types)");
    $checks=consolidationReconcile($p)+consolidationPreflight($p);
    if(array_sum($checks)!==0)throw new LogicException('Recovery reconciliation failed; transaction rolled back.');
    if(isset($o['rehearse'])){$p->rollBack();$status='rehearsed_and_rolled_back';}
    else{$p->prepare('DELETE FROM schema_migrations WHERE name=?')->execute([$name]);$p->commit();$status='sources_reconciled_legacy_storage_enabled';}
    echo json_encode(['target'=>$target,'database'=>$p->query('SELECT DATABASE()')->fetchColumn(),'port'=>3306,'status'=>$status,'checks'=>$checks],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){if(isset($p)&&$p->inTransaction())$p->rollBack();fwrite(STDERR,'Recovery stopped: '.$e->getMessage().PHP_EOL);exit(1);}
