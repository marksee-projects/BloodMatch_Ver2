<?php

declare(strict_types=1);

// Read-only U0 preflight/postflight. Never applies migrations or modifies data.
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/backend/src/autoload.php';

use BloodMatch\Config\Database;
use BloodMatch\Config\Env;

Env::load(BASE_PATH . '/.env');

try {
    $pdo = Database::pdo();
    $column = $pdo->query(
        "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blood_requests' AND COLUMN_NAME = 'urgency'"
    )->fetch();
    if ($column === false) {
        throw new RuntimeException('blood_requests.urgency is missing.');
    }

    $counts = $pdo->query(
        "SELECT COUNT(*) AS total_rows,
                COALESCE(SUM(urgency = 'routine'), 0) AS routine_rows,
                COALESCE(SUM(urgency = 'urgent'), 0) AS urgent_rows,
                COALESCE(SUM(urgency = 'emergency'), 0) AS emergency_rows,
                COALESCE(SUM(urgency = 'critical'), 0) AS critical_rows,
                COALESCE(SUM(urgency IS NULL OR urgency NOT IN ('routine', 'urgent', 'emergency')), 0) AS invalid_rows
         FROM blood_requests"
    )->fetch();
    $migration = '019_canonical_emergency_urgency.sql';
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE name = ?');
    $stmt->execute([$migration]);
    $applied = (int) $stmt->fetchColumn() === 1;

    echo json_encode([
        'database' => $pdo->query('SELECT DATABASE()')->fetchColumn(),
        'column' => $column,
        'counts' => $counts,
        'migration' => $migration,
        'migration_applied' => $applied,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;

    if (in_array('--expect-emergency', $argv, true)) {
        if ($column['COLUMN_TYPE'] !== "enum('routine','urgent','emergency')"
            || $column['IS_NULLABLE'] !== 'NO'
            || !in_array($column['COLUMN_DEFAULT'], ['routine', "'routine'"], true)
            || (int) $counts['critical_rows'] !== 0 || (int) $counts['invalid_rows'] !== 0 || !$applied) {
            throw new RuntimeException('U0 verification failed: expected final ENUM, zero retired/invalid rows, and migration recorded.');
        }
        echo '[urgency] U0 verified: canonical ENUM, zero critical rows.' . PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, '[urgency] ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
