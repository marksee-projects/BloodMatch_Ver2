<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require __DIR__.'/helpers/non_response_isolation.php';
require dirname(__DIR__).'/backend/src/autoload.php';

use BloodMatch\Config\{Database,Env};
use BloodMatch\Services\{NonResponseStandbyService as Standby,NotificationService,WorkflowLockService,
    MatchResponseService,RequestLifecycleService,DonorEligibilityService,DonationCompletionPolicy};
use BloodMatch\Repositories\{UserRepository,MatchRepository};

$worker=($argv[1]??'')==='--worker';
$token=$worker?(string)getenv('BM_NON_RESPONSE_TOKEN'):bin2hex(random_bytes(8));
$storage=$worker?(string)getenv('BM_NON_RESPONSE_STORAGE'):sys_get_temp_dir().'/bloodmatch-nr48-'.$token;
if (!$worker) {
    mkdir($storage,0700); mkdir($storage.'/sessions',0700); mkdir($storage.'/keys',0700);
    putenv('BM_NON_RESPONSE_STORAGE='.$storage); putenv('BM_NON_RESPONSE_TOKEN='.$token);
}
ini_set('session.save_path',$storage.'/sessions'); ini_set('error_log',$storage.'/errors.log');
putenv('DOCUMENT_KEY_DIRECTORY='.$storage.'/keys'); $_ENV['DOCUMENT_KEY_DIRECTORY']=$storage.'/keys';
$p=null; $checks=0; $ownedUsers=[]; $ownedRequests=[]; $error=null;
function nrQ(string $sql,array $args=[]): PDOStatement {
    global $p; $s=$p->prepare($sql); $s->execute($args); return $s;
}
function nrCheck(bool $ok,string $label): void {
    global $checks; if (!$ok) { throw new RuntimeException('FAIL '.$label); }
    $checks++; echo "PASS $label\n";
}
function nrUser(): int {
    global $p,$token,$ownedUsers;
    nrQ("INSERT INTO users(email,first_name,last_name,role,account_status,verification_status,date_of_birth,blood_type,blood_type_source,donor_enrolled_at,donor_availability,email_verified_at)
        VALUES (?,'Synthetic','NR48','member','active','verified','1990-01-01','O+','self_reported',UTC_TIMESTAMP(),'available',UTC_TIMESTAMP())",
        ['nr48-'.$token.'-'.bin2hex(random_bytes(4)).'@example.test']);
    $id=(int)$p->lastInsertId(); $ownedUsers[]=$id; return $id;
}
function nrFixture(?int $donor=null): array {
    global $p,$ownedRequests; $owner=nrUser(); $donor??=nrUser();
    nrQ("INSERT INTO blood_requests(requester_id,required_blood_type,quantity_units,facility_name,urgency,needed_datetime,status,review_status)
        VALUES (?,'O+',1,'Synthetic NR48','routine',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 7 DAY),'OPEN','not_required')",[$owner]);
    $request=(int)$p->lastInsertId(); $ownedRequests[]=$request;
    nrQ("INSERT INTO matches(request_id,donor_id,generation,status) VALUES (?,?,1,'NOTIFIED')",[$request,$donor]);
    $match=(int)$p->lastInsertId();
    WorkflowLockService::transaction(fn()=>NotificationService::notifyMatchGeneration(
        array_replace(nrQ('SELECT * FROM blood_requests WHERE id=?',[$request])->fetch(),['id'=>$request]),1,[$donor],false));
    $op=(int)nrQ('SELECT id FROM donor_opportunities WHERE request_id=? AND donor_id=?',[$request,$donor])->fetchColumn();
    return ['owner'=>$owner,'donor'=>$donor,'request'=>$request,'match'=>$match,'op'=>$op];
}
function nrTimer(int $id): array { return nrQ('SELECT * FROM donor_opportunities WHERE id=?',[$id])->fetch(); }
function nrDue(int $id,int $hours=48): void {
    nrQ('UPDATE donor_opportunities SET notified_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL ? HOUR),due_at=UTC_TIMESTAMP(6) WHERE id=?',[$hours,$id]);
}
function nrStart(array $fixture,string $action): array {
    global $storage; $ready=$storage.'/ready-'.bin2hex(random_bytes(5)); $pipes=[];
    $proc=proc_open([PHP_BINARY,__FILE__,'--worker',$action,(string)$fixture['op'],(string)$fixture['request'],(string)$fixture['donor'],$ready],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));
    if (!is_resource($proc)) { throw new RuntimeException('Cannot launch isolated worker.'); }
    fclose($pipes[0]); $end=microtime(true)+12;
    while (!is_file($ready)) { if (microtime(true)>$end) { proc_terminate($proc); throw new RuntimeException('Worker preflight/ready timeout.'); } usleep(10000); }
    $connection=(int)file_get_contents($ready); unlink($ready);
    // Demonstrate real overlapping row-lock waits, not just two sequential calls.
    do {
        $waiting=(int)nrQ("SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id=? AND trx_state='LOCK WAIT'",[$connection])->fetchColumn();
        if ($waiting>0) { return [$proc,$pipes]; }
        usleep(20000);
    } while (microtime(true)<$end);
    proc_terminate($proc); throw new RuntimeException('Worker did not enter the expected row-lock wait.');
}
function nrFinish(array $child): array {
    [$proc,$pipes]=$child; $stdout=stream_get_contents($pipes[1]); $stderr=stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); $exit=proc_close($proc);
    if ($exit!==0 || $stderr!=='') { throw new RuntimeException('Isolated worker failed (details in isolated log).'); }
    return json_decode($stdout,true,512,JSON_THROW_ON_ERROR);
}
try {
    Env::load(dirname(__DIR__).'/.env');
    // PDO's connect timeout does not bound every Windows/mysqlnd greeting wait.
    // Fail closed before credentials/fixtures if the listener never greets clients.
    $socket=@stream_socket_client('tcp://127.0.0.1:3306',$socketError,$socketMessage,3);
    if ($socket===false) { throw new RuntimeException('Test server TCP connection unavailable.'); }
    stream_set_timeout($socket,3);
    $greeting=fread($socket,4); fclose($socket);
    if ($greeting===false || strlen($greeting)!==4) { throw new RuntimeException('Test server did not send its initial MySQL greeting.'); }
    // Bounded connection preflight, fixed database with no fallback and no secret output.
    $p=new PDO('mysql:host=127.0.0.1;port=3306;dbname=bloodmatch_test;charset=utf8mb4',Env::get('DB_USER',''),Env::get('DB_PASS',''),[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5]);
    $target=$p->query('SELECT DATABASE() AS db,@@port AS port')->fetch();
    if ($target['db']!=='bloodmatch_test' || (int)$target['port']!==3306) { throw new LogicException('Wrong test target.'); }
    $property=new ReflectionProperty(Database::class,'pdo'); $property->setValue(null,$p);
    $p->exec("SET time_zone='+00:00'"); $p->exec('SET SESSION innodb_lock_wait_timeout=15');
    $required=['022_non_response_opportunities.sql','030_donation_completion_workflow.sql','031_offer_chat_donation_proof.sql','032_consolidate_donation_storage.sql'];
    foreach ($required as $name) {
        if ((int)nrQ('SELECT COUNT(*) FROM schema_migrations WHERE name=?',[$name])->fetchColumn()!==1) { throw new LogicException('Required test schema not ready.'); }
    }
    if (!Standby::ready()) { throw new LogicException('Non-response schema unavailable.'); }
    if ($worker) {
        [$script,$flag,$action,$op,$request,$donor,$ready]=$argv;
        file_put_contents($ready,(string)$p->query('SELECT CONNECTION_ID()')->fetchColumn());
        try {
            $result=match($action) {
                'due'=>Standby::processDue([(int)$op]),
                'response'=>(new MatchResponseService())->respond((new UserRepository())->findById((int)$donor),(int)$request,'nr48_race'),
                'expiry'=>(new RequestLifecycleService())->expireOne((int)$request,gmdate('Y-m-d H:i:s',time()+86400*8)),
            };
            echo json_encode(['ok'=>true,'result'=>$result]);
        } catch (Throwable $e) { echo json_encode(['ok'=>false,'code'=>(int)$e->getCode()]); }
        exit;
    }
    \BloodMatch\Http\Session::start();
    nrCheck(true,'verified disposable bloodmatch_test:3306 and required migration markers before writes');
    nrCheck(\BloodMatch\Config\AppConfig::basePath()===$storage,'all test storage/session paths are isolated');
    $attempts=nrQ('SELECT * FROM email_attempts ORDER BY id')->fetchAll();
    $settings=nrQ('SELECT * FROM system_settings ORDER BY setting_key')->fetchAll();
    // Freeze server time for exact microsecond deadline tests. Session-only, never global.
    $p->exec('SET timestamp='.(int)$p->query('SELECT UNIX_TIMESTAMP()')->fetchColumn());
    $p->beginTransaction();
    try {
        $a=nrFixture(); $row=nrTimer($a['op']);
        nrCheck((int)nrQ('SELECT TIMESTAMPDIFF(SECOND,notified_at,due_at) FROM donor_opportunities WHERE id=?',[$a['op']])->fetchColumn()===48*3600,'new committed in-app issuance stores 48-hour interval');
        nrQ('UPDATE notifications SET read_at=UTC_TIMESTAMP() WHERE id=?',[$row['notification_id']]);
        $visible=nrQ('SELECT created_at,read_at FROM notifications WHERE id=?',[$row['notification_id']])->fetch();
        WorkflowLockService::transaction(fn()=>NotificationService::notifyMatchGeneration(nrQ('SELECT * FROM blood_requests WHERE id=?',[$a['request']])->fetch(),1,[$a['donor']],false));
        nrCheck(nrTimer($a['op'])===$row && nrQ('SELECT created_at,read_at FROM notifications WHERE id=?',[$row['notification_id']])->fetch()===$visible,'quiet notification retry preserves issuance deadline and visible creation/read state');
        nrCheck((int)nrQ("SELECT COUNT(*) FROM notifications WHERE user_id=? AND type='match.new' AND related_id=?",[$a['donor'],$a['request']])->fetchColumn()===1,'same opportunity notification remains deduplicated');
        nrCheck(Standby::processDue([$a['op']])===0,'new opportunity is not immediately due');
        nrDue($a['op']); nrQ('UPDATE donor_opportunities SET due_at=DATE_ADD(due_at,INTERVAL 1 MICROSECOND) WHERE id=?',[$a['op']]);
        nrCheck(Standby::processDue([$a['op']])===0,'one microsecond before 48-hour boundary does not apply standby');
        nrQ('UPDATE donor_opportunities SET due_at=UTC_TIMESTAMP(6) WHERE id=?',[$a['op']]);
        nrCheck(Standby::processDue([$a['op']])===1,'exact 48-hour boundary applies one transition');
        $started=Standby::state($a['donor'])['standby_started_at'];
        nrCheck(Standby::processDue([$a['op']])===0 && Standby::state($a['donor'])['standby_started_at']===$started,'retry preserves existing active standby');
        nrCheck((int)nrQ("SELECT COUNT(*) FROM notifications WHERE user_id=? AND type='donor.non_response_standby'",[$a['donor']])->fetchColumn()===1,'standby notification deduplicated');
        $future=nrFixture($a['donor']);
        nrCheck(Standby::acknowledge($a['donor']) && !Standby::acknowledge($a['donor']),'acknowledgment is explicit and idempotent');
        nrCheck(nrTimer($a['op'])['status']==='acknowledged' && nrTimer($future['op'])['status']==='pending','acknowledgment retires due timers and preserves future opportunity');
        nrCheck(Standby::processDue([$a['op']])===0,'acknowledged timer cannot retrigger');
        $watermark=Standby::state($a['donor'])['consumed_through'];
        nrQ('UPDATE donor_opportunities SET due_at=? WHERE id=?',[$watermark,$future['op']]);
        nrCheck(Standby::processDue([$future['op']])===0,'pending timer at consumed-through equality remains consumed');
        nrQ('UPDATE donor_opportunities SET due_at=DATE_ADD(?,INTERVAL 1 MICROSECOND) WHERE id=?',[$watermark,$future['op']]);
        nrCheck(!Standby::consumed(nrTimer($future['op'])['due_at'],$watermark),'future timer one microsecond beyond watermark remains a distinct obligation');
        $legacy=nrFixture(); nrDue($legacy['op'],42); $legacyBefore=nrTimer($legacy['op']);
        WorkflowLockService::transaction(fn()=>Standby::opportunity($legacy['request'],$legacy['donor'],1,(int)$legacyBefore['notification_id']));
        nrCheck(nrTimer($legacy['op'])===$legacyBefore,'retry preserves stored legacy 42-hour deadline and issuance history');
        nrCheck(Standby::processDue([$legacy['op']])===1,'legacy 42-hour obligation still applies at its stored deadline');
        $new43=nrFixture(); nrQ('UPDATE donor_opportunities SET notified_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 43 HOUR),due_at=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 5 HOUR) WHERE id=?',[$new43['op']]);
        nrCheck(Standby::processDue([$new43['op']])===0,'new 43-hour-old opportunity is not due');
        $generation=nrFixture(); nrDue($generation['op']);
        nrQ('UPDATE matches SET generation=2 WHERE id=?',[$generation['match']]);
        WorkflowLockService::transaction(fn()=>Standby::opportunity($generation['request'],$generation['donor'],2,(int)nrTimer($generation['op'])['notification_id']));
        nrCheck(nrTimer($generation['op'])['status']==='superseded' && Standby::processDue([$generation['op']])===0,'genuine new generation supersedes older pending deadline');

        $exclusions=[
            'membership_pending'=>['verification_status'=>'pending'], 'membership_rejected'=>['verification_status'=>'rejected'],
            'membership_unverified'=>['verification_status'=>'unverified'], 'email_unverified'=>['email_verified_at'=>null],
            'inactive'=>['account_status'=>'deactivated'], 'not_enrolled'=>['donor_enrolled_at'=>null],
            'unavailable'=>['donor_availability'=>'unavailable'], 'unanchored_standby'=>['donor_availability'=>'standby'],
            'missing_dob'=>['date_of_birth'=>null], 'under16'=>['date_of_birth'=>gmdate('Y-m-d',strtotime('-14 years'))],
            'minor_without_consent'=>['date_of_birth'=>gmdate('Y-m-d',strtotime('-17 years'))],
            'missing_blood'=>['blood_type'=>null], 'incompatible'=>['blood_type'=>'AB+'],
            'post_donation_standby'=>['last_verified_donation_at'=>gmdate('Y-m-d H:i:s',time()-3600)],
            'post_donation_cooldown'=>['last_verified_donation_at'=>gmdate('Y-m-d H:i:s',time()-5*86400)],
        ];
        foreach ($exclusions as $label=>$fields) {
            $f=nrFixture(); nrDue($f['op']);
            $set=implode(',',array_map(static fn($key)=>$key.'=?',array_keys($fields)));
            nrQ('UPDATE users SET '.$set.' WHERE id=?',[...array_values($fields),$f['donor']]);
            $match=nrQ('SELECT * FROM matches WHERE id=?',[$f['match']])->fetch();
            nrCheck(Standby::processDue([$f['op']])===0 && nrTimer($f['op'])['status']==='invalidated',$label.' invalidates specific ineligible opportunity');
            nrCheck(nrQ('SELECT * FROM matches WHERE id=?',[$f['match']])->fetch()===$match,$label.' leaves match unchanged');
            nrQ("UPDATE users SET account_status='active',verification_status='verified',email_verified_at=UTC_TIMESTAMP(),donor_enrolled_at=UTC_TIMESTAMP(),donor_availability='available',date_of_birth='1990-01-01',blood_type='O+',last_verified_donation_at=NULL WHERE id=?",[$f['donor']]);
            WorkflowLockService::transaction(fn()=>Standby::opportunity($f['request'],$f['donor'],1,(int)nrTimer($f['op'])['notification_id']));
            nrCheck(Standby::processDue([$f['op']])===0 && nrTimer($f['op'])['status']==='invalidated',$label.' recovery/retry cannot revive invalidated timer');
        }
        $held=nrFixture(); nrDue($held['op']);
        nrQ("INSERT INTO donation_reports(match_id,donor_id,status,reported_at,workflow_state,hold_started_at) VALUES (?,?,'PENDING',UTC_TIMESTAMP(),'awaiting_requester',UTC_TIMESTAMP())",[$held['match'],$held['donor']]);
        $report=nrQ('SELECT * FROM donation_reports WHERE match_id=?',[$held['match']])->fetch();
        nrCheck(Standby::processDue([$held['op']])===0 && nrTimer($held['op'])['status']==='invalidated','pending-report hold excludes new non-response sanction');
        nrCheck(nrQ('SELECT * FROM donation_reports WHERE match_id=?',[$held['match']])->fetch()===$report,'hold, report and reminder timestamps remain untouched');
        $minor=nrFixture(); nrDue($minor['op']);
        nrQ('UPDATE users SET date_of_birth=? WHERE id=?',[gmdate('Y-m-d',strtotime('-17 years')),$minor['donor']]);
        nrQ("INSERT INTO member_documents(user_id,doc_type,stored_name,mime_type,size_bytes) VALUES (?,'parental_consent',?,'application/pdf',1)",[$minor['donor'],hash('sha256',$token.'consent')]);
        nrCheck(Standby::processDue([$minor['op']])===1,'current DB-derived parental consent permits age17 timeout eligibility');
        foreach (['CANCELLED','EXPIRED','FULFILLED','overdue','answered'] as $state) {
            $stale=nrFixture(); nrDue($stale['op']);
            if ($state==='overdue') nrQ('UPDATE blood_requests SET needed_datetime=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY) WHERE id=?',[$stale['request']]);
            elseif ($state==='answered') nrQ("UPDATE matches SET status='RESPONDED' WHERE id=?",[$stale['match']]);
            else nrQ('UPDATE blood_requests SET status=? WHERE id=?',[$state,$stale['request']]);
            $match=nrQ('SELECT * FROM matches WHERE id=?',[$stale['match']])->fetch();
            nrCheck(Standby::processDue([$stale['op']])===0 && nrTimer($stale['op'])['status']==='invalidated' && nrQ('SELECT * FROM matches WHERE id=?',[$stale['match']])->fetch()===$match,$state.' retires only the stale timer without altering answered/current match');
        }
        $other=nrFixture(); nrDue($other['op']); $before=nrTimer($other['op']);
        nrCheck(Standby::processDue([])===0 && nrTimer($other['op'])===$before,'empty scope does not process unrelated opportunities');
        $oldAnchor=nrFixture(); nrDue($oldAnchor['op']);
        nrQ("UPDATE users SET donor_availability='standby',last_verified_donation_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 100 DAY) WHERE id=?",[$oldAnchor['donor']]);
        nrCheck(Standby::processDue([$oldAnchor['op']])===1,'expired anchored stored standby uses effective eligibility');
        nrCheck(nrQ('SELECT * FROM system_settings ORDER BY setting_key')->fetchAll()===$settings,'post-donation settings unchanged');
        $r=['status'=>'PENDING','workflow_state'=>'awaiting_requester','reported_at'=>'2026-10-01 00:00:00'];
        nrCheck(DonationCompletionPolicy::dueActions($r,'2026-10-02 00:00:00')===['remind'] && DonationCompletionPolicy::dueActions($r,'2026-10-03 00:00:00')===['remind','escalate'],'report reminder24h and escalation48h unchanged');
    } finally { $p->rollBack(); $ownedUsers=[]; $ownedRequests=[]; $p->exec('SET timestamp=0'); }

    // Committed synthetic fixtures only for cross-connection lock tests; ownership-checked cleanup below.
    foreach (['response','timeout_before_response','expiry','timeout_before_expiry','two_ticks'] as $scenario) {
        $f=nrFixture(); nrDue($f['op']); $p->beginTransaction();
        WorkflowLockService::request($f['request']);
        if ($scenario==='response') {
            (new MatchResponseService())->respond((new UserRepository())->findById($f['donor']),$f['request'],'nr48_parent');
            $child=nrStart($f,'due');
        } elseif ($scenario==='expiry') {
            (new RequestLifecycleService())->expireOne($f['request'],gmdate('Y-m-d H:i:s',time()+8*86400));
            $child=nrStart($f,'due');
        } elseif ($scenario==='two_ticks') {
            $child=nrStart($f,'due'); $second=nrStart($f,'due');
        } else {
            nrCheck(Standby::processDue([$f['op']])===1,$scenario.' first transaction applies standby');
            $child=nrStart($f,$scenario==='timeout_before_response'?'response':'expiry');
        }
        $p->commit(); $result=nrFinish($child);
        if ($scenario==='response') {
            nrCheck($result['ok'] && $result['result']===0 && nrTimer($f['op'])['status']==='answered' && Standby::state($f['donor'])['standby_started_at']===null,'concurrent committed response beats waiting timeout without stale sanction');
        } elseif ($scenario==='expiry') {
            nrCheck($result['ok'] && $result['result']===0 && nrTimer($f['op'])['status']==='invalidated' && Standby::state($f['donor'])['standby_started_at']===null,'concurrent committed expiry beats waiting timeout');
        } elseif ($scenario==='timeout_before_response') {
            nrCheck(!$result['ok'] && Standby::state($f['donor'])['standby_started_at']!==null && nrQ('SELECT status FROM matches WHERE id=?',[$f['match']])->fetchColumn()==='NOTIFIED','concurrent response reloads committed standby and cannot bypass it');
        } elseif ($scenario==='timeout_before_expiry') {
            nrCheck($result['ok'] && nrQ('SELECT status FROM blood_requests WHERE id=?',[$f['request']])->fetchColumn()==='EXPIRED' && Standby::state($f['donor'])['standby_started_at']!==null,'expiry after timeout preserves legitimate active standby');
        } else {
            $two=nrFinish($second); nrCheck($result['ok'] && $two['ok'] && $result['result']+$two['result']===1,'concurrent safety ticks produce exactly one transition');
        }
    }
    nrCheck(nrQ('SELECT * FROM email_attempts ORDER BY id')->fetchAll()===$attempts && \PHPMailer\PHPMailer\PHPMailer::$calls===0,'synthetic in-app notifications only; zero SMTP calls and mail attempts');
    nrCheck(nrQ('SELECT * FROM system_settings ORDER BY setting_key')->fetchAll()===$settings,'concurrent workflows preserve all settings');
} catch (Throwable $e) {
    $error=$e;
    // Connection failures deliberately omit DSNs, credentials and private server messages.
    fwrite(STDERR,($p===null?'Test target unavailable before target verification; no fixture writes performed.':$e->getMessage()).PHP_EOL);
} finally {
    if ($p && !$worker) {
        if ($p->inTransaction()) { $p->rollBack(); }
        foreach ($ownedRequests as $id) {
            nrQ("DELETE r FROM blood_requests r JOIN users u ON u.id=r.requester_id WHERE r.id=? AND u.email LIKE ?",[$id,'nr48-'.$token.'-%@example.test']);
        }
        foreach ($ownedUsers as $id) { nrQ('DELETE FROM users WHERE id=? AND email LIKE ?',[$id,'nr48-'.$token.'-%@example.test']); }
        if ($ownedUsers) { nrCheck((int)nrQ('SELECT COUNT(*) FROM users WHERE email LIKE ?',['nr48-'.$token.'-%@example.test'])->fetchColumn()===0,'committed synthetic users/requests cleaned; append-only synthetic audit retained'); }
    }
    if (!$worker) {
        echo "$checks focused checks completed. Isolated diagnostic storage: $storage\n";
    }
}
exit($error?1:0);
