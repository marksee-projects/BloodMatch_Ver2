<?php
declare(strict_types=1);
define('BASE_PATH', __DIR__);
require BASE_PATH . '/backend/src/autoload.php';
use BloodMatch\Config\Database;
use BloodMatch\Config\Env;
Env::load(BASE_PATH . '/.env');

Database::pdo()->query('DELETE FROM matches WHERE request_id = 6');

echo "Regenerating matches...\n";
$summary = (new \BloodMatch\Services\MatchService())->generateForRequest(6, true, 'manual', 1);
print_r($summary);

$stmt = Database::pdo()->query('SELECT m.*, u.first_name, u.last_name, u.blood_type FROM matches m JOIN users u ON m.donor_id = u.id WHERE m.request_id = 6');
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

print_r($matches);
