<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/backend/src/autoload.php';require __DIR__.'/donation_consolidation_support.php';
$o=getopt('',['target:','apply','application-approved','writers-stopped','backup-file:','backup-sha:','reviewed-sha:','cutover-evidence:']);
try{
    $target=$o['target']??'test';$p=consolidationTarget($target);$name='035_retire_donation_sources.sql';$file=__DIR__.'/migrations/'.$name;$sha=hash_file('sha256',$file);
    $sources=['donation_credits','donation_report_holds','compatibility_matrix'];
    $ledger=$p->query('SELECT name FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
    if(array_diff(['032_consolidate_donation_storage.sql','033_immutable_donation_context.sql'],$ledger))throw new LogicException('032 and 033 must be recorded.');
    $tables=$p->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    $present=array_intersect($sources,$tables);$recorded=in_array($name,$ledger,true);
    if($recorded&&count($present)===0){echo json_encode(['target'=>$target,'status'=>'retired','physical_tables'=>count($tables)]),PHP_EOL;exit;}
    if($recorded||count($present)!==3)throw new LogicException('Partial or inconsistent retirement; stop and inspect backup, no blind retry.');
    $deps=[];
    $rows=$p->query("SELECT TABLE_NAME,CONSTRAINT_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IN ('donation_credits','donation_report_holds','compatibility_matrix')")->fetchAll();foreach($rows as $r)$deps[]=$r['TABLE_NAME'].':'.$r['CONSTRAINT_NAME'];
    foreach([['VIEWS','TABLE_NAME','VIEW_DEFINITION'],['TRIGGERS','TRIGGER_NAME','ACTION_STATEMENT'],['ROUTINES','ROUTINE_NAME','ROUTINE_DEFINITION'],['EVENTS','EVENT_NAME','EVENT_DEFINITION']] as [$catalog,$key,$body]){
        $schemaColumn=match($catalog){'VIEWS'=>'TABLE_SCHEMA','TRIGGERS'=>'TRIGGER_SCHEMA','ROUTINES'=>'ROUTINE_SCHEMA','EVENTS'=>'EVENT_SCHEMA'};
        foreach($p->query("SELECT $key AS object_name,$body AS definition FROM information_schema.$catalog WHERE $schemaColumn=DATABASE()") as $r)if(preg_match('/\b(donation_credits|donation_report_holds|compatibility_matrix)\b/i',(string)$r['definition']))$deps[]=$catalog.':'.$r['object_name'];
    }
    if($deps)throw new LogicException('Active database object dependencies: '.implode(',',$deps));
    $checks=[
      'source_credit_not_preserved'=>(int)$p->query("SELECT COUNT(*) FROM donation_credits c LEFT JOIN donation_reports d ON d.id=c.report_id WHERE d.id IS NULL OR d.match_id<>c.match_id OR d.donor_id<>c.donor_id OR NOT(d.occurred_at<=>c.occurred_at) OR NOT(d.credit_recorded_at<=>c.recorded_at) OR NOT(d.completion_basis<=>c.completion_basis)")->fetchColumn(),
      'source_hold_identity_not_preserved'=>(int)$p->query('SELECT COUNT(*) FROM donation_report_holds h LEFT JOIN donation_reports d ON d.id=h.report_id WHERE d.id IS NULL OR d.donor_id<>h.donor_id OR NOT(d.hold_started_at<=>h.started_at)')->fetchColumn(),
      'invalid_current_credit'=>(int)$p->query("SELECT COUNT(*) FROM donation_reports WHERE credit_recorded_at IS NOT NULL AND (status<>'CONFIRMED' OR occurred_at IS NULL OR completion_basis IS NULL OR completion_basis NOT IN ('mutual','admin'))")->fetchColumn(),
      'context_mismatch'=>(int)$p->query('SELECT COUNT(*) FROM donation_reports d JOIN donation_chat_contexts c ON c.id=d.offer_context_id WHERE d.match_id<>c.match_id')->fetchColumn(),
    ];
    $old=$p->query('SELECT recipient_type,allowed_donor_types FROM compatibility_matrix ORDER BY recipient_type')->fetchAll(PDO::FETCH_KEY_PAIR);
    $new=[];foreach($p->query('SELECT recipient_type,donor_type FROM compatibility_pairs ORDER BY CAST(recipient_type AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci,preference_order') as $r)$new[$r['recipient_type']][]=$r['donor_type'];
    $expected=[];foreach($old as $recipient=>$list)$expected[$recipient]=array_map('trim',explode(',',$list));$checks['ordered_compatibility_mismatch']=$expected===$new?0:1;
    if(array_sum($checks)!==0)throw new LogicException('Preservation/invariant checks failed; no automatic repair.');
    $sourceDigests=[];foreach($sources as $table)$sourceDigests[$table]=consolidationDigest($p,$table);
    if($target==='application'){
        $evidence=$o['cutover-evidence']??dirname(__DIR__).'/backend/storage/maintenance/application-consolidation032-result.json';
        $raw=file_get_contents($evidence);if(str_starts_with($raw,"\xEF\xBB\xBF"))$raw=substr($raw,3);
        $cutover=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        if(($cutover['database']??null)!=='bloodmatch'||($cutover['status']??null)!=='applied_sources_retained')throw new LogicException('Verified main cutover evidence required.');
        foreach($sources as $table)if(($cutover['preservation'][$table]??null)!==$sourceDigests[$table])throw new LogicException('Source rows changed since cutover; investigate old writers/retention before retirement.');
    }
    $result=['target'=>$target,'database'=>$p->query('SELECT DATABASE()')->fetchColumn(),'migration'=>$name,'sha256'=>$sha,'database_dependencies'=>0,'checks'=>$checks,'source_digests'=>$sourceDigests,'status'=>'ready_for_retirement'];
    if(isset($o['apply'])){
        if(!isset($o['writers-stopped'])||($target==='application'&&!isset($o['application-approved'])))throw new LogicException('Explicit approval and stopped writers required.');
        if($target==='application'&&!is_file(dirname(__DIR__).'/backend/storage/maintenance/website.lock'))throw new LogicException('HTTP maintenance fence required.');
        if((int)$p->query('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB=DATABASE() AND ID<>CONNECTION_ID()')->fetchColumn())throw new LogicException('Other target connections remain.');
        consolidationBackup($o['backup-file']??'',(string)($o['backup-sha']??''),$result['database']);
        if(!hash_equals($sha,(string)($o['reviewed-sha']??'')))throw new LogicException('Reviewed retirement SHA required.');
        $remaining=array_values(array_diff($tables,array_merge($sources,['schema_migrations'])));$before=retirementSnapshot($p,$remaining);
        $p->exec(file_get_contents($file));
        if($before!==retirementSnapshot($p,$remaining))throw new LogicException('Remaining data changed: keep maintenance and recover.');
        $p->prepare('INSERT INTO schema_migrations(name) VALUES(?)')->execute([$name]);
        $result+=['remaining_tables_verified'=>count($remaining),'remaining_rows_preserved'=>true,'backup_sha256'=>hash_file('sha256',$o['backup-file'])];
        $result['status']='retired';$result['physical_tables']=count($tables)-3;
    }
    echo json_encode($result,JSON_THROW_ON_ERROR),PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Retirement stopped: '.$e->getMessage().PHP_EOL);exit(1);}
function retirementSnapshot(PDO $p,array $tables):array{
    $result=[];foreach($tables as $table){
        if(!preg_match('/^[a-z_]+$/D',$table))throw new LogicException('Invalid table identifier.');
        $s=$p->prepare("SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND CONSTRAINT_NAME='PRIMARY' ORDER BY ORDINAL_POSITION");$s->execute([$table]);$keys=$s->fetchAll(PDO::FETCH_COLUMN);if(!$keys)throw new LogicException('Primary key required for preservation.');
        $rows=$p->query('SELECT * FROM `'.$table.'` ORDER BY '.implode(',',array_map(fn($key)=>'`'.$key.'`',$keys)));$h=hash_init('sha256');$n=0;
        while($r=$rows->fetch(PDO::FETCH_ASSOC)){hash_update($h,json_encode($r,JSON_THROW_ON_ERROR)."\n");$n++;}$result[$table]=['rows'=>$n,'sha256'=>hash_final($h)];
    }return $result;
}
