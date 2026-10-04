<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/backend/src/autoload.php';

use BloodMatch\Config\Database;
use BloodMatch\Config\Env;
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Services\MatchService;

Env::load(BASE_PATH . '/.env');

try {
    $pdo = Database::pdo();
} catch (Throwable $e) {
    echo "Connection error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

// Fetch dummy users
$stmt = $pdo->query("SELECT * FROM users WHERE email LIKE 'dummyuser%' LIMIT 5");
$dummyUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($dummyUsers)) {
    echo "No dummy users found. Did you run the dummy user seed script?" . PHP_EOL;
    exit(1);
}

$bloodTypes = ['A+', 'B+', 'O+', 'AB-', 'O-'];
$urgencies = ['routine', 'urgent', 'critical'];
$facilities = ['Bataan General Hospital', 'Balanga Medical Center', 'Orani District Hospital', 'Mariveles District Hospital'];

$repo = new BloodRequestRepository();
$matchService = new MatchService();

foreach ($dummyUsers as $index => $user) {
    $bloodType = $bloodTypes[array_rand($bloodTypes)];
    $urgency = $urgencies[array_rand($urgencies)];
    $facility = $facilities[array_rand($facilities)];
    
    $neededDate = date('Y-m-d H:i:s', strtotime('+' . rand(1, 5) . ' days'));
    
    $reqId = $repo->create([
        'requester_id' => (int) $user['id'],
        'request_chapter_id' => $user['chapter_id'] ?? 1,
        'required_blood_type' => $bloodType,
        'quantity_units' => rand(1, 4),
        'facility_name' => $facility,
        'hospital_id' => null,
        'location_id' => rand(1, 10), // Random municipality
        'latitude' => 14.68,
        'longitude' => 120.54,
        'urgency' => $urgency,
        'needed_datetime' => $neededDate,
        'review_status' => 'approved' 
    ]);
    
    echo "Created request #{$reqId} for {$user['email']} (Type: {$bloodType}, {$facility})" . PHP_EOL;
    
    try {
        $summary = $matchService->generateForRequest($reqId, true, 'request_created', (int) $user['id']);
        echo "  -> Matching run: Found {$summary['matches_found']} potential donors." . PHP_EOL;
    } catch (\Throwable $e) {
        echo "  -> Matching error: " . $e->getMessage() . PHP_EOL;
    }
}

echo "Done generating dummy requests and matches!" . PHP_EOL;
