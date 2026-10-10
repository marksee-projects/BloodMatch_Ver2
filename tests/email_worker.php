<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/backend/src/autoload.php';
require BASE_PATH.'/database/email_checkpoint_support.php';
use BloodMatch\Config\Database;
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Repositories\EmailOutboxRepository;
use BloodMatch\Services\NotificationService as Notify;
use BloodMatch\Services\TestEmailWorker;

$pdo=null;$users=[];$requests=[];$attemptIds=[];$workers=[];$passed=0;$messages=[];$token=bin2hex(random_bytes(8));
ini_set('display_errors','0');
function q(string $sql,array $args=[]): PDOStatement{$s=Database::pdo()->prepare($sql);$s->execute($args);return $s;}
function check(bool $ok,string $label): void{global $passed;if(!$ok){throw new LogicException('FAIL '.$label);}$passed++;echo 'PASS '.$label.PHP_EOL;}
function user(string $label,string $role='member',string $status='verified',int $chapter=1): int{
    global $users,$token;
    q("INSERT INTO users(email,first_name,last_name,role,chapter_id,verification_status,date_of_birth,
        blood_type,blood_type_source,donor_enrolled_at,donor_availability,email_verified_at)
        VALUES (?,'Worker','Fixture',?,?,?,'1990-01-02','O-','self_reported',UTC_TIMESTAMP(),'available',UTC_TIMESTAMP())",
        ["worker-$token-$label@example.test",$role,$chapter,$status]);
    $id=(int)Database::pdo()->lastInsertId();$users[]=$id;return $id;
}
function membership(int $member,int $officer,string $decision='rejected'): int{
    q('UPDATE users SET verification_status=? WHERE id=?',[$decision,$member]);
    q('INSERT INTO verification_decisions(target_user_id,officer_id,decision) VALUES (?,?,?)',[$member,$officer,$decision]);
    $event=(int)Database::pdo()->lastInsertId();
    $n=Notify::notify($member,'verification.decision','Synthetic','Synthetic',['related_type'=>'verification','related_id'=>$member,
        'dedup_key'=>"verification:$member:$decision:decision:$event",'email'=>Notify::EMAIL_NORMAL]);
    return (int)q('SELECT id FROM email_outbox WHERE notification_id=?',[$n])->fetchColumn();
}
function emergency(int $owner,int $donor): int{
    global $requests,$token;
    $id=(new BloodRequestRepository())->create(['requester_id'=>$owner,'request_chapter_id'=>1,'required_blood_type'=>'O+',
        'quantity_units'=>1,'facility_name'=>"Worker $token",'hospital_id'=>null,'location_id'=>null,'latitude'=>null,'longitude'=>null,
        'urgency'=>'emergency','needed_datetime'=>gmdate('Y-m-d H:i:s',time()+3600),'review_status'=>'not_required']);
    $requests[]=$id;
    q("INSERT INTO matches(request_id,donor_id,generation,status) VALUES (?,?,1,'NOTIFIED')",[$id,$donor]);
    $r=q('SELECT * FROM blood_requests WHERE id=?',[$id])->fetch(PDO::FETCH_ASSOC);
    $notifications=Notify::notifyMatchGeneration($r,1,[$donor],true);
    q('UPDATE email_outbox e JOIN notifications n ON n.id=e.notification_id SET e.created_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 6 MINUTE) WHERE n.related_type=? AND n.related_id=? AND n.user_id=?', ['blood_request',$id,$donor]);
    return (int)q('SELECT id FROM email_outbox WHERE notification_id=(SELECT id FROM notifications WHERE related_type=? AND related_id=? AND user_id=?)',
        ['blood_request',$id,$donor])->fetchColumn();
}
function job(int $id): array{return q('SELECT * FROM email_outbox WHERE id=?',[$id])->fetch(PDO::FETCH_ASSOC);}
function runFake(array $ids,string $mode='accept',int $limit=20): array{
    global $messages,$attemptIds;
    $worker=new TestEmailWorker(function(array $meta)use($mode):bool{
        global $messages;
        if(Database::pdo()->inTransaction()){throw new LogicException('Transport entered with active workflow transaction.');}
        $messages[]=$meta;return $mode==='accept';
    });
    try{return $worker->run($ids,$mode!=='unconfigured',$limit);}
    finally{$attemptIds=array_map('intval',q('SELECT id FROM email_attempts')->fetchAll(PDO::FETCH_COLUMN));}
}
function clearAttempts():void{global $attemptIds;foreach($attemptIds as $id){q('DELETE FROM email_attempts WHERE id=?',[$id]);}$attemptIds=[];}
function seedBudget(int $count,string $class,string $age='0 SECOND'):void{
    global $attemptIds;
    if(!in_array($age,['0 SECOND','2 HOUR'],true)){throw new LogicException('Invalid fixture age.');}
    for($i=0;$i<$count;$i++){q("INSERT INTO email_attempts(mail_class,attempted_at) VALUES (?,DATE_SUB(UTC_TIMESTAMP(6),INTERVAL $age))",[$class]);$attemptIds[]=(int)Database::pdo()->lastInsertId();}
}
function race(int $id):array{
    global $workers;
    $start=microtime(true)+1;$results=[];$workers=[];
    for($i=0;$i<2;$i++){
        $pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'--worker',(string)$id,(string)$start],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,BASE_PATH);
        if(!is_resource($proc)){throw new LogicException('Cannot start parallel workers.');}fclose($pipes[0]);$workers[]=[$proc,$pipes];
    }
    $bad=false;
    foreach($workers as [$proc,$pipes]){
        $out=stream_get_contents($pipes[1]);stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);$r=json_decode($out,true);
        if($code!==0||!is_array($r)){$bad=true;}else{$results[]=$r;}
    }
    $workers=[];if($bad){throw new LogicException('Parallel worker failed; raw errors withheld.');}return $results;
}
try{
    $_ENV['MAIL_HOST']='';putenv('MAIL_HOST=');$pdo=emailCheckpointTarget();
    if(($argv[1]??'')==='--worker'){
        $until=(float)($argv[3]??0);if($until>microtime(true)+5){throw new LogicException('Invalid worker gate.');}
        while(microtime(true)<$until){usleep(1000);}
        $worker=new TestEmailWorker(static function(array $meta):bool{usleep(200000);return true;});
        echo json_encode($worker->run([(int)$argv[2]]));exit(0);
    }
    if(count($argv)!==1||!function_exists('proc_open')){throw new LogicException('No arguments and proc_open required.');}
    check(emailCheckpointShape($pdo)===['email_outbox'=>'ready','email_attempts'=>'ready'],'021 shape ready');
    check((int)q('SELECT COUNT(*) FROM schema_migrations WHERE name=?',['021_email_outbox_accounting.sql'])->fetchColumn()===1,'021 recorded');
    check((int)q('SELECT COUNT(*) FROM email_attempts')->fetchColumn()===0,'empty attempt ledger required; stop other writers, never reset existing attempts');
    $baseline=q('SELECT id,status,rejection_reason FROM donation_reports ORDER BY id')->fetchAll(PDO::FETCH_NUM);
    $officer=user('officer','officer');$normalUser=user('normal','member','rejected');$donor=user('cross-chapter','member','verified',2);
    $normal=membership($normalUser,$officer);$urgent=emergency($officer,$donor);
    $r=runFake([$normal,$urgent],'accept',1);
    check($r['sent']===1&&job($urgent)['status']==='sent'&&job($normal)['status']==='pending','Emergency priority before normal');
    check($messages[0]['mail_class']==='emergency','Emergency attempt grouping');
    $r=runFake([$normal]);check($r['sent']===1&&end($messages)['mail_class']==='account','membership normal priority uses account budget');
    check(job($normal)['attempts']==1&&q('SELECT emailed_at FROM notifications WHERE id=?',[job($normal)['notification_id']])->fetchColumn()!==null,'acceptance atomically records sent and emailed_at');
    $before=count($messages);runFake([$normal,$urgent]);check(count($messages)===$before,'sent jobs are not delivered twice');clearAttempts();
    $digestDonor=user('digest');$groupOne=emergency($officer,$digestDonor);$groupTwo=emergency($officer,$digestDonor);
    $before=count($messages);$r=runFake([$groupOne,$groupTwo]);
    check($r['sent']===2&&count($messages)===$before+1&&count(end($messages)['job_ids'])===2,'two requesters opportunities grouped into one fake email');
    check((int)q('SELECT COUNT(*) FROM email_attempts')->fetchColumn()===1,'digest consumes one shared attempt');
    $waiting=emergency($officer,$digestDonor);q('UPDATE email_outbox SET created_at=UTC_TIMESTAMP(6) WHERE id=?',[$waiting]);
    $r=runFake([$waiting]);check($r['deferred']===1&&job($waiting)['last_error_code']==='digest_grouping'&&(int)job($waiting)['attempts']===0,'five-minute grouping waits without consuming attempts');
    q('UPDATE email_outbox SET created_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 6 MINUTE),next_attempt_at=UTC_TIMESTAMP(6) WHERE id=?',[$waiting]);
    $r=runFake([$waiting]);check($r['sent']===1,'second donor digest allowed inside rolling 30 minutes');
    $capped=emergency($officer,$digestDonor);$r=runFake([$capped]);
    check($r['deferred']===1&&job($capped)['last_error_code']==='emergency_recipient_limit'&&(int)job($capped)['attempts']===0,'third donor digest across requests deferred inside rolling 30 minutes');clearAttempts();
    $failure=membership(user('failure','member','rejected'),$officer);$firstMessage=null;
    foreach([1=>60,2=>300,3=>900,4=>3600,5=>14400,6=>0] as $i=>$delay){
        q('UPDATE email_outbox SET next_attempt_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?',[$failure]);
        $r=runFake([$failure],'fail');$row=job($failure);$delta=strtotime($row['next_attempt_at'].' UTC')-strtotime($row['updated_at'].' UTC');
        check((int)$row['attempts']===$i&&$row['status']===($i===6?'failed':'pending')&&abs($delta-$delay)<=1,"failure attempt $i uses approved backoff/exhaustion");
        $meta=end($messages);$firstMessage??=$meta['message_id'];check($meta['message_id']===$firstMessage,"stable Message-ID retry $i");
    }
    check((int)q('SELECT COUNT(*) FROM email_attempts')->fetchColumn()===6,'failed fake transports consume six committed attempts');clearAttempts();
    $unconfigured=membership(user('unconfigured','member','rejected'),$officer);$r=runFake([$unconfigured],'unconfigured');
    check($r['deferred']===1&&(int)job($unconfigured)['attempts']===0&&job($unconfigured)['last_error_code']==='smtp_unconfigured','unconfigured deferral consumes no attempt');
    $hour=emergency($officer,$donor);seedBudget(90,'emergency');$r=runFake([$hour]);
    check($r['deferred']===1&&(int)job($hour)['attempts']===0&&job($hour)['last_error_code']==='shared_budget','90/hour Emergency cap defers without transport attempt');
    $protected=membership(user('protected','member','rejected'),$officer);$r=runFake([$protected]);
    check($r['sent']===1,'account mail still uses protected hourly capacity');clearAttempts();
    $day=emergency($officer,$donor);seedBudget(400,'emergency','2 HOUR');$r=runFake([$day]);
    check($r['deferred']===1&&(int)job($day)['attempts']===0,'400/day Emergency cap preserved');
    $dayAccount=membership(user('day-account','member','rejected'),$officer);$r=runFake([$dayAccount]);check($r['sent']===1,'protected daily account capacity preserved');clearAttempts();
    $total=membership(user('total-hour','member','rejected'),$officer);seedBudget(100,'account');$r=runFake([$total]);
    check($r['deferred']===1&&(int)job($total)['attempts']===0,'100/hour total cap preserved');clearAttempts();
    q('UPDATE email_outbox SET next_attempt_at=UTC_TIMESTAMP(6) WHERE id=?',[$total]);seedBudget(450,'account','2 HOUR');$r=runFake([$total]);
    check($r['deferred']===1&&(int)job($total)['attempts']===0,'450/day total cap preserved');clearAttempts();
    $capUser=user('recipient-cap','member','rejected');$recipient=membership($capUser,$officer);
    for($i=0;$i<5;$i++){q("INSERT INTO notifications(user_id,type,title,body,emailed_at,created_at) VALUES (?,'verification.decision','Synthetic','Synthetic',UTC_TIMESTAMP(),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR))",[$capUser]);}
    $r=runFake([$recipient]);check($r['deferred']===1&&job($recipient)['last_error_code']==='recipient_limit'&&(int)job($recipient)['attempts']===0,'five normal acceptances per user/hour deferred by emailed_at');
    for($i=0;$i<5;$i++){q("INSERT INTO notifications(user_id,type,title,body,emailed_at) VALUES (?,'match.new','Synthetic','Synthetic',UTC_TIMESTAMP())",[$donor]);}
    $bypass=emergency($officer,$donor);runFake([$bypass]);check(job($bypass)['status']==='sent','Emergency retains recipient-limit bypass within shared capacity');clearAttempts();
    $expired=membership(user('expired','member','rejected'),$officer);q('UPDATE email_outbox SET expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?',[$expired]);
    runFake([$expired]);check(job($expired)['cancel_reason']==='expired'&&(int)job($expired)['attempts']===0,'expired membership job cancelled without attempt');
    $staleUser=user('superseded','member','rejected');$older=membership($staleUser,$officer);$newer=membership($staleUser,$officer,'verified');
    runFake([$older,$newer]);check(job($older)['cancel_reason']==='superseded'&&job($newer)['status']==='sent','new decision supersedes older unsent job');clearAttempts();
    $unverifiedUser=user('unverified','member','rejected');$unverified=membership($unverifiedUser,$officer);q('UPDATE users SET email_verified_at=NULL WHERE id=?',[$unverifiedUser]);
    runFake([$unverified]);check(job($unverified)['cancel_reason']==='recipient_unverified','current email verification rechecked');
    $closed=emergency($officer,$donor);$request=end($requests);q("UPDATE blood_requests SET status='CANCELLED' WHERE id=?",[$request]);
    runFake([$closed]);check(job($closed)['cancel_reason']==='request_closed','closed request cancels outreach');
    $deadline=emergency($officer,$donor);$request=end($requests);q('UPDATE blood_requests SET needed_datetime=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?',[$request]);
    runFake([$deadline]);check(job($deadline)['cancel_reason']==='donor_ineligible','live request deadline rechecked even if stored job expiry is future');
    $generation=emergency($officer,$donor);$request=end($requests);q('UPDATE matches SET generation=2 WHERE request_id=?',[$request]);
    runFake([$generation]);check(job($generation)['cancel_reason']==='stale_generation','generation change cancels old outreach');
    $responded=emergency($officer,$donor);$request=end($requests);q("UPDATE matches SET status='RESPONDED' WHERE request_id=?",[$request]);
    runFake([$responded]);check(job($responded)['cancel_reason']==='offer_resolved','responded offer no longer needs opportunity email');
    foreach(['inactive','availability','cooldown','blood','age','consent'] as $condition){
        $fixtureDonor=user($condition,'member','verified',2);$candidate=emergency($officer,$fixtureDonor);
        $sql=match($condition){
            'inactive'=>"account_status='deactivated',deactivated_at=UTC_TIMESTAMP()",'availability'=>"donor_availability='unavailable'",
            'cooldown'=>"last_verified_donation_at=UTC_TIMESTAMP()",'blood'=>"blood_type='AB+'",'age'=>"date_of_birth=DATE_SUB(UTC_DATE(),INTERVAL 15 YEAR)",
            'consent'=>"date_of_birth=DATE_SUB(UTC_DATE(),INTERVAL 17 YEAR)"};
        q("UPDATE users SET $sql WHERE id=?",[$fixtureDonor]);runFake([$candidate]);check(job($candidate)['cancel_reason']==='donor_ineligible',"current $condition safety rule enforced");
    }
    $excludedUser=user('excluded');$pdo->beginTransaction();$n=Notify::notify($excludedUser,'account.status_changed','Synthetic','Synthetic');
    (new EmailOutboxRepository())->insert($n,'normal',gmdate('Y-m-d H:i:s',time()+3600));$pdo->commit();
    $excluded=(int)q('SELECT id FROM email_outbox WHERE notification_id=?',[$n])->fetchColumn();runFake([$excluded]);check(job($excluded)['cancel_reason']==='channel_excluded','excluded lifecycle jobs never delivered');
    $lease=membership(user('lease','member','rejected'),$officer);$repo=new EmailOutboxRepository();$now=(string)q('SELECT UTC_TIMESTAMP(6)')->fetchColumn();
    $old=$repo->claim([$lease],$now);check($repo->claim([$lease],$now)===null,'unexpired lease cannot be claimed again');
    q('UPDATE email_outbox SET lease_expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?',[$lease]);
    $fresh=$repo->claim([$lease],(string)q('SELECT UTC_TIMESTAMP(6)')->fetchColumn());
    check($fresh['lease_token']!==$old['lease_token'],'expired lease recovery replaces token');
    check(!$repo->finish($old,'sent',(string)q('SELECT UTC_TIMESTAMP(6)')->fetchColumn()),'stale lease cannot mark sent/emailed_at');
    q('UPDATE email_outbox SET lease_expires_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id=?',[$lease]);runFake([$lease]);check(job($lease)['status']==='sent','interrupted lease recovery delivers once');clearAttempts();
    $ownership=membership(user('ownership','member','rejected'),$officer);
    $worker=new TestEmailWorker(static function(array $meta):bool{
        q('UPDATE email_outbox SET lease_token=? WHERE id=?',[str_repeat('a',32),$meta['job_id']]);return true;
    });
    $r=$worker->run([$ownership]);$attemptIds=array_map('intval',q('SELECT id FROM email_attempts')->fetchAll(PDO::FETCH_COLUMN));
    check($r['lost']===1&&job($ownership)['status']==='leased'&&job($ownership)['sent_at']===null,'lost completion ownership cannot claim delivery');clearAttempts();
    $parallel=membership(user('parallel','member','rejected'),$officer);$r=race($parallel);
    $attemptIds=array_map('intval',q('SELECT id FROM email_attempts')->fetchAll(PDO::FETCH_COLUMN));
    check(array_sum(array_column($r,'sent'))===1&&(int)job($parallel)['attempts']===1&&count($attemptIds)===1,'parallel runs send same job at most once in non-crash test');clearAttempts();
    $overlap=membership(user('overlap','member','rejected'),$officer);$other=Database::independentPdo();
    $s=$other->prepare('SELECT GET_LOCK(?,0)');$s->execute(['bm-test-email-worker']);
    try{$r=runFake([$overlap]);check($r['status']==='overlap_skipped'&&job($overlap)['status']==='pending','overlap is skipped without claim');}
    finally{$other->prepare('SELECT RELEASE_LOCK(?)')->execute(['bm-test-email-worker']);}
    check($baseline===q('SELECT id,status,rejection_reason FROM donation_reports ORDER BY id')->fetchAll(PDO::FETCH_NUM),'donation history unchanged');
    echo "$passed database fake-worker checks passed\n";
}catch(Throwable $e){fwrite(STDERR,($e instanceof LogicException?$e->getMessage():'Fake-worker database suite failed; raw errors withheld.').PHP_EOL);$failed=true;}
finally{
    foreach($workers as [$proc,$pipes]){foreach($pipes as $pipe){if(is_resource($pipe)){fclose($pipe);}}if(is_resource($proc)){proc_close($proc);}}
    if($pdo instanceof PDO&&($argv[1]??'')!=='--worker'){
        if($pdo->inTransaction()){$pdo->rollBack();}
        clearAttempts();
        foreach($requests as $request){q("DELETE FROM notifications WHERE related_type='blood_request' AND related_id=?",[$request]);q('DELETE FROM blood_requests WHERE id=?',[$request]);}
        foreach($users as $user){q('DELETE FROM users WHERE id=?',[$user]);}
        echo "Exact fixtures and synthetic attempts cleaned; audit history retained.\n";
    }
}
exit(isset($failed)?1:0);
