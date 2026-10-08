<?php

declare(strict_types=1);

// USER-RUN ONLY. Mutates disposable bloodmatch_test fixtures; no reset/migration.
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/backend/src/autoload.php';

use BloodMatch\Config\Database;
use BloodMatch\Config\Env;
use BloodMatch\Http\Session;
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Repositories\DonationReportRepository;
use BloodMatch\Repositories\UserRepository;
use BloodMatch\Services\DonationService;
use BloodMatch\Services\Exceptions\DonorIneligibleException;
use BloodMatch\Services\MatchResponseService;
use BloodMatch\Services\MatchService;

Env::load(BASE_PATH . '/.env');
$pdo = Database::pdo();
if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'bloodmatch_test') {
    fwrite(STDERR, "REFUSED: workflow tests require bloodmatch_test.\n");
    exit(1);
}
Session::start();
if (($argv[1] ?? '') === '--worker') {
    $operation = (string) ($argv[2] ?? '');
    $actorId = (int) ($argv[3] ?? 0);
    $targetId = (int) ($argv[4] ?? 0);
    $_SESSION['user_id'] = $actorId;
    try {
        $actor = (new UserRepository())->findById($actorId);
        if ($actor === null) { throw new RuntimeException('Missing test actor.', 403); }
        $result = match ($operation) {
            'report' => (new DonationService())->submit($actorId, $targetId, 'Atomic fixture'),
            'withdraw' => (new MatchResponseService())->withdraw($actor, $targetId),
            'confirm' => (new DonationService())->decide($actor, $targetId, true),
            'generate' => (new MatchService())->generateForRequest($targetId, false, 'manual_rematch', $actorId),
            default => throw new RuntimeException('Unknown test operation.', 400),
        };
        echo json_encode(['status' => $operation === 'report' ? 201 : 200, 'result' => $result]);
        exit(0);
    } catch (Throwable $e) {
        $status = (int) $e->getCode();
        echo json_encode(['status' => $status >= 400 && $status <= 499 ? $status : 500,
            'error_class' => get_class($e)]);
        exit(0);
    }
}

$passed = 0;
$failed = 0;
$userIds = [];
$requestIds = [];
$token = bin2hex(random_bytes(6));
$hash = password_hash('WorkflowTest123!', PASSWORD_BCRYPT);
function check(string $label, bool $ok): void {
    global $passed, $failed;
    if ($ok) { $passed++; echo 'PASS ' . $label . PHP_EOL; }
    else { $failed++; echo 'FAIL ' . $label . PHP_EOL; }
}
function scalar(string $sql, array $args): mixed {
    $stmt = Database::pdo()->prepare($sql); $stmt->execute($args); return $stmt->fetchColumn();
}
function write(string $sql, array $args): void {
    $stmt = Database::pdo()->prepare($sql); $stmt->execute($args);
}
function user(string $label, string $role = 'member', bool $enrolled = true): array {
    global $pdo, $userIds, $token, $hash;
    $email = "workflow-{$token}-{$label}@example.test";
    $stmt = $pdo->prepare("INSERT INTO users
        (email,password_hash,first_name,last_name,role,chapter_id,verification_status,account_status,
         date_of_birth,blood_type,blood_type_source,donor_enrolled_at,donor_availability,email_verified_at)
        VALUES (?,?,'Workflow','Fixture',?,1,'verified','active','1990-01-02','O-','self_reported',?,?,UTC_TIMESTAMP())");
    $stmt->execute([$email, $hash, $role, $enrolled ? gmdate('Y-m-d H:i:s') : null, $enrolled ? 'available' : null]);
    $id = (int) $pdo->lastInsertId(); $userIds[] = $id;
    return (new UserRepository())->findById($id);
}
function requestFor(array $requester, int $quantity = 2): int {
    global $requestIds, $token;
    $id = (new BloodRequestRepository())->create([
        'requester_id' => (int) $requester['id'], 'request_chapter_id' => 1,
        'required_blood_type' => 'O+', 'quantity_units' => $quantity,
        'facility_name' => 'Workflow ' . $token, 'hospital_id' => null, 'location_id' => null,
        'latitude' => null, 'longitude' => null, 'urgency' => 'routine',
        'needed_datetime' => gmdate('Y-m-d H:i:s', time() + 86400), 'review_status' => 'not_required',
    ]);
    $requestIds[] = $id; return $id;
}
function offer(array $donor, int $requestId): int {
    return (int) (new MatchResponseService())->respond($donor, $requestId, 'atomicity_fixture')['match_id'];
}
function blocked(callable $work, int $status): bool {
    try { $work(); } catch (Throwable $e) { return (int) $e->getCode() === $status; }
    return false;
}
function race(array $jobs): array {
    $running = [];
    foreach ($jobs as [$operation, $actorId, $targetId]) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $operation, (string) $actorId, (string) $targetId],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH);
        if (!is_resource($process)) { throw new RuntimeException('Could not launch parallel PHP test worker.'); }
        fclose($pipes[0]); $running[] = [$process, $pipes];
    }
    $results = [];
    foreach ($running as [$process, $pipes]) {
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
        $result = json_decode($out, true);
        if ($exit !== 0 || !is_array($result)) { throw new RuntimeException('Invalid PHP worker result (no credentials printed).'); }
        $results[] = $result;
    }
    return $results;
}

