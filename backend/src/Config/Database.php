<?php

declare(strict_types=1);

namespace BloodMatch\Config;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::get('DB_PORT', '3306');
        $name = Env::get('DB_NAME', '');
        $user = Env::get('DB_USER', '');
        $pass = Env::get('DB_PASS', '');

        $isolated = Env::get('BLOODMATCH_TEST_ISOLATION', '') === '1';
        if ($isolated && ($host !== '127.0.0.1' || $name !== 'bloodmatch_test' || $port !== '3306')) {
            throw new RuntimeException('Isolated tests require bloodmatch_test on 127.0.0.1:3306.');
        }

        if ($name === '' || $user === '') {
            throw new RuntimeException('Database configuration is incomplete.');
        }

        $portsToTry = [(string) $port];
        $altPort = ((string) $port === '3307') ? '3306' : '3307';
        if (!$isolated && !in_array($altPort, $portsToTry, true)) {
            $portsToTry[] = $altPort;
        }

        $lastException = null;
        foreach ($portsToTry as $tryPort) {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $tryPort, $name);
            try {
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                if ($isolated) {
                    $target = self::$pdo->query('SELECT DATABASE() AS db, @@port AS port')->fetch();
                    if ($target['db'] !== 'bloodmatch_test' || (int)$target['port'] !== 3306) {
                        self::$pdo = null;
                        throw new RuntimeException('Isolated database target mismatch.');
                    }
                }
                self::$pdo->exec("SET time_zone = '+00:00'");
                return self::$pdo;
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }

        throw new RuntimeException('Database connection failed: ' . ($lastException ? $lastException->getMessage() : 'unknown error'), 0, $lastException);
    }

    // Same resolved target as the workflow connection; no fallback on this
    // second connection. Accounting commits must survive workflow rollback.
    public static function independentPdo(): PDO
    {
        $target = self::pdo()->query('SELECT DATABASE() AS db_name, @@port AS db_port')->fetch(PDO::FETCH_ASSOC);
        $pdo = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Env::get('DB_HOST', '127.0.0.1'), $target['db_port'], $target['db_name']),
            Env::get('DB_USER', ''), Env::get('DB_PASS', ''), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        $pdo->exec("SET time_zone = '+00:00'");
        return $pdo;
    }

    public static function configSummary(): array
    {
        return [
            'host' => Env::get('DB_HOST', '127.0.0.1'),
            'port' => (int) Env::get('DB_PORT', '3307'),
        ];
    }
}
