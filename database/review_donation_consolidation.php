<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/backend/src/autoload.php';
require __DIR__.'/donation_consolidation_support.php';
$o=getopt('',['target:','apply','reviewed-sha:','backup-file:','backup-sha:','application-approved','writers-stopped','context-identity']);
try {
    $target=$o['target']??'test';$p=consolidationTarget($target);
    if(isset($o['context-identity'])) {
        $file=__DIR__.'/migrations/033_immutable_donation_context.sql';$name=basename($file);$sha=hash_file('sha256',$file);
        $s=$p->prepare('SELECT 1 FROM schema_migrations WHERE name=?');$s->execute(['032_consolidate_donation_storage.sql']);if(!$s->fetchColumn())throw new LogicException('032 required before context hardening.');
        $s->execute([$name]);$recorded=(bool)$s->fetchColumn();
        $result=['target'=>$target,'migration'=>$name,'sha256'=>$sha,'recorded'=>$recorded];
        if(isset($o['apply'])) {
            if($recorded)throw new LogicException('033 already recorded.');
            if(!isset($o['writers-stopped'])||($target==='application'&&!isset($o['application-approved'])))throw new LogicException('Writer fence and application approval required.');
            if((int)$p->query('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB=DATABASE() AND ID<>CONNECTION_ID()')->fetchColumn())throw new LogicException('Other target connections remain.');
            $backup=$o['backup-file']??'';
            consolidationBackup($backup,(string)($o['backup-sha']??''),(string)$p->query('SELECT DATABASE()')->fetchColumn());
            if(!hash_equals($sha,(string)($o['reviewed-sha']??'')))throw new LogicException('Exact reviewed migration SHA required.');
            foreach(preg_split('/^-- next statement\s*$/m',file_get_contents($file)) as $sql)if(trim($sql)!=='')$p->exec($sql);
            $p->prepare('INSERT INTO schema_migrations(name) VALUES(?)')->execute([$name]);
            $result['status']='context_identity_hardened';
        }else $result['status']=$recorded?'recorded':'prepared';
        echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;exit;
    }
    $file=__DIR__.'/migrations/032_consolidate_donation_storage.sql';$name=basename($file);$sha=hash_file('sha256',$file);
    $s=$p->prepare('SELECT 1 FROM schema_migrations WHERE name=?');$s->execute([$name]);$recorded=(bool)$s->fetchColumn();
    $partial=(int)$p->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='donation_reports' AND COLUMN_NAME='credit_recorded_at'")->fetchColumn()
        +(int)$p->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='compatibility_pairs'")->fetchColumn();
    $s->execute(['031_offer_chat_donation_proof.sql']);if(!$s->fetchColumn())throw new LogicException('Migration 031 is required.');
    if(!$recorded && $partial)throw new LogicException('Partial 032: stop and recover from the verified backup; no blind retry.');
    $checks=$recorded?[]:consolidationPreflight($p);
    $result=['target'=>$target,'database'=>$p->query('SELECT DATABASE()')->fetchColumn(),'port'=>3306,'migration'=>$name,'sha256'=>$sha,'recorded'=>$recorded,'preflight'=>$checks];
    if(isset($o['apply'])) {
        if($recorded)throw new LogicException('Already recorded; do not apply again.');
        if(array_sum($checks)!==0)throw new LogicException('Preflight failed; no data is repaired automatically.');
        if(!isset($o['writers-stopped']))throw new LogicException('Stop target writers and acknowledge --writers-stopped.');
        if((int)$p->query("SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB=DATABASE() AND ID<>CONNECTION_ID()")->fetchColumn()!==0)throw new LogicException('Other target connections remain; stop writers before applying.');
        if($target==='application'&&!isset($o['application-approved']))throw new LogicException('Application execution requires separate explicit user approval.');
        $backup=$o['backup-file']??'';
        consolidationBackup($backup,(string)($o['backup-sha']??''),$result['database']);
        if(!hash_equals($sha,(string)($o['reviewed-sha']??'')))throw new LogicException('Exact reviewed migration SHA required.');
        $columns=$p->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='donation_reports' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
        $before=[];foreach(['donation_reports','donation_credits','donation_report_holds','donation_report_events','donation_report_evidence','donation_correction_requests','compatibility_matrix'] as $table)$before[$table]=consolidationDigest($p,$table);
        foreach(preg_split('/^-- next statement\s*$/m',file_get_contents($file)) as $sql)if(trim($sql)!=='')$p->exec($sql);
        $after=[];foreach(array_keys($before) as $table)$after[$table]=consolidationDigest($p,$table,$table==='donation_reports'?$columns:null);
        if($before!==$after)throw new LogicException('Original-row preservation failed; leave maintenance in place and recover.');
        $reconcile=consolidationReconcile($p);
        if(array_sum($reconcile)!==0)throw new LogicException('Copy reconciliation failed; migration marker NOT recorded.');
        $p->prepare('INSERT INTO schema_migrations(name) VALUES(?)')->execute([$name]);
        $result+=['preservation'=>$after,'reconciliation'=>$reconcile,'backup_sha256'=>hash_file('sha256',$backup)];
        $result['status']='applied_sources_retained';
    } else $result['status']=$recorded?'recorded_new_storage_authoritative':(array_sum($checks)===0?'ready_for_reviewed_apply':'preflight_failed');
    echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Consolidation stopped: '.$e->getMessage().PHP_EOL);exit(1);}
