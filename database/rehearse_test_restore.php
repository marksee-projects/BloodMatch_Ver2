<?php
/** Explicitly authorized test-only restore; never changes application rows. */
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
define('BLOODMATCH_MAINTENANCE_FUNCTIONS_ONLY',true);
require __DIR__.'/demo_maintenance.php';
use BloodMatch\Config\Env;

try {
    $args=getopt('',['restore']);
    $port=(int)Env::get('DB_PORT','3306');
    if ($port!==3306 || (int)Env::get('TEST_DB_PORT','3306')!==3306) throw new RuntimeException('Rehearsal requires the confirmed port 3306 for both targets.');
    $p=new PDO('mysql:host='.Env::get('DB_HOST','127.0.0.1').';port=3306;dbname=bloodmatch;charset=utf8mb4',Env::get('DB_USER','root'),Env::get('DB_PASS',''),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $p->exec("SET time_zone='+00:00'");
    $identity=$p->query('SELECT DATABASE() AS db,@@port AS port,@@event_scheduler AS events')->fetch();
    if ($identity['db']!=='bloodmatch' || (int)$identity['port']!==3306 || $identity['events']!=='OFF') throw new RuntimeException('Unexpected source or database event scheduler is not OFF.');
    if (scalar($p,"SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA IN ('bloodmatch','bloodmatch_test')")!==0) throw new RuntimeException('Review scheduled SQL events before cloning.');
    if (scalar($p,"SELECT COUNT(*) FROM users WHERE id=3 AND email='markseejr@gmail.com' AND role='admin' AND account_status='active'")!==1) throw new RuntimeException('Designated source administrator mismatch.');
    if (scalar($p,"SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB='bloodmatch_test' AND ID<>CONNECTION_ID()")!==0) throw new RuntimeException('Stop test database clients/workers before restore.');
    $baseline=snapshot($p);
    $baselineHash=hash('sha256',json_encode($baseline,JSON_THROW_ON_ERROR));
    echo json_encode(['source'=>'bloodmatch','destination'=>'bloodmatch_test','port'=>3306,'source_users'=>scalar($p,'SELECT COUNT(*) FROM users'),'source_snapshot'=>$baselineHash,'mode'=>isset($args['restore'])?'restore':'review'],JSON_PRETTY_PRINT),PHP_EOL;
    if (!isset($args['restore'])) exit;
    $sourceBackup=backup($p,'bloodmatch',3306);
    $testBackup=backup($p,'bloodmatch_test',3306);
    echo 'Source backup: '.$sourceBackup.PHP_EOL.'Previous test backup: '.$testBackup.PHP_EOL;
    $dump=file_get_contents($sourceBackup);
    if ($dump===false || $dump==='' || preg_match('/^\s*(?:USE\b|CREATE\s+DATABASE\b|DROP\s+DATABASE\b)/mi',$dump) || preg_match('/`bloodmatch`\s*\.|\b(?:FROM|JOIN|UPDATE|INTO|TABLE)\s+bloodmatch\s*\./i',$dump)) throw new RuntimeException('Dump contains explicit database targeting or cannot be read.');
    if (!hash_equals($baselineHash,hash('sha256',json_encode(snapshot($p),JSON_THROW_ON_ERROR)))) throw new RuntimeException('Application changed during backup; no test replacement performed.');
    $escape=fn($s)=>'"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','\\n','\\r'],(string)$s).'"';
    $config=privateFile('restore-'.bin2hex(random_bytes(8)).'.cnf',"[client]\nhost=".$escape(Env::get('DB_HOST','127.0.0.1'))."\nport=3306\nuser=".$escape(Env::get('DB_USER','root'))."\npassword=".$escape(Env::get('DB_PASS',''))."\n");
    chmod($config,0600);
    try {
        // Both SQL targets are fixed literals. The source is never a DDL target.
        $p->exec('DROP DATABASE `bloodmatch_test`');
        $p->exec('CREATE DATABASE `bloodmatch_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $process=proc_open(['C:/xampp/mysql/bin/mysql.exe','--defaults-extra-file='.$config,'--default-character-set=utf8mb4','--binary-mode','--database=bloodmatch_test'],[0=>['file',$sourceBackup,'r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($process)) throw new RuntimeException('Cannot launch test import. Restore prior test backup if needed.');
        stream_get_contents($pipes[1]);fclose($pipes[1]);
        $error=stream_get_contents($pipes[2]);fclose($pipes[2]);
        if (proc_close($process)!==0) throw new RuntimeException('Test import failed; stop before repair. '.substr($error,0,800));
    } finally { if (is_file($config)) unlink($config); }
    $p->exec('USE `bloodmatch_test`');
    $testHash=hash('sha256',json_encode(snapshot($p),JSON_THROW_ON_ERROR));
    if (!hash_equals($baselineHash,$testHash)) throw new RuntimeException('Restored table data/schema fingerprint differs; stop before repair.');
    $p->exec('USE `bloodmatch`');
    if (!hash_equals($baselineHash,hash('sha256',json_encode(snapshot($p),JSON_THROW_ON_ERROR)))) throw new RuntimeException('Application changed during rehearsal restore. Investigate before continuing.');
    $result=['restored'=>'bloodmatch_test','source_backup'=>$sourceBackup,'previous_test_backup'=>$testBackup,'verified_table_fingerprint'=>$testHash,'application_unchanged'=>true];
    $report=privateFile('rehearsal-restore-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode($result+['report'=>$report],JSON_PRETTY_PRINT),PHP_EOL;
} catch(Throwable $e) { fwrite(STDERR,'STOP: '.$e->getMessage().PHP_EOL);exit(1); }
