<?php
declare(strict_types=1);
// USER-RUN ONLY. Repair only the absent 028 prerequisite on bloodmatch_test:3306.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/backend/src/autoload.php';
require __DIR__.'/operational_checkpoint_support.php';
$options=getopt('', ['apply','reviewed-sha:','backup-confirmed']);
try {
    $p=operationalTarget('test');
    $name='028_user_presence.sql';$file=__DIR__.'/migrations/'.$name;$sha=hash_file('sha256',$file);
    $s=$p->prepare('SELECT 1 FROM schema_migrations WHERE name=?');$s->execute([$name]);$recorded=$s->fetchColumn()!==false;
    $exists=(bool)$p->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_presence'")->fetchColumn();
    $result=['target'=>$p->query('SELECT DATABASE() AS database_name,@@port AS port')->fetch(),'migration'=>$name,'sha256'=>$sha,'recorded'=>$recorded,'table_exists'=>$exists,'writes'=>false];
    if (isset($options['apply'])) {
        // Never repair ledger/table mismatches or existing schema automatically.
        if ($recorded || $exists) { throw new LogicException('Table or ledger already exists. Review the state; no automatic retry/repair.'); }
        if (!isset($options['backup-confirmed']) || !hash_equals($sha,(string)($options['reviewed-sha']??''))) { throw new LogicException('Confirm a recoverable test backup and the exact reviewed SHA.'); }
        foreach (explode(';',file_get_contents($file)) as $sql) { if(trim($sql)!=='')$p->exec($sql); }
        $p->prepare('INSERT INTO schema_migrations(name) VALUES(?)')->execute([$name]);
        $result['writes']=true;$result['status']='applied';
    } else {
        $result['status']=$recorded&&$exists?'present':(!$recorded&&!$exists?'prepared_not_applied':'review_schema_ledger_mismatch');
    }
    echo json_encode($result,JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR,'Test presence migration stopped: '.$e->getMessage().PHP_EOL);exit(1);
}
