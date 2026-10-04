<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/backend/src/autoload.php';

use BloodMatch\Config\Database;
use BloodMatch\Config\Env;

Env::load(BASE_PATH . '/.env');

try {
    $pdo = Database::pdo();
} catch (Throwable $e) {
    echo "Connection error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

$dummyPassHash = password_hash('DummyPassword123!', PASSWORD_BCRYPT);

echo "Creating 10 dummy accounts (7 verified, 3 unverified)..." . PHP_EOL;

for ($i = 1; $i <= 10; $i++) {
    $verificationStatus = ($i <= 7) ? 'verified' : 'unverified';
    $email = "dummyuser{$i}@example.com";
    
    // We insert dummy users. Let's assume some common fields.
    $stmt = $pdo->prepare("
        INSERT INTO users (email, password_hash, first_name, last_name, role, chapter_id, verification_status, account_status, date_of_birth, blood_type, blood_type_source, blood_type_verified)
        VALUES (?, ?, ?, ?, 'donor', 1, ?, 'active', '1990-01-01', 'O+', 'user_provided', 0)
        ON DUPLICATE KEY UPDATE 
            verification_status = VALUES(verification_status),
            password_hash = VALUES(password_hash)
    ");
    
    $stmt->execute([
        $email,
        $dummyPassHash,
        "Dummy",
        "User{$i}",
        $verificationStatus
    ]);
}

echo "Dummy accounts created successfully." . PHP_EOL;
