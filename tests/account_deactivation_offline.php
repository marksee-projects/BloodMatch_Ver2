<?php
declare(strict_types=1);
// Exercise real middleware denial/session destruction in child CLI processes, with fake storage.
namespace BloodMatch\Http {
    final class Session {
        public static bool $destroyed = false;
        public static function start(): void { $_SESSION = ['user_id'=>7]; }
        public static function destroy(): void { self::$destroyed = true; $_SESSION = []; }
    }
}
namespace BloodMatch\Repositories {
    final class UserRepository {
        public function findById(int $id): ?array {
            $case=$GLOBALS['argv'][1]??'';
            return $case==='missing' ? null : ['id'=>$id,'role'=>'member','account_status'=>$case==='active'?'active':'deactivated',
                'disabled_until'=>$case==='temporary'?gmdate('Y-m-d H:i:s',time()+86400):null];
        }
    }
}
namespace BloodMatch\Services {
    final class SessionIdentityPolicy { public static function matches(array $user,array $session): bool { return true; } }
    final class AuditLogger { public static function log(...$args): void {} }
}
namespace BloodMatch\Utils {
    final class Response { public static function error(string $message,int $status=400,array $details=[]): void {
        echo json_encode(['status'=>$status,'session_destroyed'=>\BloodMatch\Http\Session::$destroyed]);
    } }
}
namespace {
    if(isset($argv[1])) {
        require dirname(__DIR__).'/backend/src/Middleware/AuthMiddleware.php';
        \BloodMatch\Middleware\AuthMiddleware::requireActiveUser('offline.auth.me');
        echo json_encode(['status'=>200,'session_destroyed'=>\BloodMatch\Http\Session::$destroyed]); exit;
    }
    foreach(['deactivated','temporary','missing','active'] as $case) {
        $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,$case],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($process))throw new RuntimeException('Cannot start test child.');
        fclose($pipes[0]);$result=json_decode(stream_get_contents($pipes[1]),true);$error=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
        if($code!==0||!is_array($result)||$result['status']!==($case==='active'?200:403)||$result['session_destroyed']!==($case!=='active'))throw new RuntimeException('FAIL '.$case.' '.$error);
        echo 'PASS '.$case.' session handling'.PHP_EOL;
    }
    echo "4 middleware/session checks passed; fake storage, no database/server/mail.\n";
}
