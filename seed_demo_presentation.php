<?php

declare(strict_types=1);

define('BASE_PATH', __DIR__);
require BASE_PATH . '/backend/src/autoload.php';

use BloodMatch\Config\Database;
use BloodMatch\Config\Env;

Env::load(BASE_PATH . '/.env');

$pdo = Database::pdo();
$passHash = password_hash('Password123!', PASSWORD_BCRYPT);

$requesters = [
    [
        'email' => 'maria.clara@example.com',
        'first_name' => 'Maria',
        'last_name' => 'Clara',
        'chapter_id' => 2, // Mt. Tarak
        'blood_type' => 'AB+',
        'latitude' => 14.435000,
        'longitude' => 120.486700,
    ],
    [
        'email' => 'diego.silang@example.com',
        'first_name' => 'Diego',
        'last_name' => 'Silang',
        'chapter_id' => 3, // Meridian Heights
        'blood_type' => 'B+',
        'latitude' => 14.676500,
        'longitude' => 120.536100,
    ],
    [
        'email' => 'emilio.jacinto@example.com',
        'first_name' => 'Emilio',
        'last_name' => 'Jacinto',
        'chapter_id' => 2, // Mt. Tarak
        'blood_type' => 'B+',
        'latitude' => 14.440000,
        'longitude' => 120.490000,
    ]
];

echo "Creating presentation accounts and blood requests...\n";

foreach ($requesters as $reqUser) {
    // Insert User
    $stmt = $pdo->prepare("
        INSERT INTO users (
            email, password_hash, first_name, last_name, role, chapter_id, 
            verification_status, account_status, date_of_birth, blood_type, 
            blood_type_source, blood_type_verified, latitude, longitude
        ) VALUES (?, ?, ?, ?, 'member', ?, 'verified', 'active', '1985-06-15', ?, 'self_reported', 0, ?, ?)
        ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
    ");
    $stmt->execute([
        $reqUser['email'], $passHash, $reqUser['first_name'], $reqUser['last_name'], 
        $reqUser['chapter_id'], $reqUser['blood_type'], $reqUser['latitude'], $reqUser['longitude']
    ]);
    
    $userId = (int) $pdo->lastInsertId();
    if ($userId === 0) {
        // If it already existed, fetch ID
        $userId = (int) $pdo->query("SELECT id FROM users WHERE email = '{$reqUser['email']}'")->fetchColumn();
    }

    // Create Blood Request
    $neededDate = date('Y-m-d H:i:s', strtotime('+3 days'));
    
    $reqRepo = new \BloodMatch\Repositories\BloodRequestRepository();
    $reqId = $reqRepo->create([
        'requester_id' => $userId,
        'request_chapter_id' => $reqUser['chapter_id'],
        'required_blood_type' => $reqUser['blood_type'],
        'quantity_units' => 2,
        'facility_name' => 'Bataan General Hospital',
        'hospital_id' => null,
        'location_id' => 3, // Some valid location ID in bataan_locations
        'latitude' => $reqUser['latitude'] + 0.005,
        'longitude' => $reqUser['longitude'] + 0.005,
        'urgency' => 'Urgent',
        'needed_datetime' => $neededDate,
        'review_status' => 'approved'
    ]);
    
    echo "Created request #{$reqId} for {$reqUser['first_name']} ({$reqUser['blood_type']})\n";

    // Run Matching Engine
    try {
        $matchSummary = (new \BloodMatch\Services\MatchService())->generateForRequest($reqId, true, 'presentation_seed', 1);
        echo "  -> Found {$matchSummary['pool_size']} compatible donors.\n";
    } catch (\Throwable $e) {
        echo "  -> Error running matching: " . $e->getMessage() . "\n";
    }
}

echo "Done generating presentation data!\n";
