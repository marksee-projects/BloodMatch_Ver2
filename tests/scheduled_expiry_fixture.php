<?php

declare(strict_types=1);

// BM-SCHEDULED-EXPIRY-FIXTURE: USER-RUN ONLY; no worker invocation or migration execution.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('log_errors', '0');
ini_set('error_log', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null');
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/backend/src/autoload.php';

use BloodMatch\Config\Database;
use BloodMatch\Config\Env;
use BloodMatch\Http\Session;
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Repositories\DonationReportRepository;
use BloodMatch\Repositories\UserRepository;
use BloodMatch\Services\DonationService;
use BloodMatch\Services\Exceptions\ValidationException;

$action = $argv[1] ?? 'check';
$stage = 'arguments';
$locks = [];
$saved = false;
$session = false;
$manifest = BASE_PATH . '/logs/scheduled-expiry-fixture.json';
$pdo = null;
function sql(string $query, array $args = []): PDOStatement {
    $stmt = Database::pdo()->prepare($query); $stmt->execute($args); return $stmt;
}
function demand(bool $ok, string $label): void {
    if (!$ok) { echo 'FAIL ' . $label . PHP_EOL; throw new RuntimeException($label); }
    echo 'PASS ' . $label . PHP_EOL;
}
function safety(array $ids): array {
    $marks = implode(',', array_fill(0, count($ids), '?'));
    return sql("SELECT id, donor_availability, last_verified_donation_at FROM users WHERE id IN ($marks) ORDER BY id", $ids)->fetchAll();
}
function migrationReady(): array {
    $column = sql("SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'donation_reports' AND COLUMN_NAME = 'rejection_reason'")->fetch();
    $table = sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations'")->fetchColumn();
    $ledger = $table && sql('SELECT COUNT(*) FROM schema_migrations WHERE name = ?', ['020_donation_report_rejection_reason.sql'])->fetchColumn() > 0;
    return ['column_ready' => $column !== false && $column['DATA_TYPE'] === 'varchar'
        && (int) $column['CHARACTER_MAXIMUM_LENGTH'] === 500 && $column['IS_NULLABLE'] === 'YES',
        'migration_020_recorded' => (bool) $ledger];
}
try {
    if (count($argv) > 2 || !in_array($action, ['check', 'setup', 'assert', 'cleanup'], true)) { throw new RuntimeException('arguments'); }
    foreach (['DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_NAME' => 'bloodmatch_test', 'MAIL_HOST' => ''] as $key => $value) {
        putenv($key . '=' . $value); $_ENV[$key] = $value;
    }
    Env::load(BASE_PATH . '/.env');
    $stage = 'connection'; $pdo = Database::pdo();
    $stage = 'target';
    $target = sql('SELECT DATABASE() AS db, @@port AS port')->fetch();
    if ($target['db'] !== 'bloodmatch_test' || (int) $target['port'] !== 3306) { throw new RuntimeException('target'); }
    if ($action === 'check') {
        // SELECT-only: no locks/files/fixtures/ledger updates/session writes.
        $ready = migrationReady();
        echo json_encode(['database' => 'bloodmatch_test', 'port' => 3306] + $ready) . PHP_EOL;
        exit($ready['column_ready'] && $ready['migration_020_recorded'] ? 0 : 1);
    }
    $stage = 'fixture_lock';
    if (!is_dir(dirname($manifest)) && !@mkdir(dirname($manifest), 0700, true)) { throw new RuntimeException('storage'); }
    foreach (['scheduled-expiry-fixture.lock', 'test-expiry.lock'] as $name) {
        $handle = @fopen(BASE_PATH . '/logs/' . $name, 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) { throw new RuntimeException('busy'); }
        $locks[] = $handle;
    }
    if ($action === 'setup') {
        $stage = 'prerequisites';
        if (file_exists($manifest)) { throw new RuntimeException('existing_fixture_cleanup_required'); }
        $ready = migrationReady();
        if (!$ready['column_ready'] || !$ready['migration_020_recorded']) { throw new RuntimeException('migration_020_not_ready'); }
        // Isolate a single expiry effect; never sweep/rewrite preexisting test data here.
        if ((int) sql("SELECT COUNT(*) FROM blood_requests WHERE status = 'OPEN' AND needed_datetime < UTC_TIMESTAMP()")->fetchColumn() !== 0) {
            throw new RuntimeException('preexisting_overdue_requests');
        }
        $chapter = (int) sql('SELECT id FROM chapters ORDER BY id LIMIT 1')->fetchColumn();
        if ($chapter < 1) { throw new RuntimeException('reference_chapter_missing'); }
        $token = bin2hex(random_bytes(8));
        $state = ['version' => 1, 'token' => $token, 'chapter' => $chapter, 'users' => [], 'matches' => []];
        $stage = 'setup_transaction'; $pdo->beginTransaction();
        $hash = password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT);
        foreach (['requester', 'officer', 'potential', 'notified', 'responded', 'pending', 'completed'] as $label) {
            sql("INSERT INTO users (email,password_hash,first_name,last_name,role,chapter_id,verification_status,
                account_status,date_of_birth,blood_type,blood_type_source,donor_enrolled_at,donor_availability,email_verified_at)
                VALUES (?,?,'Scheduled','Fixture',?,?,'verified','active','1990-01-02','O-','self_reported',?,?,UTC_TIMESTAMP())",
                ["scheduled-{$token}-{$label}@example.test", $hash, $label === 'officer' ? 'officer' : 'member', $chapter,
                    in_array($label, ['requester', 'officer'], true) ? null : gmdate('Y-m-d H:i:s'),
                    in_array($label, ['requester', 'officer'], true) ? null : 'available']);
            $state['users'][$label] = (int) $pdo->lastInsertId();
        }
        $requestId = (new BloodRequestRepository())->create([
            'requester_id' => $state['users']['requester'], 'request_chapter_id' => $chapter,
            'required_blood_type' => 'O+', 'quantity_units' => 3, 'facility_name' => 'Scheduled expiry ' . $token,
            'hospital_id' => null, 'location_id' => null, 'latitude' => null, 'longitude' => null,
            'urgency' => 'routine', 'needed_datetime' => gmdate('Y-m-d H:i:s', time() - 60), 'review_status' => 'not_required',
        ]);
        $state['request'] = $requestId;
        foreach (['potential' => 'POTENTIAL', 'notified' => 'NOTIFIED', 'responded' => 'RESPONDED', 'pending' => 'RESPONDED', 'completed' => 'COMPLETED'] as $label => $status) {
            sql('INSERT INTO matches (request_id,donor_id,generation,status) VALUES (?,?,1,?)', [$requestId, $state['users'][$label], $status]);
            $state['matches'][$label] = (int) $pdo->lastInsertId();
        }
        $reports = new DonationReportRepository();
        $historic = gmdate('Y-m-d H:i:s', time() - 86400 * 5);
        $state['pending_report'] = $reports->insert($state['matches']['pending'], $state['users']['pending'], 'Scheduled pending fixture', $historic);
        $state['completed_report'] = $reports->insert($state['matches']['completed'], $state['users']['completed'], 'Historical completed fixture', $historic);
        $reports->markConfirmed($state['completed_report'], $state['users']['officer'], $historic);
        sql("UPDATE users SET donor_availability = 'standby', last_verified_donation_at = ? WHERE id = ?", [$historic, $state['users']['completed']]);
        $state['safety'] = safety(array_values($state['users']));
        $state['completed_match_row'] = sql('SELECT * FROM matches WHERE id = ?', [$state['matches']['completed']])->fetch();
        $state['completed_report_row'] = sql('SELECT * FROM donation_reports WHERE id = ?', [$state['completed_report']])->fetch();
        $state['pending_report_row'] = sql('SELECT * FROM donation_reports WHERE id = ?', [$state['pending_report']])->fetch();
        $log = BASE_PATH . '/logs/test-expiry.jsonl';
        clearstatcache(true, $log);
        $state['log_offset'] = file_exists($log) ? filesize($log) : 0;
        $state['setup_utc'] = gmdate('Y-m-d\TH:i:s\Z');
        // Persist exact IDs before commit so interrupted setup remains safely cleanable.
        if (@file_put_contents($manifest, json_encode($state, JSON_THROW_ON_ERROR), LOCK_EX) === false) { throw new RuntimeException('manifest_write'); }
        $saved = true; $pdo->commit();
        echo 'PASS fixture setup: one overdue OPEN request, four unfinished offers, one completed donation, one pending report. Request ID=' . $requestId . PHP_EOL;
        echo 'NEXT install the existing test scheduler; wait at least three minutes; remove it before assertions.' . PHP_EOL;
    } else {
        $stage = 'manifest';
        if (!file_exists($manifest) && $action === 'cleanup') { echo "PASS fixture already absent\n"; exit(0); }
        $state = json_decode((string) file_get_contents($manifest), true, 512, JSON_THROW_ON_ERROR);
        if (($state['version'] ?? null) !== 1 || !preg_match('/^[a-f0-9]{16}$/', $state['token'] ?? '')
            || count($state['users'] ?? []) !== 7 || count($state['matches'] ?? []) !== 5) { throw new RuntimeException('manifest_invalid'); }
        $stage = 'fixture_ownership';
        foreach ($state['users'] as $label => $id) {
            $email = sql('SELECT email FROM users WHERE id = ?', [(int) $id])->fetchColumn();
            if ($email !== false && $email !== "scheduled-{$state['token']}-{$label}@example.test") { throw new RuntimeException('ownership'); }
            if ($email === false && $action === 'assert') { throw new RuntimeException('missing_fixture'); }
        }
        $request = sql('SELECT * FROM blood_requests WHERE id = ?', [$state['request']])->fetch();
        if ($request !== false && ($request['facility_name'] !== 'Scheduled expiry ' . $state['token']
            || (int) $request['requester_id'] !== (int) $state['users']['requester'])) { throw new RuntimeException('ownership'); }
        if ($action === 'cleanup') {
            $stage = 'cleanup'; $pdo->beginTransaction();
            $userIds = array_values($state['users']);
            $marks = implode(',', array_fill(0, count($userIds), '?'));
            // Audit history is append-only. Keep it; the existing actor FK uses ON DELETE SET NULL.
            $auditRows = sql("SELECT id, action, target_type, target_id, context, created_at FROM audit_log WHERE actor_id IN ($marks) ORDER BY id", $userIds)->fetchAll();
            sql("DELETE FROM notifications WHERE related_type = 'blood_request' AND related_id = ?", [$state['request']]);
            sql('DELETE FROM blood_requests WHERE id = ?', [$state['request']]); // fixture offers/reports cascade
            sql("DELETE FROM users WHERE id IN ($marks)", $userIds); // fixture notifications cascade
            demand((int) sql('SELECT COUNT(*) FROM blood_requests WHERE id = ?', [$state['request']])->fetchColumn() === 0
                && (int) sql("SELECT COUNT(*) FROM users WHERE id IN ($marks)", $userIds)->fetchColumn() === 0, 'exact fixture rows removed');
            if ($auditRows !== []) {
                $auditIds = array_column($auditRows, 'id');
                $auditMarks = implode(',', array_fill(0, count($auditIds), '?'));
                demand(sql("SELECT id, action, target_type, target_id, context, created_at FROM audit_log WHERE id IN ($auditMarks) ORDER BY id", $auditIds)->fetchAll() === $auditRows
                    && (int) sql("SELECT COUNT(*) FROM audit_log WHERE id IN ($auditMarks) AND actor_id IS NOT NULL", $auditIds)->fetchColumn() === 0, 'fixture audit history retained with cleared actor references');
            }
            $pdo->commit();
            if (!unlink($manifest)) { throw new RuntimeException('manifest_remove'); }
            echo "PASS cleanup complete; scheduler logs and all audit history retained\n";
        } else {
            $stage = 'scheduled_evidence';
            $log = @fopen(BASE_PATH . '/logs/test-expiry.jsonl', 'rb');
            if ($log === false || fstat($log)['size'] < $state['log_offset']) { throw new RuntimeException('log_missing_or_truncated'); }
            fseek($log, $state['log_offset']); $runs = [];
            while (($line = fgets($log)) !== false) {
                $run = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (($run['invocation'] ?? '') === 'scheduled' && ($run['status'] ?? '') === 'completed'
                    && ($run['database'] ?? '') === 'bloodmatch_test' && ($run['port'] ?? 0) === 3306 && ($run['exit_code'] ?? -1) === 0
                    && ($run['expired_count'] ?? 0) >= 1 && strtotime($run['utc']) >= strtotime($state['setup_utc'])) { $runs[] = $run; }
            }
            fclose($log);
            Session::start(); $session = true; $_SESSION['user_id'] = $state['users']['officer'];
            demand($runs !== [], 'S01 new successful scheduled run expired at least one request');
            $stage = 'closure_assertions';
            demand($request !== false && $request['status'] === 'EXPIRED' && $request['expired_at'] !== null, 'S02 overdue OPEN request became EXPIRED');
            $expiryTime = strtotime($request['expired_at'] . ' UTC');
            demand(count(array_filter($runs, fn ($run) => abs($expiryTime - strtotime($run['utc'])) <= 5 + (int) ceil($run['elapsed_ms'] / 1000))) > 0,
                'S03 expiry cutoff correlates with scheduled run');
            foreach (['potential', 'notified', 'responded', 'pending'] as $label) {
                demand(sql('SELECT status FROM matches WHERE id = ? AND request_id = ?', [$state['matches'][$label], $state['request']])->fetchColumn() === 'CLOSED', 'S04 ' . $label . ' offer CLOSED');
            }
            $reports = new DonationReportRepository();
            demand(sql('SELECT * FROM donation_reports WHERE id = ?', [$state['pending_report']])->fetch() === $state['pending_report_row'], 'S05 pending report unchanged');
            demand(sql('SELECT * FROM matches WHERE id = ?', [$state['matches']['completed']])->fetch() === $state['completed_match_row']
                && sql('SELECT * FROM donation_reports WHERE id = ?', [$state['completed_report']])->fetch() === $state['completed_report_row'], 'S06 completed donation unchanged');
            demand(safety(array_values($state['users'])) === $state['safety'], 'S07 all donation anchors/availability unchanged by expiry');
            $actor = (new UserRepository())->findById($state['users']['officer']);
            $pending = array_values(array_filter($reports->listPendingByChapter($state['chapter']), fn ($row) => (int) $row['id'] === $state['pending_report']));
            demand(count($pending) === 1 && $pending[0]['request_status'] === 'EXPIRED' && $pending[0]['match_status'] === 'CLOSED'
                && (int) $pending[0]['donor_id'] !== (int) $actor['id'], 'S08 chapter-scoped staff queue retains report for rejection');
            $stage = 'decision_assertions'; $blocked = false;
            try { (new DonationService())->decide($actor, $state['pending_report'], true); }
            catch (RuntimeException $e) { $blocked = (int) $e->getCode() === 409; }
            demand($blocked, 'S09 authorized confirmation blocked with 409');
            $blankBlocked = false;
            try { (new DonationService())->decide($actor, $state['pending_report'], false, '   '); }
            catch (ValidationException $e) { $blankBlocked = true; }
            demand($blankBlocked && sql('SELECT status FROM donation_reports WHERE id = ?', [$state['pending_report']])->fetchColumn() === 'PENDING', 'S10 blank rejection reason blocked without changing report');
            $reason = 'Scheduled-expiry fixture: closed request; donation cannot be confirmed.';
            (new DonationService())->decide($actor, $state['pending_report'], false, $reason);
            $rejected = sql('SELECT * FROM donation_reports WHERE id = ?', [$state['pending_report']])->fetch();
            demand($rejected['status'] === 'REJECTED' && $rejected['rejection_reason'] === $reason && (int) $rejected['confirmed_by'] === (int) $actor['id'], 'S11 authorized reasoned rejection persisted');
            $history = array_values(array_filter($reports->listByDonor($state['users']['pending']), fn ($row) => (int) $row['id'] === $state['pending_report']));
            $context = sql("SELECT context FROM audit_log WHERE actor_id = ? AND action = 'donation.rejected' AND target_type = 'donation_report' AND target_id = ? ORDER BY id DESC LIMIT 1", [$actor['id'], (string) $state['pending_report']])->fetchColumn();
            demand(count($history) === 1 && $history[0]['rejection_reason'] === $reason && $context !== false
                && (json_decode($context, true)['rejection_reason'] ?? null) === $reason, 'S12 rejection reason retained in own history and audit');
            demand(safety(array_values($state['users'])) === $state['safety']
                && sql('SELECT * FROM donation_reports WHERE id = ?', [$state['completed_report']])->fetch() === $state['completed_report_row']
                && sql('SELECT * FROM matches WHERE id = ?', [$state['matches']['completed']])->fetch() === $state['completed_match_row']
                && sql('SELECT status FROM blood_requests WHERE id = ?', [$state['request']])->fetchColumn() === 'EXPIRED', 'S13 denied confirmation/rejection create no donation, cooldown or reopening');
            echo "== Scheduled expiry fixture: 16 assertions passed, 0 failed ==\n";
        }
    }
} catch (Throwable $e) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
        if ($action === 'setup' && $saved) { @unlink($manifest); }
    }
    echo 'FAIL action=' . $action . ' stage=' . $stage . ' exception=' . get_class($e) . ' code=' . (int) $e->getCode()
        . '; raw errors suppressed. Remove task, then run cleanup if a manifest exists.' . PHP_EOL;
    exit(1);
} finally {
    if ($session) { Session::destroy(); }
    foreach (array_reverse($locks) as $handle) { flock($handle, LOCK_UN); fclose($handle); }
}
