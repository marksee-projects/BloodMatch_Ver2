<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
ini_set('display_errors','0');ini_set('log_errors','0');
define('BASE_PATH',dirname(__DIR__));require BASE_PATH.'/backend/src/autoload.php';require __DIR__.'/operational_checkpoint_support.php';
use BloodMatch\Config\Env;
use BloodMatch\Services\EmailWorker;
use BloodMatch\Services\QueuedSmtpTransport;
$options=getopt('',['deliver','scheduled','job-id:']);$run=isset($options['deliver'])||isset($options['scheduled']);$result=['database'=>'bloodmatch','host'=>'127.0.0.1','port'=>3306,'smtp'=>$run,'status'=>'failed','exit_code'=>1];
try {
    $pdo=operationalTarget('application');$result['readiness']=operationalReadiness($pdo);
    $cutover=(string)Env::get('EMAIL_WORKER_START_AT_UTC','');$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$cutover,new \DateTimeZone('UTC'));
    $result['cutover_configured']=$parsed!==false && $parsed->format('Y-m-d H:i:s')===$cutover;
    $result['server_time_utc']=(string)$pdo->query('SELECT UTC_TIMESTAMP()')->fetchColumn();
    if (!$run) { $result['status']='review';$result['exit_code']=0; }
    else {
        if (Env::get('EMAIL_WORKER_ENABLED','0')!=='1' || !$result['readiness']['ready']) { throw new LogicException('Email worker disabled or schema not ready.'); }
        // An initial quiet 30-minute cutover avoids inventing historical donor reservations.
        $quiet=$pdo->query("SELECT applied_at<=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 MINUTE) FROM schema_migrations WHERE name='023_email_donor_reservations.sql'")->fetchColumn();
        if (!(bool)$quiet) { throw new LogicException('Wait for the 30-minute cutover window before real delivery.'); }
        if (isset($options['job-id'])) {
            if (!ctype_digit((string)$options['job-id']) || (int)$options['job-id']<1) { throw new LogicException('Invalid exact job ID.'); }
            $ids=[(int)$options['job-id']];
        } else {
            if (!isset($options['scheduled'])) { throw new LogicException('Manual delivery requires one reviewed job-id.'); }
            if (!$result['cutover_configured']) { throw new LogicException('Explicit automatic-delivery cutover timestamp required.'); }
            $s=$pdo->prepare("SELECT id FROM email_outbox WHERE created_at>=? AND ((status='pending' AND next_attempt_at<=UTC_TIMESTAMP(6)) OR (status='leased' AND lease_expires_at<=UTC_TIMESTAMP(6))) ORDER BY (priority='emergency') DESC,next_attempt_at,id LIMIT 100");
            $s->execute([$cutover]);$ids=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));
        }
        if (!is_dir(BASE_PATH.'/logs') && !mkdir(BASE_PATH.'/logs',0700,true)) { throw new LogicException('Logging unavailable.'); }
        $log=fopen(BASE_PATH.'/logs/application-email.jsonl','ab');if (!$log) { throw new LogicException('Logging unavailable.'); }fclose($log);
        $transport=new QueuedSmtpTransport();
        $result['worker']=(new EmailWorker(\Closure::fromCallable($transport),'bloodmatch',3306,'bm-application-email-worker'))->run($ids,\BloodMatch\Services\Mailer::isConfigured(),20);
        $result['status']='completed';$result['exit_code']=0;
    }
} catch (Throwable $e) { $result['status']='failed'; }
finally {
    $result['utc']=gmdate('Y-m-d\TH:i:s\Z');
    if ($run && @file_put_contents(BASE_PATH.'/logs/application-email.jsonl',json_encode($result).PHP_EOL,FILE_APPEND|LOCK_EX)===false) { $result['status']='log_failed';$result['exit_code']=1; }
    echo json_encode($result,JSON_UNESCAPED_SLASHES).PHP_EOL;
}
exit($result['exit_code']);
