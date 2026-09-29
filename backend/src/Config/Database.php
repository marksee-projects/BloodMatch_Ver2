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

        if ($name === '' || $user === '') {
            throw new RuntimeException('Database configuration is incomplete.');
        }

        $portsToTry = [(string) $port];
        $altPort = ((string) $port === '3307') ? '3306' : '3307';
        if (!in_array($altPort, $portsToTry, true)) {
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
                self::$pdo->exec("SET time_zone = '+00:00'");
                return self::$pdo;
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }

        throw new RuntimeException('Database connection failed: ' . ($lastException ? $lastException->getMessage() : 'unknown error'), 0, $lastException);
    }

    public static function configSummary(): array
    {
        return [
            'host' => Env::get('DB_HOST', '127.0.0.1'),
            'port' => (int) Env::get('DB_PORT', '3307'),
        ];
    }
}
