<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/backend/src/autoload.php';

use BloodMatch\Config\Database;
use BloodMatch\Config\Env;
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Services\BloodCompatibilityService;
use BloodMatch\Services\MatchService;

Env::load(BASE_PATH . '/.env');

if (strtolower((string) Env::get('APP_ENV', '')) !== 'local') {
    fwrite(STDERR, "Home examples may only be seeded when APP_ENV=local.\n");
    exit(1);
}

$pdo = Database::pdo();
$databaseName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
if ($databaseName === '' || str_contains(strtolower($databaseName), 'prod')) {
    fwrite(STDERR, "Refusing to seed an unsafe database target.\n");
    exit(1);
}

$targetName = 'Regular Ito User';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--target-name=')) {
        $targetName = trim(substr($argument, strlen('--target-name=')));
    }
}

$targetStmt = $pdo->prepare(
    "SELECT id, full_name, blood_type
     FROM users
     WHERE full_name = ? AND role = 'member' AND account_status = 'active'
     LIMIT 1"
);
$targetStmt->execute([$targetName]);
$target = $targetStmt->fetch(PDO::FETCH_ASSOC);

if ($target === false || empty($target['blood_type'])) {
    fwrite(STDERR, "Active member '{$targetName}' with a saved blood type was not found.\n");
    exit(1);
}

$recipientTypes = BloodCompatibilityService::getCompatibleRecipientTypesForDonor((string) $target['blood_type']);
$hospitals = $pdo->query(
    'SELECT h.id, h.name, h.location_id, l.latitude, l.longitude
     FROM hospitals h
     JOIN bataan_locations l ON l.id = h.location_id
     WHERE l.is_active = 1
     ORDER BY h.id ASC
     LIMIT 5'
)->fetchAll(PDO::FETCH_ASSOC);

if (count($hospitals) < 5) {
    fwrite(STDERR, "At least five seeded hospitals with locations are required.\n");
    exit(1);
}

$people = [
    ['Andrea', 'Santos', 1],
    ['Miguel', 'Reyes', 2],
    ['Carlo', 'Mendoza', 3],
    ['Rafael', 'Garcia', 1],
    ['Nico', 'Villanueva', 2],
];
$urgencies = ['critical', 'urgent', 'routine', 'urgent', 'routine'];
$quantities = [2, 1, 3, 2, 1];
$dayOffsets = [1, 2, 4, 3, 6];
$passwordHash = password_hash('BloodMatchDemo123!', PASSWORD_BCRYPT);
$requestRepo = new BloodRequestRepository();
$matchService = new MatchService();

echo "Seeding five Home examples for {$target['full_name']} ({$target['blood_type']}) in {$databaseName}.\n";

foreach ($people as $index => [$firstName, $lastName, $chapterId]) {
    $email = sprintf('home.example.%d@bloodmatch.local', $index + 1);
    $requestType = $recipientTypes[$index % count($recipientTypes)];
    $hospital = $hospitals[$index];

    $userStmt = $pdo->prepare(
        "INSERT INTO users
            (email, password_hash, first_name, last_name, role, chapter_id,
             verification_status, account_status, date_of_birth, blood_type,
             blood_type_source, blood_type_verified, email_verified_at)
         VALUES (?, ?, ?, ?, 'member', ?, 'verified', 'active', '1995-01-15', ?, 'self_reported', 0, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), password_hash = VALUES(password_hash), chapter_id = VALUES(chapter_id),
             verification_status = 'verified', account_status = 'active'"
    );
    $userStmt->execute([$email, $passwordHash, $firstName, $lastName, $chapterId, $requestType]);
    $requesterId = (int) $pdo->lastInsertId();

    $existingStmt = $pdo->prepare(
        "SELECT id FROM blood_requests
         WHERE requester_id = ? AND facility_name = ? AND status = 'OPEN'
           AND needed_datetime > UTC_TIMESTAMP()
         LIMIT 1"
    );
    $existingStmt->execute([$requesterId, $hospital['name']]);
    $existingId = $existingStmt->fetchColumn();
    if ($existingId !== false) {
        echo "Kept existing example at {$hospital['name']} ({$requestType}).\n";
        continue;
    }

    $neededAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
        ->modify('+' . $dayOffsets[$index] . ' days')
        ->setTime(9 + ($index * 2), 0)
        ->format('Y-m-d H:i:s');

    $requestId = $requestRepo->create([
        'requester_id' => $requesterId,
        'request_chapter_id' => $chapterId,
        'required_blood_type' => $requestType,
        'quantity_units' => $quantities[$index],
        'facility_name' => (string) $hospital['name'],
        'hospital_id' => (int) $hospital['id'],
        'location_id' => (int) $hospital['location_id'],
        'latitude' => (float) $hospital['latitude'],
        'longitude' => (float) $hospital['longitude'],
        'urgency' => $urgencies[$index],
        'needed_datetime' => $neededAt,
        'review_status' => 'not_required',
    ]);

    $matchService->generateForRequest($requestId, true, 'home_example_seed', null);
    echo "Created {$requestType} example at {$hospital['name']} ({$urgencies[$index]}).\n";
}

echo "Home examples are ready.\n";
