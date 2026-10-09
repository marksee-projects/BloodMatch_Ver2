<?php

declare(strict_types=1);

use BloodMatch\Config\Database;
use BloodMatch\Config\Env;

// Shared by user-only migration review and database tests. No writes here.
function emailCheckpointTarget(): PDO
{
    foreach (['DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_NAME' => 'bloodmatch_test'] as $key => $value) {
        putenv($key . '=' . $value); $_ENV[$key] = $value;
    }
    Env::load(dirname(__DIR__) . '/.env');
    $pdo = Database::pdo();
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'bloodmatch_test'
        || (int) $pdo->query('SELECT @@port')->fetchColumn() !== 3306) {
        throw new LogicException('Refused: expected bloodmatch_test on port 3306; connector fallback is not permitted.');
    }
    return $pdo;
}

/** Compare the complete declared columns/indexes/FK, refusing partial/drifted objects. */
function emailCheckpointShape(PDO $pdo): array
{
    $tables = [
        'email_outbox' => [
            'id' => ['bigint unsigned', 'NO', null, 'auto_increment'],
            'notification_id' => ['bigint unsigned', 'NO', null, ''],
            'delivery_key' => ['varchar(190)', 'NO', null, ''],
            'priority' => ["enum('normal','emergency')", 'NO', null, ''],
            'status' => ["enum('pending','leased','sent','failed','cancelled')", 'NO', 'pending', ''],
            'attempts' => ['smallint unsigned', 'NO', '0', ''],
            'next_attempt_at' => ['datetime(6)', 'NO', null, ''],
            'lease_token' => ['char(32)', 'YES', null, ''],
            'lease_expires_at' => ['datetime(6)', 'YES', null, ''],
            'expires_at' => ['datetime(6)', 'NO', null, ''],
            'last_error_code' => ['varchar(60)', 'YES', null, ''],
            'cancel_reason' => ['varchar(60)', 'YES', null, ''],
            'created_at' => ['datetime(6)', 'NO', null, ''],
            'updated_at' => ['datetime(6)', 'NO', null, ''],
            'sent_at' => ['datetime(6)', 'YES', null, ''],
        ],
        'email_attempts' => [
            'id' => ['bigint unsigned', 'NO', null, 'auto_increment'],
            'mail_class' => ["enum('account','normal','emergency')", 'NO', null, ''],
            'attempted_at' => ['datetime(6)', 'NO', null, ''],
        ],
    ];
    $indexes = [
        'email_outbox' => [
            'PRIMARY' => [0, 'id'], 'uq_email_outbox_notification' => [0, 'notification_id'],
            'uq_email_outbox_delivery' => [0, 'delivery_key'],
            'idx_email_outbox_due' => [1, 'status,priority,next_attempt_at,id'],
            'idx_email_outbox_lease' => [1, 'status,lease_expires_at'],
        ],
        'email_attempts' => ['PRIMARY' => [0, 'id'], 'idx_email_attempts_window' => [1, 'attempted_at,mail_class']],
    ];
    $result = [];
    foreach ($tables as $table => $expected) {
        $stmt = $pdo->prepare('SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]); $meta = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($meta === false) { $result[$table] = 'missing'; continue; }
        $valid = $meta['ENGINE'] === 'InnoDB' && $meta['TABLE_COLLATION'] === 'utf8mb4_unicode_ci';
        $stmt = $pdo->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLLATION_NAME
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
        $stmt->execute([$table]); $actual = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $type = preg_replace('/\b(bigint|smallint)\(\d+\)/', '$1', strtolower($column['COLUMN_TYPE']));
            $default = $column['COLUMN_DEFAULT'];
            // MariaDB reports quoted string defaults and the literal NULL.
            if ($default === 'NULL') { $default = null; }
            elseif (is_string($default)) { $default = trim($default, "'"); }
            $actual[$column['COLUMN_NAME']] = [$type, $column['IS_NULLABLE'], $default, $column['EXTRA']];
            if ($column['COLLATION_NAME'] !== null && $column['COLLATION_NAME'] !== 'utf8mb4_unicode_ci') { $valid = false; }
        }
        $valid = $valid && $actual === $expected;
        $stmt = $pdo->prepare('SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SUB_PART FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME, SEQ_IN_INDEX');
        $stmt->execute([$table]); $actualIndexes = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $index) {
            $name = $index['INDEX_NAME'];
            if ($index['SUB_PART'] !== null) { $valid = false; }
            if (!isset($actualIndexes[$name])) { $actualIndexes[$name] = [(int) $index['NON_UNIQUE'], $index['COLUMN_NAME']]; }
            else { $actualIndexes[$name][1] .= ',' . $index['COLUMN_NAME']; }
        }
        ksort($actualIndexes); $expectedIndexes = $indexes[$table]; ksort($expectedIndexes);
        $valid = $valid && $actualIndexes === $expectedIndexes;
        $stmt = $pdo->prepare('SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE
            FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
            ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME
            WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=?');
        $stmt->execute([$table]); $fks = $stmt->fetchAll(PDO::FETCH_NUM);
        $expectedFks = $table === 'email_outbox' ? [['notification_id', 'notifications', 'id', 'CASCADE', 'RESTRICT']] : [];
        $result[$table] = $valid && $fks === $expectedFks ? 'ready' : 'drift';
    }
    return $result;
}
