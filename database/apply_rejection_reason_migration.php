<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

// USER-RUN ONLY. Default is review; --apply targets bloodmatch_test, never another DB.
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/backend/src/autoload.php';

use BloodMatch\Config\Database;
use BloodMatch\Config\Env;

$options = getopt('', ['apply', 'port:']);
$port = (string) ($options['port'] ?? '3306');
if (!preg_match('/^[1-9]\d{0,4}$/D', $port) || (int) $port > 65535) {
    fwrite(STDERR, "[020] Invalid test database port.\n"); exit(1);
}
foreach (['DB_HOST' => '127.0.0.1', 'DB_PORT' => $port, 'DB_NAME' => 'bloodmatch_test'] as $key => $value) {
    putenv($key . '=' . $value); $_ENV[$key] = $value;
}
Env::load(BASE_PATH . '/.env');
$name = '020_donation_report_rejection_reason.sql';
try {
    $pdo = Database::pdo();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'bloodmatch_test'
        || (int) $pdo->query('SELECT @@port')->fetchColumn() !== (int) $port) {
        throw new LogicException('Refused: resolved database/port does not match the requested test target.');
    }
    $columnStmt = $pdo->prepare("SELECT DATA_TYPE AS data_type, CHARACTER_MAXIMUM_LENGTH AS max_length,
        IS_NULLABLE AS nullable
        FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'donation_reports' AND COLUMN_NAME = 'rejection_reason'");
    $readColumn = static function () use ($columnStmt): ?array {
        $columnStmt->execute(); $row = $columnStmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    };
    $validColumn = static fn (?array $column): bool => $column !== null
        && $column['data_type'] === 'varchar' && (int) $column['max_length'] === 500
        && $column['nullable'] === 'YES';
    $column = $readColumn();
    if ($column !== null && !$validColumn($column)) {
        throw new LogicException('Refused: existing rejection_reason column has an unexpected definition; no conversion performed.');
    }
    $ledgerExists = (bool) $pdo->query("SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations'")->fetchColumn();
    if (!$ledgerExists) {
        throw new LogicException('Migration ledger missing. Review the existing test database setup before applying 020; do not reset data.');
    }
    $ledgerStmt = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE name = ?');
    $ledgerStmt->execute([$name]); $recorded = $ledgerStmt->fetchColumn() !== false;
    if ($recorded && $column === null) {
        throw new LogicException('Refused: ledger says 020 is applied but its column is missing. Investigate before changing the schema.');
    }
    $sql = file_get_contents(__DIR__ . '/migrations/' . $name);
    if ($sql === false || trim($sql) === '') { throw new LogicException('Migration file is missing or empty.'); }
    echo '[020] Target: bloodmatch_test on port ' . $port . PHP_EOL;
    echo '[020] Column: ' . ($column === null ? 'pending' : 'VARCHAR(500) NULL') . PHP_EOL;
    echo '[020] Ledger: ' . ($recorded ? 'recorded' : 'pending') . PHP_EOL;
    echo $sql . PHP_EOL;
    if (!array_key_exists('apply', $options)) {
        echo "[020] Review only; no writes. Use --apply only after reviewing this SQL.\n"; exit(0);
    }
    // Stop test writers before applying; DDL auto-commits. Never drop/rebuild a table.
    $before = $pdo->query('SELECT id, status FROM donation_reports ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    if ($column === null) {
        $pdo->exec($sql);
        if (!$validColumn($readColumn())) { throw new LogicException('Column verification failed. No ledger entry added; inspect the schema.'); }
        if ((int) $pdo->query('SELECT COUNT(*) FROM donation_reports WHERE rejection_reason IS NOT NULL')->fetchColumn() !== 0) {
            throw new LogicException('Unexpected non-NULL reasons during additive apply. Stop test writers and inspect before recording migration.');
        }
    }
    $after = $pdo->query('SELECT id, status FROM donation_reports ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    if ($before !== $after) { throw new LogicException('Report IDs/statuses changed during apply. Stop test writers and inspect; no ledger entry added.'); }
    if (!$recorded) {
        $pdo->prepare('INSERT INTO schema_migrations (name) VALUES (?)')->execute([$name]);
    }
    echo '[020] Verified: ' . count($after) . ' existing report IDs/statuses unchanged; nullable column ready; ledger recorded.' . PHP_EOL;
    echo "[020] No status update, reason backfill, deletion, or other migration was performed.\n";
} catch (Throwable $e) {
    $message = $e instanceof LogicException ? $e->getMessage() : 'Database operation failed. Check test database setup/permissions; no credentials are printed.';
    fwrite(STDERR, '[020] ' . $message . PHP_EOL); exit(1);
}