try {
    $requester = user('requester', 'member', false);
    $officer = user('officer', 'officer', false);
    $_SESSION['user_id'] = (int) $officer['id'];
    $stale = user('stale'); $requestId = requestFor($requester);
    write('UPDATE users SET donor_availability = ? WHERE id = ?', ['unavailable', $stale['id']]);
    try { offer($stale, $requestId); check('W01 stale availability snapshot denied', false); }
    catch (DonorIneligibleException $e) { check('W01 stale availability snapshot denied', $e->eligibility()['code'] === 'unavailable'); }
    write('UPDATE users SET donor_availability = ?, date_of_birth = NULL WHERE id = ?', ['available', $stale['id']]);
    try { offer($stale, $requestId); check('W02 stale DOB snapshot denied', false); }
    catch (DonorIneligibleException $e) { check('W02 stale DOB snapshot denied', $e->eligibility()['code'] === 'age_ineligible'); }
    check('W03 rejected snapshots persist no match', (int) scalar('SELECT COUNT(*) FROM matches WHERE request_id = ? AND donor_id = ?', [$requestId, $stale['id']]) === 0);

    $donor = user('duplicate'); $req = requestFor($requester); $match = offer($donor, $req);
    $results = race([['report', $donor['id'], $match], ['report', $donor['id'], $match]]);
    $statuses = array_column($results, 'status'); sort($statuses);
    check('W04 concurrent reports have one success and one conflict', $statuses === [201, 409]);
    check('W05 only one pending report persisted', (int) scalar("SELECT COUNT(*) FROM donation_reports WHERE match_id = ? AND status = 'PENDING'", [$match]) === 1);
    check('W06 pending report prevents withdrawal', blocked(fn () => (new MatchResponseService())->withdraw($donor, $match), 409));

    $donor2 = user('withdraw-race'); $req2 = requestFor($requester); $match2 = offer($donor2, $req2);
    $results = race([['report', $donor2['id'], $match2], ['withdraw', $donor2['id'], $match2]]);
    $pending = (int) scalar("SELECT COUNT(*) FROM donation_reports WHERE match_id = ? AND status = 'PENDING'", [$match2]);
    $state = (string) scalar('SELECT status FROM matches WHERE id = ?', [$match2]);
    check('W07 report/withdraw race keeps a valid pair of states', ($pending === 1 && $state === 'RESPONDED') || ($pending === 0 && $state === 'NOTIFIED'));
    check('W08 report/withdraw workers return expected success/conflict only', count(array_filter(array_column($results, 'status'), fn ($s) => in_array($s, [200, 201], true))) === 1
        && count(array_filter(array_column($results, 'status'), fn ($s) => $s === 409)) === 1);

    $report = (int) scalar("SELECT id FROM donation_reports WHERE match_id = ? AND status = 'PENDING'", [$match]);
    // Model existing legacy duplicates without applying a new constraint/migration.
    $duplicateReport = (new DonationReportRepository())->insert($match, (int) $donor['id'], 'Legacy duplicate fixture', gmdate('Y-m-d H:i:s'));
    (new DonationService())->decide($officer, $report, true);
    $anchor = scalar('SELECT last_verified_donation_at FROM users WHERE id = ?', [$donor['id']]);
    check('W09 legacy second pending report cannot reconfirm completed match', blocked(fn () => (new DonationService())->decide($officer, $duplicateReport, true), 409));
    check('W10 blocked reconfirmation preserves cooldown anchor and pending history', scalar('SELECT last_verified_donation_at FROM users WHERE id = ?', [$donor['id']]) === $anchor
        && scalar('SELECT status FROM donation_reports WHERE id = ?', [$duplicateReport]) === 'PENDING');
    $otherRequest = requestFor($requester);
    try { offer($donor, $otherRequest); check('W11 confirmation safety window rejects stale donor on another request', false); }
    catch (DonorIneligibleException $e) { check('W11 confirmation safety window rejects stale donor on another request', in_array($e->eligibility()['code'], ['standby', 'cooldown'], true)); }

    $a = user('quota-a'); $b = user('quota-b'); $quotaRequest = requestFor($requester, 2);
    $ma = offer($a, $quotaRequest); $mb = offer($b, $quotaRequest);
    $ra = (new DonationService())->submit((int) $a['id'], $ma, null)['id'];
    $rb = (new DonationService())->submit((int) $b['id'], $mb, null)['id'];
    $results = race([['confirm', $officer['id'], $ra], ['confirm', $officer['id'], $rb]]);
    check('W12 parallel legitimate confirmations both complete', array_column($results, 'status') === [200, 200]);
    check('W13 quota fulfillment is consistent', scalar('SELECT status FROM blood_requests WHERE id = ?', [$quotaRequest]) === 'FULFILLED'
        && (int) scalar("SELECT COUNT(*) FROM matches WHERE request_id = ? AND status = 'COMPLETED'", [$quotaRequest]) === 2);

    $regenDonor = user('generation'); $regenRequest = requestFor($requester, 2); $regenMatch = offer($regenDonor, $regenRequest);
    $regenReport = (new DonationService())->submit((int) $regenDonor['id'], $regenMatch, null)['id'];
    $results = race([['confirm', $officer['id'], $regenReport], ['generate', $officer['id'], $regenRequest]]);
    check('W14 generation cannot undo a completed donation', array_column($results, 'status') === [200, 200]
        && scalar('SELECT status FROM matches WHERE id = ?', [$regenMatch]) === 'COMPLETED');

    foreach (['CANCELLED', 'EXPIRED', 'FULFILLED'] as $closed) {
        $closedDonor = user(strtolower($closed)); $closedRequest = requestFor($requester); $closedMatch = offer($closedDonor, $closedRequest);
        $closedReport = (new DonationService())->submit((int) $closedDonor['id'], $closedMatch, null)['id'];
        write('UPDATE blood_requests SET status = ? WHERE id = ?', [$closed, $closedRequest]);
        check('W15 ' . $closed . ' blocks confirmation without recording donation', blocked(fn () => (new DonationService())->decide($officer, $closedReport, true), 409)
            && scalar('SELECT last_verified_donation_at FROM users WHERE id = ?', [$closedDonor['id']]) === null
            && scalar('SELECT status FROM donation_reports WHERE id = ?', [$closedReport]) === 'PENDING');
    }
} catch (Throwable $e) {
    $failed++; echo 'FAIL workflow test interrupted: ' . get_class($e) . ' at ' . basename($e->getFile()) . ':' . $e->getLine() . PHP_EOL;
} finally {
    if ($requestIds !== []) {
        $ph = implode(',', array_fill(0, count($requestIds), '?'));
        write("DELETE FROM notifications WHERE related_type = 'blood_request' AND related_id IN ($ph)", $requestIds);
        write("DELETE FROM blood_requests WHERE id IN ($ph)", $requestIds);
    }
    if ($userIds !== []) {
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        write("DELETE FROM users WHERE id IN ($ph)", $userIds);
    }
}
echo "== Workflow atomicity: {$passed} passed, {$failed} failed ==\n";
exit($failed > 0 ? 1 : 0);
