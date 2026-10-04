<?php
declare(strict_types=1);
define('BASE_PATH', __DIR__);
require BASE_PATH . '/backend/src/autoload.php';
use BloodMatch\Config\Database;
use BloodMatch\Config\Env;
Env::load(BASE_PATH . '/.env');

$actor = Database::pdo()->query("SELECT email, blood_type FROM users WHERE email = 'markseejr@gmail.com'")->fetch(PDO::FETCH_ASSOC);

$compatibleRecipientTypes = \BloodMatch\Services\BloodCompatibilityService::getCompatibleRecipientTypes($actor['blood_type']);
print_r($compatibleRecipientTypes);

$rows = (new \BloodMatch\Repositories\BloodRequestRepository())->listFeed(20, $compatibleRecipientTypes);
foreach ($rows as $row) {
    echo "Request ID: {$row['id']}, Blood Type: {$row['required_blood_type']}\n";
}
