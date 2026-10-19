<?php
declare(strict_types=1);

// Execute the real renewal controller with isolated in-memory collaborators.
// No autoloader, environment, PDO connection, files, notifications or SMTP.
namespace BloodMatch\Tests {
    final class State {
        public static array $old = ['id'=>10,'requester_id'=>7,'status'=>'EXPIRED','needed_datetime'=>'past'];
        public static array $created = [];
        public static array $matching = [];
        public static array $response = [];
        public static bool $successor = false;
        public static int $actor = 7;
    }
}
namespace BloodMatch\Config {
    class Database {
        public static function pdo(): object { return new class {
            public function prepare(string $sql): object { return new class {
                public function execute(array $params): void {}
                public function fetchColumn(): mixed { return \BloodMatch\Tests\State::$successor ? 11 : false; }
            }; }
        }; }
    }
}
namespace BloodMatch\Middleware {
    class AuthMiddleware { public static function requireActiveUser(string $action): array { return ['id'=>\BloodMatch\Tests\State::$actor]; } }
}
namespace BloodMatch\Repositories {
    class BloodRequestRepository {
        public function create(array $row): int { \BloodMatch\Tests\State::$created[]=$row; return 11; }
    }
}
namespace BloodMatch\Services {
    use BloodMatch\Tests\State;
    class WorkflowLockService {
        public static function transaction(callable $work): mixed { return $work(); }
        public static function request(int $id): array { return State::$old; }
        public static function users(array $ids): array { return [State::$actor=>['id'=>State::$actor]]; }
    }
    class RequestLifecycleService {
        public static function assertCanEdit(array $actor,array $row): void {
            if ($actor['id']!==$row['requester_id']) throw new \RuntimeException('Forbidden.',403);
        }
    }
    class RequestService {
        public static function assertCanCreate(array $actor): void {}
        public static function validatePayload(array $body,bool $partial=false): array { return $body; }
        public static function hasDeadlinePassed(array $row): bool { return $row['needed_datetime']==='past'; }
        public static function reviewStatusFor(array $actor): string { return 'not_required'; }
    }
    class MatchService {
        // Same required arguments as the production matching contract.
        public function generateForRequest(int $requestId,bool $bumpGeneration,string $trigger,?int $actorId=null,?int $onlyDonorId=null): array {
            State::$matching[]=func_get_args(); return [];
        }
    }
    class AuditLogger { public static function log(...$args): void {} }
}
namespace BloodMatch\Utils {
    class Request { public static function str(string $key): string { return 'future'; } }
    class Response {
        public static function success(array $body): void { \BloodMatch\Tests\State::$response=[200,$body]; }
        public static function error(string $message,int $status): void { \BloodMatch\Tests\State::$response=[$status,$message]; }
    }
}
namespace {
    use BloodMatch\Tests\State;
    require dirname(__DIR__).'/backend/src/Controllers/RequestActionsController.php';
    function check(string $name,bool $ok): void { if (!$ok) throw new RuntimeException('FAIL '.$name); echo 'PASS '.$name.PHP_EOL; }
    $controller=new \BloodMatch\Controllers\RequestActionsController();
    $controller->renew(['id'=>10]);
    check('Expired renewal completes instead of missing-argument failure',State::$response[0]===200);
    check('Renewal creates a new linked post',State::$response[1]['request']===['id'=>11,'renewed_from_id'=>10] && count(State::$created)===1);
    check('Matching uses new request, generation and owner context',State::$matching===[[11,true,'request_created',7]]);
    check('Original expired record is preserved',State::$old['status']==='EXPIRED' && State::$old['needed_datetime']==='past');
    State::$successor=true;
    $controller->renew(['id'=>10]);
    check('Repeated renewal is blocked without creating/matching again',State::$response[0]===409 && count(State::$created)===1 && count(State::$matching)===1);
    State::$actor=9;
    $controller->renew(['id'=>10]);
    check('Another account cannot renew the request',State::$response[0]===403 && count(State::$created)===1);
    State::$actor=7; State::$successor=false; State::$old['status']='CANCELLED';
    $controller->renew(['id'=>10]);
    check('Cancelled request cannot be renewed',State::$response[0]===409 && count(State::$created)===1);
    $repo=file_get_contents(dirname(__DIR__).'/backend/src/Repositories/BloodRequestRepository.php');
    $feed=substr($repo,strpos($repo,'public function pageCompatibleOpenForViewer('));
    $feed=substr($feed,0,strpos($feed,'public function findCompatibleOpenForViewer('));
    check('Home SQL sorts newest first before pagination with stable ID tie break',str_contains($feed,'ORDER BY br.created_at DESC, br.id DESC') && !str_contains($feed,'ORDER BY FIELD('));
    echo "8 offline renewal/feed checks passed; no database or SMTP.\n";
}
