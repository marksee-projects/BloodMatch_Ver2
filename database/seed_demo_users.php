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

$passHash = password_hash('Password123!', PASSWORD_BCRYPT);



// 2. Create Chapter Officer Account
$stmt = $pdo->prepare("
    INSERT INTO users (email, password_hash, first_name, last_name, role, chapter_id, verification_status, account_status, date_of_birth, blood_type, blood_type_source, blood_type_verified)
    VALUES ('officer@bloodmatch.org', ?, 'Mt. Samat', 'Officer', 'officer', 1, 'verified', 'active', '1992-05-15', 'A+', 'officer_verified', 1)
    ON DUPLICATE KEY UPDATE role = 'officer', password_hash = VALUES(password_hash), verification_status = 'verified', account_status = 'active'
");
$stmt->execute([$passHash]);

// 3. Promote markseejr@gmail.com to Admin as well for convenience
$stmt = $pdo->prepare("
    UPDATE users SET role = 'admin', verification_status = 'verified' WHERE email = 'markseejr@gmail.com'
");
$stmt->execute();

echo "Demo accounts created/updated successfully!" . PHP_EOL;
