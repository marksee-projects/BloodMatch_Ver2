<?php
declare(strict_types=1);
require dirname(__DIR__) . '/backend/src/autoload.php';
use BloodMatch\Config\Database;
use BloodMatch\Services\EmailEnqueueService as Queue;
use BloodMatch\Services\Exceptions\EmailEnqueueException;

$passed = 0;
function check(bool $ok, string $label): void {
    global $passed;
    if (!$ok) { throw new RuntimeException('FAIL ' . $label); }
    $passed++; echo 'PASS ' . $label . PHP_EOL;
}
check(Queue::priority('match.new', 'emergency') === 'emergency', 'Emergency opportunity approved');
check(Queue::priority('verification.decision', 'normal') === 'normal', 'membership decision approved');
foreach (['match.responded', 'donation.confirmed', 'donation.rejected', 'account.status_changed', 'verification.resubmitted', 'request.expired', 'request.cancelled', 'request.fulfilled'] as $type) {
    check(Queue::priority($type, 'normal') === null && Queue::priority($type, 'emergency') === null, "$type excluded from email");
}
check(Queue::priority('match.new', 'none') === null && Queue::priority('match.new', 'normal') === null, 'Routine/Urgent and rematch do not queue');
class EnqueuePDO extends PDO {
    public bool $active = false;
    public array $events = [];
    public function __construct() {}
    public function inTransaction(): bool { return $this->active; }
    public function beginTransaction(): bool { $this->events[] = 'begin'; $this->active = true; return true; }
    public function commit(): bool { $this->events[] = 'commit'; $this->active = false; return true; }
    public function rollBack(): bool { $this->events[] = 'rollback'; $this->active = false; return true; }
    public function exec(string $statement): int|false { $this->events[] = $statement; return 0; }
}
$pdo = new EnqueuePDO(); (new ReflectionProperty(Database::class, 'pdo'))->setValue(null, $pdo);
check(Queue::transaction(fn () => 7) === 7 && $pdo->events === ['begin', 'commit'], 'owned success commits once');
$pdo->events = [];
try { Queue::transaction(fn () => throw new RuntimeException('synthetic SQL failure')); check(false, 'failure'); }
catch (EmailEnqueueException $e) { check($e->getCode() === 503 && !str_contains($e->getMessage(), 'synthetic') && $pdo->events === ['begin', 'rollback'], 'owned failure rolls back with sanitized 503'); }
$pdo->events = []; $pdo->active = true;
check(Queue::transaction(fn () => 8) === 8 && $pdo->active
    && $pdo->events === ['SAVEPOINT bm_email_enqueue', 'RELEASE SAVEPOINT bm_email_enqueue'], 'nested success leaves owner uncommitted');
$pdo->events = [];
try { Queue::transaction(fn () => throw new RuntimeException('synthetic')); check(false, 'nested failure'); }
catch (EmailEnqueueException $e) { check($pdo->active && $pdo->events === ['SAVEPOINT bm_email_enqueue', 'ROLLBACK TO SAVEPOINT bm_email_enqueue', 'RELEASE SAVEPOINT bm_email_enqueue'], 'nested failure removes partial enqueue even if owner catches error'); }
echo "$passed offline enqueue checks passed\n";
