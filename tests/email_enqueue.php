<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/backend/src/autoload.php';
require BASE_PATH . '/database/email_checkpoint_support.php';
require __DIR__ . '/helpers/fake_mail_transport.php';

use BloodMatch\Config\Database;
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Repositories\UserRepository;
use BloodMatch\Services\NotificationService as Notify;
use BloodMatch\Services\VerificationService;
use BloodMatch\Services\MatchService;
use BloodMatch\Services\Exceptions\EmailEnqueueException;

ini_set('display_errors', '0');
$pdo = null; $users = []; $requests = []; $passed = 0; $workers = []; $token = bin2hex(random_bytes(8));
function q(string $sql, array $params = []): PDOStatement {
    $s = Database::pdo()->prepare($sql); $s->execute($params); return $s;
}
function check(bool $ok, string $label): void {
    global $passed;
    if (!$ok) { throw new LogicException('FAIL ' . $label); }
    $passed++; echo 'PASS ' . $label . PHP_EOL;
}
function fixtureUser(string $label, string $role = 'member', string $status = 'verified', int $chapter = 1): array {
    global $users, $token;
    q("INSERT INTO users (email,first_name,last_name,role,chapter_id,verification_status,date_of_birth,
        blood_type,blood_type_source,donor_enrolled_at,donor_availability,email_verified_at,latitude,longitude)
        VALUES (?,'Enqueue','Fixture',?, ?,?,'1990-01-02','O-','self_reported',UTC_TIMESTAMP(),'available',UTC_TIMESTAMP(),14.4,120.5)",
        ["enqueue-$token-$label@example.test", $role, $chapter, $status]);
    $id = (int) Database::pdo()->lastInsertId(); $users[] = $id;
    return (new UserRepository())->findById($id);
}
function memberDocument(int $id): void {
    q("INSERT INTO member_documents (user_id,doc_type,stored_name,mime_type,size_bytes) VALUES (?,'national_id',?,'image/png',1)", [$id, bin2hex(random_bytes(32))]);
}
function event(int $id, string $key, string $type = 'verification.decision'): ?int {
    return Notify::notify($id, $type, 'Synthetic', 'Synthetic', ['dedup_key' => $key, 'generation' => 0,
        'related_type' => 'verification', 'related_id' => $id, 'email' => Notify::EMAIL_NORMAL]);
}
function jobs(int $user): int {
    return (int) q('SELECT COUNT(*) FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.user_id=?', [$user])->fetchColumn();
}
function notifications(int $user): int { return (int) q('SELECT COUNT(*) FROM notifications WHERE user_id=?', [$user])->fetchColumn(); }
function requestFixture(int $owner, string $urgency): int {
    global $requests, $token;
    $id = (new BloodRequestRepository())->create(['requester_id' => $owner, 'request_chapter_id' => 1,
        'required_blood_type' => 'O+', 'quantity_units' => 1, 'facility_name' => "Enqueue $token", 'hospital_id' => null,
        'location_id' => null, 'latitude' => 14.6, 'longitude' => 120.5, 'urgency' => $urgency,
        'needed_datetime' => gmdate('Y-m-d H:i:s', time() + 3600), 'review_status' => 'not_required']);
    $requests[] = $id; return $id;
}
function race(string $operation, int $actor, int $target, string $key): array {
    global $workers;
    $workers = []; $outcomes = []; $start = microtime(true) + 1;
    for ($i = 0; $i < 2; $i++) {
        $pipes = [];
        $proc = proc_open([PHP_BINARY, __FILE__, '--worker', $operation, (string) $actor, (string) $target, $key, (string) $start],
            [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, BASE_PATH);
        if (!is_resource($proc)) { throw new LogicException('Unable to start workers.'); }
        fclose($pipes[0]); $workers[] = [$proc, $pipes];
    }
    $bad = false;
    foreach ($workers as [$proc, $pipes]) {
        $out = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($proc); $r = json_decode($out, true);
        if ($code !== 0 || !is_array($r)) { $bad = true; } else { $outcomes[] = $r; }
    }
    $workers = [];
    if ($bad) { throw new LogicException('Worker failed; raw errors withheld.'); }
    return $outcomes;
}
// Force an outbox INSERT failure using a conflicting delivery key; no DDL,
// triggers, production test hooks or table renaming. Exclusive test writers required.
function poisonNextDelivery(int $user): int {
    global $token;
    $poison = event($user, "poison:$token:" . bin2hex(random_bytes(4)));
    $next = (int) q("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications'")->fetchColumn();
    if ($next <= $poison) { throw new LogicException('Cannot determine next fixture notification ID.'); }
    q('UPDATE email_outbox SET delivery_key=? WHERE notification_id=?', ['notification:' . $next . ':email', $poison]);
    return $poison;
}
try {
    $pdo = emailCheckpointTarget();
    // No network path, even if accidentally called. Queueing must not consume the ledger.
    $_ENV['MAIL_HOST'] = 'fake-no-sockets'; \PHPMailer\PHPMailer\PHPMailer::$fail = true;
    if (($argv[1] ?? '') === '--worker') {
        $until = (float) ($argv[6] ?? 0);
        if ($until > microtime(true) + 5) { throw new LogicException('Invalid worker gate.'); }
        while (microtime(true) < $until) { usleep(1000); }
        try {
            $actor = (new UserRepository())->findById((int) $argv[3]);
            $result = $argv[2] === 'notify' ? event((int) $argv[4], $argv[5])
                : (new VerificationService())->decide($actor, (int) $argv[4], 'rejected', 'Synthetic review', false);
            echo json_encode(['status' => 200, 'created' => $result !== null]);
        } catch (Throwable $e) { echo json_encode(['status' => $e->getCode() ?: 500]); }
        exit(0);
    }
    if (count($argv) !== 1 || !function_exists('proc_open')) { throw new LogicException('No arguments and proc_open are required.'); }
    check(emailCheckpointShape($pdo) === ['email_outbox' => 'ready','email_attempts' => 'ready'], 'migration 021 shape ready');
    check((int) q('SELECT COUNT(*) FROM schema_migrations WHERE name=?', ['021_email_outbox_accounting.sql'])->fetchColumn() === 1, '021 recorded');
    $ledgerBefore = q('SELECT id,mail_class,attempted_at FROM email_attempts ORDER BY id')->fetchAll(PDO::FETCH_NUM);
    $donationsBefore = q('SELECT id,status,rejection_reason FROM donation_reports ORDER BY id')->fetchAll(PDO::FETCH_NUM);
    $officer = fixtureUser('officer', 'officer');
    $member = fixtureUser('member');
    $concurrent = fixtureUser('concurrent');
    $review = fixtureUser('review', 'member', 'pending');
    $failedReview = fixtureUser('failed-review', 'member', 'pending');
    $approved = fixtureUser('approved', 'member', 'pending'); memberDocument((int) $approved['id']);
    $donor = fixtureUser('cross-chapter', 'member', 'verified', 2);
    $ineligible = fixtureUser('ineligible', 'member', 'pending', 2);
    $id = (int) $member['id'];
    $n = event($id, "duplicate:$token");
    check($n !== null && jobs($id) === 1, 'new membership event commits notification and job');
    $job = q('SELECT priority,status,attempts,lease_token,sent_at FROM email_outbox WHERE notification_id=?', [$n])->fetch(PDO::FETCH_ASSOC);
    check($job['priority'] === 'normal' && $job['status'] === 'pending' && (int) $job['attempts'] === 0
        && $job['lease_token'] === null && $job['sent_at'] === null, 'enqueue sets normal priority without attempts/leases/delivery claim');
    check(event($id, "duplicate:$token") === null && notifications($id) === 1 && jobs($id) === 1, 'duplicate event creates no extra notification/job');
    $r = race('notify', (int) $officer['id'], (int) $concurrent['id'], "concurrent:$token");
    check(count(array_filter($r, fn ($x) => $x['status'] === 200)) === 2 && count(array_filter($r, fn ($x) => $x['created'] ?? false)) === 1
        && notifications((int) $concurrent['id']) === 1 && jobs((int) $concurrent['id']) === 1, 'concurrent duplicate creates exactly one job');
    $pdo->beginTransaction(); event($id, "rollback:$token"); $pdo->rollBack();
    check(notifications($id) === 1 && jobs($id) === 1, 'outer rollback leaves neither new notification nor job');
    $poison = poisonNextDelivery($id); $beforeN = notifications($id); $beforeJ = jobs($id); $caught = false;
    $pdo->beginTransaction();
    try { event($id, "failure:$token"); } catch (EmailEnqueueException $e) { $caught = $e->getCode() === 503; }
    $pdo->commit();
    check($caught && notifications($id) === $beforeN && jobs($id) === $beforeJ, 'caught nested enqueue failure returns 503 and leaves no orphan even if owner commits');
    q('DELETE FROM notifications WHERE id=? AND user_id=?', [$poison, $id]);
    $unknown = false;
    try { event(0, "invalid:$token"); } catch (EmailEnqueueException $e) { $unknown = true; }
    check($unknown, 'notification FK failure is not mistaken for duplicate success');
    foreach (['account.status_changed','donation.confirmed','donation.rejected','match.responded','verification.resubmitted','request.expired','request.cancelled','request.fulfilled'] as $type) {
        $before = jobs($id); event($id, "excluded:$type:$token", $type);
        check(jobs($id) === $before, "$type remains in-app only");
    }
    q('UPDATE users SET email_verified_at=NULL WHERE id=?', [$id]);
    $before = jobs($id); event($id, "unverified:$token");
    check(jobs($id) === $before, 'unverified address receives in-app only');
    $r = race('decide', (int) $officer['id'], (int) $review['id'], $token);
    $statuses = array_column($r, 'status'); sort($statuses);
    check($statuses === [200,409], 'concurrent review has one decision and one stale-state conflict');
    check((int) q('SELECT COUNT(*) FROM verification_decisions WHERE target_user_id=?', [$review['id']])->fetchColumn() === 1
        && jobs((int) $review['id']) === 1 && notifications((int) $review['id']) === 1, 'review history/state/notification/job commit together once');
    $poison = poisonNextDelivery((int) $failedReview['id']); $failed = false;
    try { (new VerificationService())->decide($officer, (int) $failedReview['id'], 'rejected', 'Synthetic', false); }
    catch (EmailEnqueueException $e) { $failed = $e->getCode() === 503; }
    check($failed && q('SELECT verification_status FROM users WHERE id=?', [$failedReview['id']])->fetchColumn() === 'pending'
        && (int) q('SELECT COUNT(*) FROM verification_decisions WHERE target_user_id=?', [$failedReview['id']])->fetchColumn() === 0
        && jobs((int) $failedReview['id']) === 1 && notifications((int) $failedReview['id']) === 1, 'enqueue failure rolls back verification state/history/notification/job');
    check((int) q("SELECT COUNT(*) FROM audit_log WHERE target_type='user' AND target_id=? AND action='verification.rejected'", [(string) $failedReview['id']])->fetchColumn() === 0, 'failed decision audit rolls back with event');
    q('DELETE FROM notifications WHERE id=? AND user_id=?', [$poison, $failedReview['id']]);
    $poison = poisonNextDelivery((int) $failedReview['id']); $failed = false;
    $pdo->beginTransaction();
    try { (new VerificationService())->decide($officer, (int) $failedReview['id'], 'rejected', 'Synthetic', false); }
    catch (EmailEnqueueException $e) { $failed = true; }
    $pdo->commit();
    check($failed && q('SELECT verification_status FROM users WHERE id=?', [$failedReview['id']])->fetchColumn() === 'pending'
        && (int) q('SELECT COUNT(*) FROM verification_decisions WHERE target_user_id=?', [$failedReview['id']])->fetchColumn() === 0,
        'caught nested verification enqueue failure rolls back business state/history before caller commits');
    q('DELETE FROM notifications WHERE id=? AND user_id=?', [$poison, $failedReview['id']]);
    $pdo->beginTransaction(); (new VerificationService())->decide($officer, (int) $failedReview['id'], 'rejected', 'Synthetic', false); $pdo->rollBack();
    check(q('SELECT verification_status FROM users WHERE id=?', [$failedReview['id']])->fetchColumn() === 'pending'
        && notifications((int) $failedReview['id']) === 0 && jobs((int) $failedReview['id']) === 0, 'caller rollback removes verification event and outbox');
    // Avoid post-commit reconciliation against preexisting requests during this fixture.
    $pdo->beginTransaction(); (new VerificationService())->decide($officer, (int) $approved['id'], 'verified', null, false); $pdo->commit();
    check(q('SELECT verification_status FROM users WHERE id=?', [$approved['id']])->fetchColumn() === 'verified'
        && jobs((int) $approved['id']) === 1, 'approved membership decision persists with job');
    $wrong = fixtureUser('wrong-officer','officer','verified',2); $denied = false;
    try { (new VerificationService())->decide($wrong, (int) $failedReview['id'], 'rejected', 'Synthetic', false); }
    catch (RuntimeException $e) { $denied = $e->getCode() === 403; }
    check($denied && notifications((int) $failedReview['id']) === 0, 'fresh chapter authorization retained');
    q("UPDATE users SET role='member' WHERE id=?", [$officer['id']]); $denied = false;
    try { (new VerificationService())->decide($officer, (int) $failedReview['id'], 'rejected', 'Synthetic', false); }
    catch (RuntimeException $e) { $denied = $e->getCode() === 403; }
    q("UPDATE users SET role='officer' WHERE id=?", [$officer['id']]);
    check($denied && notifications((int) $failedReview['id']) === 0, 'stale actor snapshot cannot bypass current role');
    check((int) q("SELECT COUNT(*) FROM audit_log WHERE actor_id=? AND action='authz.denied'", [$officer['id']])->fetchColumn() >= 1, 'authorization denial audit retained after rollback');
    $emergency = requestFixture((int) $officer['id'], 'emergency');
    (new MatchService())->generateForRequest($emergency, true, 'request_created', (int) $officer['id']);
    check((int) q("SELECT COUNT(*) FROM email_outbox e JOIN notifications n ON n.id=e.notification_id
        WHERE n.related_type='blood_request' AND n.related_id=? AND n.user_id=? AND e.priority='emergency'", [$emergency,$donor['id']])->fetchColumn() === 1,
        'eligible compatible cross-chapter donor opportunity queues');
    check((int) q("SELECT COUNT(*) FROM email_outbox e JOIN notifications n ON n.id=e.notification_id
        JOIN blood_requests r ON r.id=n.related_id WHERE n.related_type='blood_request' AND r.id=? AND e.expires_at<>r.needed_datetime", [$emergency])->fetchColumn() === 0,
        'Emergency opportunity expiry references current request deadline');
    check((int) q("SELECT COUNT(*) FROM notifications WHERE related_type='blood_request' AND related_id=? AND user_id=?", [$emergency,$ineligible['id']])->fetchColumn() === 0, 'ineligible donor receives no opportunity');
    $before = (int) q("SELECT COUNT(*) FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.related_type='blood_request' AND n.related_id=?", [$emergency])->fetchColumn();
    (new MatchService())->generateForRequest($emergency, false, 'request_created', (int) $officer['id']);
    check((int) q("SELECT COUNT(*) FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.related_type='blood_request' AND n.related_id=?", [$emergency])->fetchColumn() === $before, 'same-generation Emergency retry creates no jobs');
    (new MatchService())->generateForRequest($emergency, true, 'request_updated', (int) $officer['id']);
    check((int) q("SELECT COUNT(*) FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.related_type='blood_request' AND n.related_id=?", [$emergency])->fetchColumn() === $before, 'material rematch does not add email');
    foreach (['routine','urgent'] as $urgency) {
        $request = requestFixture((int) $officer['id'], $urgency);
        (new MatchService())->generateForRequest($request,true,'request_created',(int) $officer['id']);
        check((int) q("SELECT COUNT(*) FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.related_type='blood_request' AND n.related_id=?", [$request])->fetchColumn() === 0, "$urgency creates no email jobs");
    }
    $rollbackRequest = requestFixture((int) $officer['id'], 'emergency');
    $pdo->beginTransaction(); (new MatchService())->generateForRequest($rollbackRequest,true,'request_created',(int) $officer['id']); $pdo->rollBack();
    check((int) q('SELECT COUNT(*) FROM matches WHERE request_id=?', [$rollbackRequest])->fetchColumn() === 0
        && (int) q("SELECT COUNT(*) FROM notifications WHERE related_type='blood_request' AND related_id=?", [$rollbackRequest])->fetchColumn() === 0, 'rolled-back match generation leaves no offers/notification/jobs');
    $failedRequest = requestFixture((int) $officer['id'], 'emergency');
    $poison = poisonNextDelivery((int) $failedReview['id']); $failed = false;
    try { (new MatchService())->generateForRequest($failedRequest,true,'request_created',(int) $officer['id']); }
    catch (EmailEnqueueException $e) { $failed = $e->getCode() === 503; }
    check($failed && (int) q('SELECT COUNT(*) FROM matches WHERE request_id=?', [$failedRequest])->fetchColumn() === 0
        && (int) q("SELECT COUNT(*) FROM notifications WHERE related_type='blood_request' AND related_id=?", [$failedRequest])->fetchColumn() === 0,
        'Emergency enqueue failure propagates and rolls back matching/notification/jobs');
    q('DELETE FROM notifications WHERE id=? AND user_id=?', [$poison, $failedReview['id']]);
    check($ledgerBefore === q('SELECT id,mail_class,attempted_at FROM email_attempts ORDER BY id')->fetchAll(PDO::FETCH_NUM), 'enqueue consumes no transport budget');
    check(\PHPMailer\PHPMailer\PHPMailer::$calls === 0, 'no handler calls SMTP transport');
    check($donationsBefore === q('SELECT id,status,rejection_reason FROM donation_reports ORDER BY id')->fetchAll(PDO::FETCH_NUM), 'donation history unchanged');
    echo "$passed database enqueue checks passed\n";
} catch (Throwable $e) {
    fwrite(STDERR, ($e instanceof LogicException ? $e->getMessage() : 'Enqueue DB checkpoint failed; raw error withheld.') . PHP_EOL); $failed = true;
} finally {
    foreach ($workers as [$proc,$pipes]) {
        foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
        if (is_resource($proc)) { proc_close($proc); }
    }
    if ($pdo instanceof PDO && ($argv[1] ?? '') !== '--worker') {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        foreach ($requests as $request) {
            q("DELETE FROM notifications WHERE related_type='blood_request' AND related_id=?", [$request]);
            q('DELETE FROM blood_requests WHERE id=?', [$request]);
        }
        foreach ($users as $user) { q('DELETE FROM users WHERE id=?', [$user]); }
        echo "Exact fixture requests/users removed; notification/outbox children cleaned; audit history retained.\n";
    }
}
exit(isset($failed) ? 1 : 0);
