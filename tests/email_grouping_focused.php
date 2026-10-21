<?php
declare(strict_types=1);
// Focused fake-only integration check. Preserves pre-existing email accounting.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH.'/backend/src/autoload.php';
require BASE_PATH.'/database/email_checkpoint_support.php';
use BloodMatch\Config\Database;
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Services\NotificationService;
use BloodMatch\Services\TestEmailWorker;
$users=[]; $requests=[]; $donor=null; $pdo=null; $passed=0; $messages=[]; $baseline=[];
function sql(string $query,array $args=[]): PDOStatement { $s=Database::pdo()->prepare($query); $s->execute($args); return $s; }
function check(bool $condition,string $label): void { global $passed; if (!$condition) throw new LogicException('FAIL '.$label); ++$passed; echo 'PASS '.$label.PHP_EOL; }
function fixtureUser(string $suffix): int {
    global $users,$token;
    sql("INSERT INTO users(email,first_name,last_name,role,chapter_id,verification_status,date_of_birth,blood_type,blood_type_source,donor_enrolled_at,donor_availability,email_verified_at) VALUES (?,'Grouping','Fixture','member',1,'verified','1990-01-02','O-','self_reported',UTC_TIMESTAMP(),'available',UTC_TIMESTAMP())",["group-$token-$suffix@example.test"]);
    $id=(int)Database::pdo()->lastInsertId(); $users[]=$id; return $id;
}
function opportunity(int $owner,int $donor): int {
    global $requests,$token;
    $id=(new BloodRequestRepository())->create(['requester_id'=>$owner,'request_chapter_id'=>1,'required_blood_type'=>'O+','quantity_units'=>1,'facility_name'=>"Grouping $token",'hospital_id'=>null,'location_id'=>null,'latitude'=>null,'longitude'=>null,'urgency'=>'emergency','needed_datetime'=>gmdate('Y-m-d H:i:s',time()+3600),'review_status'=>'not_required']);
    $requests[]=$id;
    sql("INSERT INTO matches(request_id,donor_id,generation,status) VALUES (?,?,1,'NOTIFIED')",[$id,$donor]);
    NotificationService::notifyMatchGeneration(sql('SELECT * FROM blood_requests WHERE id=?',[$id])->fetch(PDO::FETCH_ASSOC),1,[$donor],true);
    return (int)sql("SELECT e.id FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.related_type='blood_request' AND n.related_id=? AND n.user_id=?",[$id,$donor])->fetchColumn();
}
function job(int $id): array { return sql('SELECT * FROM email_outbox WHERE id=?',[$id])->fetch(PDO::FETCH_ASSOC); }
function mature(array $ids): void { foreach ($ids as $id) sql('UPDATE email_outbox SET created_at=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 6 MINUTE),next_attempt_at=UTC_TIMESTAMP(6) WHERE id=?',[$id]); }
try {
    $_ENV['MAIL_HOST']=''; putenv('MAIL_HOST='); $pdo=emailCheckpointTarget();
    $baseline=sql('SELECT * FROM email_attempts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $token=bin2hex(random_bytes(8)); $one=fixtureUser('requester-one'); $two=fixtureUser('requester-two'); $donor=fixtureUser('donor');
    $worker=new TestEmailWorker(function(array $meta): bool { global $messages; $messages[]=$meta; return true; });
    $first=opportunity($one,$donor); $second=opportunity($two,$donor);
    $r=$worker->run([$first,$second]);
    check($r['deferred']===2 && count($messages)===0,'fresh emergency jobs wait without transport');
    check(job($first)['last_error_code']==='digest_grouping' && (int)job($first)['attempts']===0,'five-minute wait consumes no attempt');
    check((bool)sql("SELECT next_attempt_at=DATE_ADD(created_at,INTERVAL 5 MINUTE) FROM email_outbox WHERE id=?",[$first])->fetchColumn(),'retry scheduled at exact five-minute boundary');
    check((int)sql("SELECT COUNT(*) FROM notifications WHERE user_id=? AND type='match.new'",[$donor])->fetchColumn()===2,'both in-app alerts exist during email wait');
    mature([$first,$second]); $r=$worker->run([$first,$second]);
    check($r['sent']===2 && count($messages)===1 && count($messages[0]['job_ids'])===2,'two different requesters grouped into one fake email for same donor');
    check((int)sql('SELECT COUNT(*) FROM email_donor_reservations WHERE donor_id=?',[$donor])->fetchColumn()===1,'grouped delivery reserves only one email slot');
    check(job($first)['status']==='sent' && job($second)['status']==='sent','both grouped jobs recorded sent');
    $worker->run([$first,$second]); check(count($messages)===1,'sent group cannot be delivered twice');
    $third=opportunity($one,$donor); mature([$third]); $worker->run([$third]);
    check(count($messages)===2 && job($third)['status']==='sent','second email allowed within rolling 30 minutes');
    $fourth=opportunity($two,$donor); mature([$fourth]); $r=$worker->run([$fourth]);
    check($r['deferred']===1 && count($messages)===2 && job($fourth)['last_error_code']==='emergency_recipient_limit','third email deferred across requesters within rolling 30 minutes');
    echo "$passed focused database grouping checks passed; fake transport only.".PHP_EOL;
} catch (Throwable $error) { $failed=true; fwrite(STDERR,($error instanceof LogicException ? $error->getMessage() : 'Focused grouping test failed; raw database errors withheld.').PHP_EOL); }
finally {
    if ($pdo instanceof PDO) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($donor !== null) {
            $attempts=sql('SELECT attempt_id FROM email_donor_reservations WHERE donor_id=?',[$donor])->fetchAll(PDO::FETCH_COLUMN);
            sql('DELETE FROM email_donor_reservations WHERE donor_id=?',[$donor]);
            foreach ($attempts as $id) sql('DELETE FROM email_attempts WHERE id=?',[$id]);
        }
        foreach ($requests as $id) { sql("DELETE FROM notifications WHERE related_type='blood_request' AND related_id=?",[$id]); sql('DELETE FROM blood_requests WHERE id=?',[$id]); }
        foreach ($users as $id) sql('DELETE FROM users WHERE id=?',[$id]);
        $remaining=sql('SELECT * FROM email_attempts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($baseline as $row) { if (!in_array($row,$remaining,true)) { $failed=true; fwrite(STDERR,'Pre-existing accounting preservation check failed.'.PHP_EOL); } }
        echo 'Exact fixtures cleaned; pre-existing accounting preserved.'.PHP_EOL;
    }
}
exit(isset($failed)?1:0);
