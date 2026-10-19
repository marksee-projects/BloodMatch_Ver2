<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/backend/src/autoload.php';require __DIR__.'/operational_checkpoint_support.php';
use BloodMatch\Services\DocumentCipher;
$options=getopt('',['target:','apply','reviewed-sha:','backup-confirmed']);
try {
    $p=operationalTarget($options['target']??'test');$file=__DIR__.'/migrations/031_offer_chat_donation_proof.sql';$name=basename($file);$sha=hash_file('sha256',$file);
    $s=$p->prepare('SELECT 1 FROM schema_migrations WHERE name=?');$s->execute([$name]);$recorded=$s->fetchColumn()!==false;
    $s->execute(['030_donation_completion_workflow.sql']);if(!$s->fetchColumn())throw new LogicException('Apply/review migration 030 first.');
    $tables=(int)$p->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('donation_chat_contexts','donation_coordination_events','donation_report_evidence','donation_correction_requests')")->fetchColumn();
    $columns=(int)$p->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND ((TABLE_NAME='matches' AND COLUMN_NAME='offer_cycle') OR (TABLE_NAME='donation_reports' AND COLUMN_NAME='offer_context_id'))")->fetchColumn();
    $indexes=(int)$p->query("SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='donation_credits' AND INDEX_NAME='uq_credit_donor_occurrence'")->fetchColumn();
    $duplicates=(int)$p->query('SELECT COUNT(*) FROM (SELECT donor_id,occurred_at FROM donation_credits GROUP BY donor_id,occurred_at HAVING COUNT(*)>1) duplicate_credit_groups')->fetchColumn();
    $keyReady=false;try { $keyReady=DocumentCipher::mode()!=='legacy'&&strlen(DocumentCipher::key(DocumentCipher::activeId()))===32; }catch(Throwable){}
    $result=['target'=>$p->query('SELECT DATABASE() AS database_name,@@port AS port')->fetch(),'migration'=>$name,'sha256'=>$sha,'recorded'=>$recorded,'new_tables'=>$tables,'new_columns'=>$columns,'new_indexes'=>$indexes,'duplicate_credit_groups'=>$duplicates,'writes'=>isset($options['apply']),'photo_processing'=>['gd'=>extension_loaded('gd'),'encrypted_key_ready'=>$keyReady,'upload_max_filesize'=>ini_get('upload_max_filesize'),'post_max_size'=>ini_get('post_max_size')]];
    if(isset($options['apply'])) {
        if($recorded || $tables || $columns || $indexes)throw new LogicException('Already applied or partial schema: inspect the backup; no blind retry supported.');
        if($duplicates)throw new LogicException('Existing duplicate occurrence credits require explicit review; no automatic repair/backfill.');
        if(!isset($options['backup-confirmed'])||!hash_equals($sha,(string)($options['reviewed-sha']??'')))throw new LogicException('Confirm the recoverable external backup and exact reviewed SHA.');
        foreach(explode(';',file_get_contents($file)) as $statement)if(trim($statement)!=='')$p->exec($statement);
        $p->prepare('INSERT INTO schema_migrations(name) VALUES(?)')->execute([$name]);$result['status']='applied';
    }else $result['status']=$recorded&&$tables===4&&$columns===2&&$indexes===1?'ready':($tables||$columns||$indexes?'review_partial_schema':'prepared_not_applied');
    echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Offer/chat migration stopped: '.$e->getMessage().PHP_EOL);exit(1);}
