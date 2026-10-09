<?php
declare(strict_types=1);
require dirname(__DIR__) . '/backend/src/autoload.php';
use BloodMatch\Services\EmailAttemptBudget as Budget;

$passed = 0;
function check(bool $ok, string $label): void {
    global $passed;
    if (!$ok) { throw new RuntimeException('FAIL ' . $label); }
    $passed++; echo 'PASS ' . $label . PHP_EOL;
}
foreach ([Budget::NORMAL, Budget::EMERGENCY] as $class) {
    check(Budget::denial($class, 89, 399, 89, 399) === null, "$class last unprotected slot");
    check(Budget::denial($class, 90, 90, 90, 90) === 'protected_hour', "$class hourly protection");
    check(Budget::denial($class, 0, 400, 0, 400) === 'protected_day', "$class daily protection");
}
check(Budget::denial(Budget::ACCOUNT, 90, 400, 90, 400) === null, 'account uses protected slots');
check(Budget::denial(Budget::ACCOUNT, 99, 449, 0, 0) === null, 'account may use all remaining total capacity');
foreach ([Budget::ACCOUNT, Budget::NORMAL, Budget::EMERGENCY] as $class) {
    check(Budget::denial($class, 100, 100, 0, 0) === 'total_hour', "$class total hourly cap");
    check(Budget::denial($class, 0, 450, 0, 0) === 'total_day', "$class total daily cap");
}
try { Budget::denial('unknown', 0, 0, 0, 0); check(false, 'invalid class'); }
catch (InvalidArgumentException $e) { check(true, 'invalid class rejected'); }

class BudgetPDO extends PDO {
    public array $events = [];
    public bool $active = false;
    public bool $failInsert = false;
    public array $counts = ['hour_total' => 0, 'day_total' => 0, 'hour_other' => 0, 'day_other' => 0];
    public function __construct() {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false { $s = new BudgetStatement($this, $query); $s->execute(); return $s; }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new BudgetStatement($this, $query); }
    public function inTransaction(): bool { return $this->active; }
    public function beginTransaction(): bool { $this->events[] = 'begin'; $this->active = true; return true; }
    public function commit(): bool { $this->events[] = 'commit'; $this->active = false; return true; }
    public function rollBack(): bool { $this->events[] = 'rollback'; $this->active = false; return true; }
    public function lastInsertId(?string $name = null): string|false { return '7'; }
}
class BudgetStatement extends PDOStatement {
    public function __construct(private BudgetPDO $pdo, private string $sql) {}
    public function execute(?array $params = null): bool {
        $kind = str_contains($this->sql, 'GET_LOCK') ? 'lock' : (str_contains($this->sql, 'RELEASE_LOCK') ? 'release'
            : (str_starts_with($this->sql, 'INSERT') ? 'insert' : (str_contains($this->sql, 'COUNT(*)') ? 'counts'
                : (str_contains($this->sql, 'UTC_TIMESTAMP') ? 'clock' : 'target'))));
        $this->pdo->events[] = $kind;
        if ($kind === 'insert' && $this->pdo->failInsert) { throw new RuntimeException('synthetic failure'); }
        return true;
    }
    public function fetchColumn(int $column = 0): mixed {
        return str_contains($this->sql, 'DATABASE()') ? 'offline'
            : (str_contains($this->sql, 'UTC_TIMESTAMP') ? '2026-10-09 12:00:00.123456' : 1);
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return $this->pdo->counts; }
}
$pdo = new BudgetPDO();
$result = Budget::reserve(Budget::ACCOUNT, $pdo);
check($result === ['allowed' => true, 'reason' => null, 'attempt_id' => 7]
    && $pdo->events === ['target', 'lock', 'begin', 'clock', 'counts', 'insert', 'commit', 'release'], 'lock before snapshot; commit and release before transport');
$pdo->events = []; $pdo->counts['hour_total'] = 100;
check(!Budget::reserve(Budget::ACCOUNT, $pdo)['allowed'] && !in_array('insert', $pdo->events, true)
    && array_slice($pdo->events, -2) === ['commit', 'release'], 'deferral consumes no attempt');
$pdo->events = []; $pdo->counts['hour_total'] = 0; $pdo->failInsert = true;
try { Budget::reserve(Budget::ACCOUNT, $pdo); check(false, 'insert failure'); }
catch (RuntimeException $e) { check(array_slice($pdo->events, -2) === ['rollback', 'release'], 'accounting failure rolls back and releases lock'); }
$pdo->active = true; $pdo->events = [];
try { Budget::reserve(Budget::ACCOUNT, $pdo); check(false, 'nested reservation'); }
catch (LogicException $e) { check($pdo->events === [], 'workflow connection refused without side effects'); }
echo "$passed offline email accounting checks passed\n";
