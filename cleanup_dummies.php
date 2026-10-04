<?php
declare(strict_types=1);
define('BASE_PATH', __DIR__);
require BASE_PATH . '/backend/src/autoload.php';
use BloodMatch\Config\Database;
use BloodMatch\Config\Env;
Env::load(BASE_PATH . '/.env');

$pdo = Database::pdo();

// Delete old dummy requests and matches
$pdo->query("DELETE FROM matches WHERE request_id IN (SELECT id FROM blood_requests WHERE requester_id IN (SELECT id FROM users WHERE email LIKE 'dummyuser%'))");
$pdo->query("DELETE FROM blood_requests WHERE requester_id IN (SELECT id FROM users WHERE email LIKE 'dummyuser%')");
// Delete old dummy users
$pdo->query("DELETE FROM users WHERE email LIKE 'dummyuser%'");

echo "Old dummy users and their requests have been deleted.\n";
