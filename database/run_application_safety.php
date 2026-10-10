<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
ini_set('display_errors','0');ini_set('log_errors','0');
define('BASE_PATH',dirname(__DIR__));require BASE_PATH.'/backend/src/autoload.php';require __DIR__.'/operational_checkpoint_support.php';
use BloodMatch\Config\Env;
use BloodMatch\Services\RequestLifecycleService;
use BloodMatch\Services\NonResponseStandbyService;
use BloodMatch\Services\AuthService;
$run=in_array('--run',$argv,true)||in_array('--scheduled',$argv,true);$lock=null;$status=['database'=>'bloodmatch','host'=>'127.0.0.1','port'=>3306,'writes'=>$run,'expired'=>0,'standby'=>0,'status'=>'failed','exit_code'=>1];
$status['invocation']=$run ? (in_array('--scheduled',$argv,true)?'scheduled':'manual') : 'review';
try {
    $pdo=operationalTarget('application');$status['readiness']=operationalReadiness($pdo,false);
    if (!$run) {
        $status['enabled']=Env::get('SAFETY_AUTOMATION_ENABLED','0')==='1';
        $status['due_open_requests']=(int)$pdo->query("SELECT COUNT(*) FROM blood_requests WHERE status='OPEN' AND needed_datetime<UTC_TIMESTAMP()")->fetchColumn();
        if ($status['readiness']['ready']) {
            // Read-only candidate count, not a promise of donor transitions after live locking checks.
            $status['opportunity_timer_candidates']=(int)$pdo->query("SELECT COUNT(*) FROM donor_opportunities o
                JOIN blood_requests r ON r.id=o.request_id JOIN matches m ON m.request_id=o.request_id AND m.donor_id=o.donor_id AND m.generation=o.generation
                LEFT JOIN donor_response_state s ON s.user_id=o.donor_id
                WHERE o.status='pending' AND o.due_at<=UTC_TIMESTAMP(6) AND r.status='OPEN' AND r.needed_datetime>=UTC_TIMESTAMP()
                AND m.status IN ('POTENTIAL','NOTIFIED') AND s.standby_started_at IS NULL AND (s.consumed_through IS NULL OR o.due_at>s.consumed_through)")->fetchColumn();
        }
        $status['status']='review';$status['exit_code']=0;
    }
    else {
        if (Env::get('SAFETY_AUTOMATION_ENABLED','0')!=='1' || !$status['readiness']['ready']) { throw new LogicException('Safety processing is disabled or schema is not ready.'); }
        if (!is_dir(BASE_PATH.'/logs') && !mkdir(BASE_PATH.'/logs',0700,true)) { throw new LogicException('Logging unavailable.'); }
        $lock=fopen(BASE_PATH.'/logs/application-safety.lock','c');
        if (!$lock) { throw new LogicException('Lock unavailable.'); }
        if (!flock($lock,LOCK_EX|LOCK_NB)) { $status['status']='overlap_skipped';$status['exit_code']=0; }
        else {
            $log=fopen(BASE_PATH.'/logs/application-safety.jsonl','ab');if (!$log) { throw new LogicException('Logging unavailable.'); }fclose($log);
            foreach ((new RequestLifecycleService())->expireDueRequests(AuthService::nowUtc(),500) as $row) { $status['expired']++; }
            $status['standby']=NonResponseStandbyService::processDue();$status['status']='completed';$status['exit_code']=0;
        }
    }
} catch (Throwable $e) { $status['status']='failed'; }
finally {
    $status['utc']=gmdate('Y-m-d\TH:i:s\Z');unset($status['readiness']['recorded']);
    $json=json_encode($status,JSON_UNESCAPED_SLASHES);
    if ($run && @file_put_contents(BASE_PATH.'/logs/application-safety.jsonl',$json.PHP_EOL,FILE_APPEND|LOCK_EX)===false) { $status['status']='log_failed';$status['exit_code']=1; }
    echo json_encode($status,JSON_UNESCAPED_SLASHES).PHP_EOL;
    if (is_resource($lock)) { flock($lock,LOCK_UN);fclose($lock); }
}
exit($status['exit_code']);
