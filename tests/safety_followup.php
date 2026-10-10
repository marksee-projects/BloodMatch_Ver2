<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(403);exit; }
define('BASE_PATH',dirname(__DIR__));
require BASE_PATH.'/backend/src/autoload.php';
require BASE_PATH.'/database/email_checkpoint_support.php';
require BASE_PATH.'/database/non_response_checkpoint_support.php';
require __DIR__.'/helpers/fake_mail_transport.php';
use BloodMatch\Config\Database;
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Repositories\UserRepository;
use BloodMatch\Services\MatchService;
use BloodMatch\Services\MatchResponseService;
use BloodMatch\Services\DonationService;
use BloodMatch\Services\NonResponseStandbyService as Standby;
use BloodMatch\Services\WorkflowLockService;
use BloodMatch\Services\NotificationService;
use BloodMatch\Services\AccountLifecycleService;

ini_set('display_errors','0');$pdo=null;$users=[];$requests=[];$passed=0;$failed=false;$token=bin2hex(random_bytes(8));
function q(string $sql,array $params=[]): PDOStatement { $s=Database::pdo()->prepare($sql);$s->execute($params);return $s; }
function check(bool $ok,string $label): void { global $passed;if(!$ok){throw new LogicException('FAIL '.$label);}$passed++;echo 'PASS '.$label.PHP_EOL; }
function fixture(string $name,string $role='member'): array {
    global $users,$token;
    q("INSERT INTO users(email,first_name,last_name,role,chapter_id,verification_status,date_of_birth,blood_type,blood_type_source,donor_enrolled_at,donor_availability,email_verified_at)
        VALUES (?,'Safety','Fixture',?,1,'verified','1990-01-02','A+','self_reported',UTC_TIMESTAMP(),'available',UTC_TIMESTAMP())",["safety-$token-$name@example.test",$role]);
    $id=(int)Database::pdo()->lastInsertId();$users[]=$id;return (new UserRepository())->findById($id);
}
function requestFixture(int $owner): int {
    global $requests;
    $id=(new BloodRequestRepository())->create(['requester_id'=>$owner,'request_chapter_id'=>1,'required_blood_type'=>'A+','quantity_units'=>3,
        'facility_name'=>'Safety fixture','location_id'=>null,'latitude'=>null,'longitude'=>null,'urgency'=>'routine',
        'needed_datetime'=>gmdate('Y-m-d H:i:s',time()+7*86400),'review_status'=>'not_required']);$requests[]=$id;return $id;
}
function notices(int $user,string $type,int $request): int { return (int)q('SELECT COUNT(*) FROM notifications WHERE user_id=? AND type=? AND related_id=?',[$user,$type,$request])->fetchColumn(); }
try {
    if (count($argv)!==1) { throw new LogicException('No arguments allowed.'); }
    $pdo=emailCheckpointTarget();\BloodMatch\Http\Session::start();check(!in_array('missing',nonResponseShape($pdo),true),'022 complete shape');
    check((int)q('SELECT COUNT(*) FROM schema_migrations WHERE name=?',['022_non_response_opportunities.sql'])->fetchColumn()===1,'022 recorded');
    check((int)q("SELECT COUNT(*) FROM donor_opportunities WHERE status='pending' AND due_at<=UTC_TIMESTAMP(6)")->fetchColumn()===0,'no unrelated due timers; stop all test writers');
    $attempts=q('SELECT * FROM email_attempts ORDER BY id')->fetchAll(PDO::FETCH_NUM);
    $owner=fixture('owner');$donor=fixture('donor');$officer=fixture('officer','officer');$admin=fixture('admin','admin');
    \BloodMatch\Http\Session::start();$_SESSION['user_id']=$officer['id'];
    $request=requestFixture($owner['id']);$matcher=new MatchService();
    $matcher->generateForRequest($request,true,'request_created');
    $visible=(int)q("SELECT id FROM notifications WHERE user_id=? AND type='match.new' AND related_id=?",[$donor['id'],$request])->fetchColumn();
    q('UPDATE notifications SET read_at=UTC_TIMESTAMP() WHERE id=?',[$visible]);
    $before=q('SELECT created_at,read_at FROM notifications WHERE id=?',[$visible])->fetch(PDO::FETCH_NUM);
    q("UPDATE blood_requests SET required_blood_type='AB+' WHERE id=?",[$request]);$matcher->generateForRequest($request,true,'material_change');
    check(notices($donor['id'],'match.new',$request)===1&&q('SELECT created_at,read_at FROM notifications WHERE id=?',[$visible])->fetch(PDO::FETCH_NUM)===$before,'quiet reuse keeps one visible notification and read/creation timestamps');
    check((int)q('SELECT COUNT(*) FROM donor_opportunities WHERE request_id=? AND donor_id=?',[$request,$donor['id']])->fetchColumn()===2,'two generations have independent retained timers');
    $matcher->generateForRequest($request,false,'manual_rematch');
    check((int)q('SELECT COUNT(*) FROM donor_opportunities WHERE request_id=? AND donor_id=?',[$request,$donor['id']])->fetchColumn()===2,'repeat matching does not restart or duplicate timers');
    check(notices($donor['id'],'match.invalidated',$request)===0,'compatible material edit does not invalidate a valid offer');
    $timerSnapshot=q('SELECT id,notified_at,due_at FROM donor_opportunities WHERE request_id=? AND donor_id=? ORDER BY id',[$request,$donor['id']])->fetchAll(PDO::FETCH_NUM);
    $ownerFresh=(new UserRepository())->findById($owner['id']);
    (new \BloodMatch\Services\RequestLifecycleService())->update($ownerFresh,$request,['required_blood_type'=>'AB+']);
    check($timerSnapshot===q('SELECT id,notified_at,due_at FROM donor_opportunities WHERE request_id=? AND donor_id=? ORDER BY id',[$request,$donor['id']])->fetchAll(PDO::FETCH_NUM),'harmless edit does not restart generation timers');
    $response=(new MatchResponseService())->respond($donor,$request,'fixture');
    (new MatchResponseService())->respond($donor,$request,'fixture_retry');
    check(notices($owner['id'],'match.responded',$request)===1,'duplicate response remains silent');
    (new MatchResponseService())->withdraw($donor,$response['match_id']);
    $response=(new MatchResponseService())->respond($donor,$request,'fixture_re_response');
    (new MatchResponseService())->respond($donor,$request,'fixture_re_response_retry');
    check(notices($owner['id'],'match.responded',$request)===2&&notices($owner['id'],'match.withdrawn',$request)===1,'genuine withdrawal/re-response alerts once per transition');
    q("UPDATE blood_requests SET required_blood_type='A+' WHERE id=?",[$request]);$matcher->generateForRequest($request,true,'material_change');
    check(q('SELECT status FROM matches WHERE id=?',[$response['match_id']])->fetchColumn()==='RESPONDED'&&notices($donor['id'],'match.invalidated',$request)===0,'compatible blood change preserves responding offer without invalidation alert');
    check((int)q("SELECT COUNT(*) FROM donor_opportunities WHERE request_id=? AND donor_id=? AND status='pending'",[$request,$donor['id']])->fetchColumn()===0,'response retires all opportunity generations for that request');
    $report=(new DonationService())->submit($donor['id'],$response['match_id'],null);
    check(notices($officer['id'],'donation.reported',$report['id'])===1,'chapter reviewer receives in-app report alert');
    q("UPDATE blood_requests SET required_blood_type='O+' WHERE id=?",[$request]);$matcher->generateForRequest($request,true,'material_change');
    check(q('SELECT status FROM matches WHERE id=?',[$response['match_id']])->fetchColumn()==='RESPONDED'&&q('SELECT status FROM donation_reports WHERE id=?',[$report['id']])->fetchColumn()==='PENDING','invalid pending report preserved');
    check(notices($donor['id'],'match.invalidated',$request)===1&&notices($owner['id'],'match.invalidated',$request)===1,'actual invalidation alerts donor/requester once');
    $matcher->generateForRequest($request,false,'material_change');
    check(notices($donor['id'],'match.invalidated',$request)===1,'repeat invalidation does not duplicate alerts');
    try { (new DonationService())->decide($officer,$report['id'],true);check(false,'invalid offer confirmation blocked'); }
    catch (RuntimeException $e) { check($e->getCode()===409,'invalid offer confirmation blocked'); }
    (new DonationService())->decide($officer,$report['id'],false,'Request changed; offer is incompatible.');
    check(q('SELECT status FROM donation_reports WHERE id=?',[$report['id']])->fetchColumn()==='REJECTED','reasoned rejection remains available');
    check(q('SELECT status FROM matches WHERE id=?',[$response['match_id']])->fetchColumn()==='CLOSED','reasoned rejection closes invalid offer once no pending report remains');
    $other=requestFixture($owner['id']);$matcher->generateForRequest($other,true,'request_created');
    $offer=(new MatchResponseService())->respond($donor,$other,'fixture');q("UPDATE blood_requests SET required_blood_type='O+' WHERE id=?",[$other]);
    $matcher->generateForRequest($other,true,'material_change');check(q('SELECT status FROM matches WHERE id=?',[$offer['match_id']])->fetchColumn()==='CLOSED','invalid responding offer without pending report closes');
    foreach (['urgent','emergency'] as $urgency) {
        $scoped=requestFixture($owner['id']);q('UPDATE blood_requests SET urgency=? WHERE id=?',[$urgency,$scoped]);
        $matcher->generateForRequest($scoped,true,'request_created');$responded=(new MatchResponseService())->respond($donor,$scoped,'fixture');
        $outboxBefore=(int)q("SELECT COUNT(*) FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.related_type='blood_request' AND n.related_id=?",[$scoped])->fetchColumn();
        q("UPDATE blood_requests SET required_blood_type='O+' WHERE id=?",[$scoped]);$matcher->generateForRequest($scoped,true,'material_change');
        check(q('SELECT status FROM matches WHERE id=?',[$responded['match_id']])->fetchColumn()==='CLOSED'&&notices($donor['id'],'match.invalidated',$scoped)===1,"$urgency material invalidation uses same offer rules");
        check((int)q("SELECT COUNT(*) FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.related_type='blood_request' AND n.related_id=?",[$scoped])->fetchColumn()===$outboxBefore,"$urgency edit adds no email jobs");
    }
    $timeoutDonor=fixture('timeout');$timed=requestFixture($owner['id']);$matcher->generateForRequest($timed,true,'request_created');
    q("UPDATE donor_opportunities SET notified_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 43 HOUR),due_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 HOUR) WHERE request_id=? AND donor_id=?",[$timed,$timeoutDonor['id']]);
    WorkflowLockService::transaction(fn()=>Standby::opportunity($timed,$timeoutDonor['id'],2,(int)q("SELECT id FROM notifications WHERE user_id=? AND type='match.new' AND related_id=?",[$timeoutDonor['id'],$timed])->fetchColumn()));
    $freshRequest=requestFixture($owner['id']);$matcher->generateForRequest($freshRequest,true,'request_created');
    $donationBefore=q('SELECT last_verified_donation_at,donor_availability FROM users WHERE id=?',[$timeoutDonor['id']])->fetch(PDO::FETCH_NUM);
    check(Standby::processDue()===1&&Standby::state($timeoutDonor['id'])['standby_started_at']!==null,'elapsed opportunity creates independent standby');
    check(q('SELECT last_verified_donation_at,donor_availability FROM users WHERE id=?',[$timeoutDonor['id']])->fetch(PDO::FETCH_NUM)===$donationBefore,'standby does not overwrite donation anchor or stored availability');
    check(Standby::processDue()===0,'repeat detection does not duplicate standby transition');
    check(Standby::acknowledge($timeoutDonor['id'])&& !Standby::acknowledge($timeoutDonor['id']),'explicit acknowledgment is idempotent');
    check(q('SELECT status FROM donor_opportunities WHERE request_id=? AND donor_id=? AND generation=1',[$timed,$timeoutDonor['id']])->fetchColumn()==='acknowledged'
        &&q('SELECT status FROM donor_opportunities WHERE request_id=? AND donor_id=? AND generation=2',[$timed,$timeoutDonor['id']])->fetchColumn()==='pending','due-at watermark retires elapsed generation and preserves fresh generation');
    check(q('SELECT status FROM donor_opportunities WHERE request_id=? AND donor_id=?',[$freshRequest,$timeoutDonor['id']])->fetchColumn()==='pending','acknowledgment preserves fresh opportunities on another request');
    check(Standby::processDue()===0,'acknowledged timeout cannot immediately retrigger');
    q("UPDATE donor_opportunities SET due_at=DATE_ADD((SELECT consumed_through FROM donor_response_state WHERE user_id=?),INTERVAL 1 MICROSECOND) WHERE request_id=? AND donor_id=? AND generation=2",[$timeoutDonor['id'],$timed,$timeoutDonor['id']]);
    check(Standby::processDue()===1,'fresh timer after watermark can later create standby');
    Standby::acknowledge($timeoutDonor['id']);
    $closedDonor=fixture('closed-timer');$closed=requestFixture($owner['id']);$matcher->generateForRequest($closed,true,'request_created');
    q("UPDATE donor_opportunities SET due_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 HOUR) WHERE request_id=? AND donor_id=?",[$closed,$closedDonor['id']]);
    q("UPDATE blood_requests SET status='CANCELLED' WHERE id=?",[$closed]);check(Standby::processDue()===0,'closed requests excluded from non-response');
    q("UPDATE blood_requests SET status='OPEN',needed_datetime=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE id=?",[$closed]);check(Standby::processDue()===0,'overdue OPEN requests excluded from non-response');
    AccountLifecycleService::change($admin['id'],$donor['id'],['chapter_id'=>2]);
    AccountLifecycleService::change($admin['id'],$donor['id'],['chapter_id'=>2]);
    check(notices($donor['id'],'account.status_changed',$donor['id'])===1,'duplicate account change creates one in-app alert');
    $resubmit=fixture('resubmit');q("UPDATE users SET verification_status='rejected' WHERE id=?",[$resubmit['id']]);
    q("INSERT INTO member_documents(user_id,doc_type,stored_name,mime_type,size_bytes) VALUES (?,'national_id',?,'image/png',1)",[$resubmit['id'],bin2hex(random_bytes(32))]);
    $resubmit=(new UserRepository())->findById($resubmit['id']);
    $pdo->beginTransaction();(new \BloodMatch\Services\VerificationService())->resubmit($resubmit);$pdo->rollBack();
    check(q('SELECT verification_status FROM users WHERE id=?',[$resubmit['id']])->fetchColumn()==='rejected'&&notices($resubmit['id'],'verification.resubmitted',$resubmit['id'])===0,'resubmission notification/status rollback together');
    (new \BloodMatch\Services\VerificationService())->resubmit($resubmit);
    check(notices($resubmit['id'],'verification.resubmitted',$resubmit['id'])===1&&(int)q('SELECT COUNT(*) FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.user_id=?',[$resubmit['id']])->fetchColumn()===0,'resubmission acknowledgment is in-app only');
    try { (new \BloodMatch\Services\VerificationService())->resubmit($resubmit);check(false,'repeat resubmission denied'); }
    catch (RuntimeException $e) { check($e->getCode()===409&&notices($resubmit['id'],'verification.resubmitted',$resubmit['id'])===1,'repeat resubmission creates no duplicate alert'); }
    $bulk=[];for($i=0;$i<101;$i++) { $bulk[]=requestFixture($owner['id']); }
    $refreshed=$matcher->refreshMatchesForDonor($donor['id'],'fixture_refresh');
    check(array_diff($bulk,$refreshed)===[],'donor reconciliation drains more than 100 active requests in bounded batches');
    check($attempts===q('SELECT * FROM email_attempts ORDER BY id')->fetchAll(PDO::FETCH_NUM)&&\PHPMailer\PHPMailer\PHPMailer::$calls===0,'no transport or accounting attempts in lifecycle suite');
    echo "$passed safety follow-up database checks passed\n";
} catch (Throwable $e) { $failed=true;fwrite(STDERR,$e instanceof LogicException?$e->getMessage().PHP_EOL:"Safety follow-up failed; raw error withheld.\n"); }
finally {
    if ($pdo instanceof PDO) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        foreach ($requests as $id) {
            q("DELETE n FROM notifications n JOIN donation_reports dr ON n.related_type='donation_report' AND n.related_id=dr.id JOIN matches m ON m.id=dr.match_id WHERE m.request_id=?",[$id]);
            q("DELETE FROM notifications WHERE related_type='blood_request' AND related_id=?",[$id]);q('DELETE FROM blood_requests WHERE id=?',[$id]);
        }
        foreach ($users as $id) { q('DELETE FROM users WHERE id=?',[$id]); }
        echo "Exact fixtures removed; audit history retained.\n";
    }
}
exit($failed?1:0);
