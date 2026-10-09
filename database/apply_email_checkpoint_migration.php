<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require dirname(__DIR__) . '/backend/src/autoload.php';
require __DIR__ . '/email_checkpoint_support.php';

// USER-RUN ONLY. Review is SELECT-only; --apply applies only 021 to the fixed test target.
$name = '021_email_outbox_accounting.sql';
try {
    if (array_diff(array_slice($argv, 1), ['--apply']) !== []) { throw new LogicException('Use no arguments for review, or --apply.'); }
    $pdo = emailCheckpointTarget();
    $shape = emailCheckpointShape($pdo);
    if (in_array('drift', $shape, true)) { throw new LogicException('Refused: unexpected existing schema. No repair/drop/reset is performed.'); }
    $ledger = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE name=?');
    $ledger->execute([$name]); $recorded = $ledger->fetchColumn() !== false;
    if ($recorded && in_array('missing', $shape, true)) { throw new LogicException('Refused: recorded migration has missing tables.'); }
    $sql = file_get_contents(__DIR__ . '/migrations/' . $name);
    if ($sql === false) { throw new LogicException('Migration file missing.'); }
    echo json_encode(['database' => 'bloodmatch_test', 'port' => 3306, 'shape' => $shape, 'recorded' => $recorded]) . PHP_EOL;
    echo $sql . PHP_EOL;
    if (!in_array('--apply', $argv, true)) { echo "Review only; no writes.\n"; exit(0); }
    // Stop test writers first. DDL auto-commits; valid existing tables can be
    // retained after partial DDL failure, and only missing tables are created.
    $snapshot = static function () use ($pdo): array {
        return [
            $pdo->query('SELECT id, user_id, type, generation, emailed_at, read_at FROM notifications ORDER BY id')->fetchAll(PDO::FETCH_NUM),
            $pdo->query('SELECT id, status, rejection_reason FROM donation_reports ORDER BY id')->fetchAll(PDO::FETCH_NUM),
        ];
    };
    $before = $snapshot();
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
        $statement = trim(preg_replace('/^\s*--.*$/m', '', $statement) ?? '');
        if ($statement === '') { continue; }
        if (!preg_match('/^CREATE TABLE (email_outbox|email_attempts) \(/', $statement, $match)) { throw new LogicException('Unexpected migration statement.'); }
        if ($shape[$match[1]] === 'missing') {
            $pdo->exec($statement);
            if ((int) $pdo->query('SELECT COUNT(*) FROM ' . $match[1])->fetchColumn() !== 0) { throw new LogicException('New table unexpectedly contains rows. Stop writers and inspect.'); }
        }
    }
    if (emailCheckpointShape($pdo) !== ['email_outbox' => 'ready', 'email_attempts' => 'ready']) { throw new LogicException('Schema verification failed; ledger not updated.'); }
    if ($snapshot() !== $before) { throw new LogicException('Existing notification/report values changed. Stop writers and inspect; ledger not updated.'); }
    if (!$recorded) { $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]); }
    echo "021 ready and recorded; existing notifications/reports unchanged. No backfill or other migration.\n";
} catch (Throwable $e) {
    fwrite(STDERR, ($e instanceof LogicException ? $e->getMessage() : 'Database operation failed; inspect test setup/permissions. Raw errors withheld.') . PHP_EOL);
    exit(1);
}
