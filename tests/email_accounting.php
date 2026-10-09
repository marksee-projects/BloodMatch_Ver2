<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/backend/src/autoload.php';
require BASE_PATH . '/database/email_checkpoint_support.php';
require __DIR__ . '/helpers/fake_mail_transport.php';

use BloodMatch\Config\Database;
use BloodMatch\Services\EmailAttemptBudget as Budget;
use BloodMatch\Services\Mailer;

ini_set('display_errors', '0');
$ids = []; $userId = null; $notifications = []; $passed = 0; $pdo = null; $workers = [];
function check(bool $ok, string $label): void {
    global $passed;
    if (!$ok) { throw new LogicException('FAIL ' . $label); }
    $passed++; echo 'PASS ' . $label . PHP_EOL;
}
function reserve(string $class): array {
    global $ids;
    $r = Budget::reserve($class);
    if ($r['attempt_id'] !== null) { $ids[] = $r['attempt_id']; }
    return $r;
}
function clearAttempts(): void {
    global $ids, $pdo;
    foreach ($ids as $id) { $pdo->prepare('DELETE FROM email_attempts WHERE id=?')->execute([$id]); }
    $ids = [];
}
function seed(int $count, string $class, string $age = '0 SECOND'): void {
    global $pdo, $ids;
    if (!in_array($age, ['0 SECOND', '2 HOUR', '25 HOUR'], true)) { throw new LogicException('Invalid fixture age.'); }
    $stmt = $pdo->prepare("INSERT INTO email_attempts (mail_class, attempted_at) VALUES (?, DATE_SUB(UTC_TIMESTAMP(6), INTERVAL $age))");
    for ($i = 0; $i < $count; $i++) { $stmt->execute([$class]); $ids[] = (int) $pdo->lastInsertId(); }
}
function parallel(string $class): array {
    global $workers, $ids;
    $workers = []; $results = []; $start = microtime(true) + 1;
    for ($i = 0; $i < 4; $i++) {
        $pipes = [];
        $proc = proc_open([PHP_BINARY, __FILE__, '--worker', $class, (string) $start], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, BASE_PATH);
        if (!is_resource($proc)) { throw new LogicException('Cannot start test workers.'); }
        fclose($pipes[0]); $workers[] = [$proc, $pipes];
    }
    $failed = false;
    foreach ($workers as [$proc, $pipes]) {
        $out = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($proc);
        $r = json_decode($out, true);
        if ($exit !== 0 || !is_array($r) || !array_key_exists('allowed', $r)) { $failed = true; continue; }
        if ($r['attempt_id'] !== null) { $ids[] = (int) $r['attempt_id']; }
        $results[] = $r;
    }
    $workers = [];
    if ($failed) { throw new LogicException('Worker failed; only known fixture IDs are cleaned. Inspect remaining test attempts before retry.'); }
    return $results;
}
try {
    $pdo = emailCheckpointTarget();
    if (($argv[1] ?? '') === '--worker') {
        $until = (float) ($argv[3] ?? 0);
        if ($until > microtime(true) + 5) { throw new LogicException('Invalid worker gate.'); }
        while (microtime(true) < $until) { usleep(1000); }
        echo json_encode(Budget::reserve((string) ($argv[2] ?? ''))) . PHP_EOL; exit(0);
    }
    if (count($argv) !== 1) { throw new LogicException('Run without arguments.'); }
    if (!function_exists('proc_open')) { throw new LogicException('proc_open is required.'); }
    check(emailCheckpointShape($pdo) === ['email_outbox' => 'ready', 'email_attempts' => 'ready'], 'complete schema shape');
    $ledger = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE name=?');
    $ledger->execute(['021_email_outbox_accounting.sql']);
    check((int) $ledger->fetchColumn() === 1, '021 recorded in existing ledger');
    check((int) $pdo->query('SELECT COUNT(*) FROM email_attempts')->fetchColumn() === 0
        && (int) $pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn() === 0, 'empty checkpoint tables required; stop all other test mail writers');
    $baseline = $pdo->query('SELECT id, status, rejection_reason FROM donation_reports ORDER BY id')->fetchAll(PDO::FETCH_NUM);
    seed(89, Budget::EMERGENCY);
    $r = parallel(Budget::EMERGENCY);
    check(count(array_filter($r, fn ($x) => $x['allowed'])) === 1, 'parallel Emergency reservations stop at 90/hour');
    check(!reserve(Budget::NORMAL)['allowed'], 'normal cannot consume account protection');
    for ($i = 0; $i < 10; $i++) { check(reserve(Budget::ACCOUNT)['allowed'], 'protected account slot ' . ($i + 1)); }
    $before = count($ids);
    check(!reserve(Budget::ACCOUNT)['allowed'] && count($ids) === $before, '100/hour denial creates no attempt');
    clearAttempts();
    seed(99, Budget::ACCOUNT);
    $r = parallel(Budget::ACCOUNT);
    check(count(array_filter($r, fn ($x) => $x['allowed'])) === 1, 'parallel accounts use total capacity through 100/hour');
    clearAttempts();
    seed(399, Budget::EMERGENCY, '2 HOUR');
    check(reserve(Budget::EMERGENCY)['allowed'] && !reserve(Budget::EMERGENCY)['allowed'], '400/24h Emergency cap independent of hour');
    $allAllowed = true;
    for ($i = 0; $i < 50; $i++) { $allAllowed = reserve(Budget::ACCOUNT)['allowed'] && $allAllowed; }
    check($allAllowed && !reserve(Budget::ACCOUNT)['allowed'], '50 protected daily slots and 450 total daily cap');
    clearAttempts();
    seed(450, Budget::ACCOUNT, '25 HOUR');
    check(reserve(Budget::ACCOUNT)['allowed'], 'older than rolling 24h releases capacity');
    clearAttempts();
    // Fixed SQL boundary evidence: exactly an hour/day old is outside (cutoff, now].
    $stmt = $pdo->prepare('SELECT ? > DATE_SUB(?, INTERVAL 1 HOUR), ? > DATE_SUB(?, INTERVAL 24 HOUR)');
    $stmt->execute(['2026-10-08 11:00:00.000000', '2026-10-08 12:00:00.000000', '2026-10-07 12:00:00.000000', '2026-10-08 12:00:00.000000']);
    check(array_map('intval', $stmt->fetch(PDO::FETCH_NUM)) === [0, 0], 'exact rolling cutoff excluded');
    $pdo->beginTransaction();
    $r = reserve(Budget::ACCOUNT); $pdo->rollBack();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM email_attempts WHERE id=?'); $stmt->execute([$r['attempt_id']]);
    check((int) $stmt->fetchColumn() === 1, 'reservation survives workflow rollback');
    $lockName = 'bm-mail-budget:' . substr(hash('sha256', 'bloodmatch_test'), 0, 32);
    $stmt = $pdo->prepare('SELECT GET_LOCK(?, 0)'); $stmt->execute([$lockName]);
    check((int) $stmt->fetchColumn() === 1, 'budget lock released before transport');
    $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
    clearAttempts();
    $_ENV['MAIL_HOST'] = 'fake-no-sockets'; $_ENV['MAIL_FROM'] = 'fixture@example.org';
    \PHPMailer\PHPMailer\PHPMailer::$fail = true;
    check(!Mailer::send('fixture@example.org', 'Synthetic', '<p>Synthetic</p>', Budget::ACCOUNT), 'fake transport failure');
    $ids = array_map('intval', $pdo->query('SELECT id FROM email_attempts')->fetchAll(PDO::FETCH_COLUMN));
    check(count($ids) === 1, 'failed transport still consumes one committed attempt');
    \PHPMailer\PHPMailer\PHPMailer::$fail = false;
    check(Mailer::send('fixture@example.org', 'Synthetic', '<p>Synthetic</p>', Budget::EMERGENCY), 'fake acceptance');
    $ids = array_map('intval', $pdo->query('SELECT id FROM email_attempts')->fetchAll(PDO::FETCH_COLUMN));
    check(count($ids) === 2, 'account and Emergency share the ledger');
    Mailer::send('fixture@example.test', 'Synthetic', 'Synthetic', Budget::ACCOUNT);
    $_ENV['MAIL_HOST'] = ''; Mailer::send('fixture@example.org', 'Synthetic', 'Synthetic', Budget::ACCOUNT);
    check((int) $pdo->query('SELECT COUNT(*) FROM email_attempts')->fetchColumn() === 2, 'reserved/unconfigured skips consume no attempts');
    clearAttempts();
    $token = bin2hex(random_bytes(8));
    $pdo->prepare("INSERT INTO users (email, first_name, last_name) VALUES (?, 'Email', 'Fixture')")->execute(["email-checkpoint-$token@example.test"]);
    $userId = (int) $pdo->lastInsertId();
    $pdo->prepare("INSERT INTO notifications (user_id,type,title,body,dedup_key,generation) VALUES (?,'test.email','Synthetic','Synthetic',?,0)")->execute([$userId, "email-test:$token"]);
    $notification = (int) $pdo->lastInsertId(); $notifications[] = $notification;
    $insert = $pdo->prepare("INSERT INTO email_outbox (notification_id,delivery_key,priority,next_attempt_at,expires_at,created_at,updated_at)
        VALUES (?,?,'normal',UTC_TIMESTAMP(6),DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 72 HOUR),UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
    $insert->execute([$notification, "email:$notification"]);
    $duplicate = false;
    try { $insert->execute([$notification, 'different']); } catch (PDOException $e) { $duplicate = ($e->errorInfo[1] ?? 0) === 1062; }
    check($duplicate, 'one outbox job per notification enforced');
    $pdo->prepare("INSERT INTO notifications (user_id,type,title,body,generation) VALUES (?,'test.email','Synthetic','Synthetic',0)")->execute([$userId]);
    $second = (int) $pdo->lastInsertId(); $notifications[] = $second;
    $duplicate = false;
    try { $insert->execute([$second, "email:$notification"]); } catch (PDOException $e) { $duplicate = ($e->errorInfo[1] ?? 0) === 1062; }
    check($duplicate, 'unique delivery key enforced');
    $foreign = false;
    try { $insert->execute([0, 'missing-notification']); } catch (PDOException $e) { $foreign = ($e->errorInfo[1] ?? 0) === 1452; }
    check($foreign, 'notification FK enforced');
    $pdo->prepare('DELETE FROM notifications WHERE id=? AND user_id=?')->execute([$notification, $userId]);
    check((int) $pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn() === 0, 'outbox cascades only its deleted fixture notification');
    check($baseline === $pdo->query('SELECT id, status, rejection_reason FROM donation_reports ORDER BY id')->fetchAll(PDO::FETCH_NUM), 'donation reports unchanged');
} catch (Throwable $e) {
    fwrite(STDERR, ($e instanceof LogicException ? $e->getMessage() : 'Database checkpoint failed; raw error withheld.') . PHP_EOL);
    $failed = true;
} finally {
    foreach ($workers as [$proc, $pipes]) {
        foreach ($pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
        if (is_resource($proc)) { proc_close($proc); }
    }
    if ($pdo instanceof PDO && ($argv[1] ?? '') !== '--worker') {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        clearAttempts();
        foreach ($notifications as $id) { $pdo->prepare('DELETE FROM notifications WHERE id=? AND user_id=?')->execute([$id, $userId]); }
        if ($userId !== null) { $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]); }
        echo "Known fixture IDs cleaned; audit history retained.\n";
        if (!isset($failed)) {
            check((int) $pdo->query('SELECT COUNT(*) FROM email_attempts')->fetchColumn() === 0
                && (int) $pdo->query('SELECT COUNT(*) FROM email_outbox')->fetchColumn() === 0, 'fixture tables empty after cleanup');
            echo "$passed database email checkpoint checks passed\n";
        }
    }
}
exit(isset($failed) ? 1 : 0);
