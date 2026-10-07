<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/backend/src/autoload.php';

use BloodMatch\Config\Database;
use BloodMatch\Config\Env;
use BloodMatch\Repositories\BloodRequestRepository;
use BloodMatch\Repositories\UserRepository;
use BloodMatch\Services\BloodCompatibilityService;

Env::load(BASE_PATH . '/.env');
$pdo = Database::pdo();

if ((string) $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'bloodmatch_test') {
    fwrite(STDERR, "This regression test runs only against bloodmatch_test.\n");
    exit(1);
}

$pdo->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(5));
    $passwordHash = password_hash('Compatibility123!', PASSWORD_BCRYPT);
    $insertUser = $pdo->prepare(
        "INSERT INTO users
            (email, password_hash, first_name, last_name, role, chapter_id, verification_status,
             account_status, date_of_birth, blood_type, blood_type_source, blood_type_verified,
             donor_availability, email_verified_at)
         VALUES (?, ?, ?, ?, 'member', ?, 'verified', 'active', '1995-01-01', ?, 'self_reported', 0, ?, UTC_TIMESTAMP())"
    );

    $insertUser->execute(["home_donor_{$suffix}@example.test", $passwordHash, 'Home', 'Donor', 1, 'A-', 'unavailable']);
    $donorId = (int) $pdo->lastInsertId();

    $requestRepo = new BloodRequestRepository();
    $compatibleTypes = BloodCompatibilityService::getCompatibleRecipientTypesForDonor('A-');
    $expectedTypes = ['A-', 'A+', 'AB-', 'AB+'];
    sort($compatibleTypes);
    sort($expectedTypes);
    if ($compatibleTypes !== $expectedTypes) {
        throw new RuntimeException('A- inverse compatibility matrix is incorrect.');
    }

    $createdRequestIds = [];
    $incompatibleRequestId = null;
    foreach (['A-', 'A+', 'AB-', 'AB+', 'O+'] as $index => $requestType) {
        $insertUser->execute([
            "home_requester_{$index}_{$suffix}@example.test",
            $passwordHash,
            'Requester',
            (string) ($index + 1),
            ($index % 3) + 1,
            $requestType,
            null,
        ]);
        $requesterId = (int) $pdo->lastInsertId();
        $createdRequestIds[$requestType] = $requestRepo->create([
            'requester_id' => $requesterId,
            'request_chapter_id' => ($index % 3) + 1,
            'required_blood_type' => $requestType,
            'quantity_units' => 1,
            'facility_name' => "Compatibility Facility {$index}",
            'hospital_id' => null,
            'location_id' => null,
            'latitude' => null,
            'longitude' => null,
            'urgency' => 'emergency',
            'needed_datetime' => gmdate('Y-m-d H:i:s', time() + 300 + ($index * 60)),
            'review_status' => 'not_required',
        ]);
        if ($requestType === 'O+') {
            $incompatibleRequestId = $createdRequestIds[$requestType];
        }
    }

    $rows = $requestRepo->listCompatibleOpenForDonor($donorId, $compatibleTypes, 100);
    $fixtureRows = array_values(array_filter(
        $rows,
        static fn (array $row): bool => in_array((int) $row['id'], array_values($createdRequestIds), true)
    ));
    $actualTypes = array_values(array_unique(array_column($fixtureRows, 'required_blood_type')));
    sort($actualTypes);
    if ($actualTypes !== $expectedTypes || count($fixtureRows) !== 4
        || in_array($incompatibleRequestId, array_map(static fn (array $row): int => (int) $row['id'], $rows), true)) {
        throw new RuntimeException('Home feed did not return exactly the four blood-compatible requests.');
    }
    foreach ($fixtureRows as $row) {
        if ($row['match_id'] !== null || $row['match_status'] !== null) {
            throw new RuntimeException('Compatibility browsing unexpectedly requires a persisted donor match.');
        }
    }

    $context = $requestRepo->findRequestContext((int) $fixtureRows[0]['id'], $donorId);
    if ($context === null || empty($context['requester_name']) || !array_key_exists('requester_chapter_name', $context)) {
        throw new RuntimeException('Request detail is missing requester card information.');
    }

    $donor = (new UserRepository())->findById($donorId);
    if (($donor['chapter_name'] ?? null) !== 'Mt. Samat Chapter') {
        throw new RuntimeException('Own profile data is missing the member chapter name.');
    }

    echo "PASS Home compatibility ignores proximity and persisted-match membership.\n";
    echo "PASS Request detail includes requester card information.\n";
    echo "PASS Own profile exposes the assigned chapter name.\n";
    $pdo->rollBack();
    exit(0);
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'FAIL ' . $error->getMessage() . "\n");
    exit(1);
}
