<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
ini_set('display_errors','0');ini_set('log_errors','0');
define('BASE_PATH',dirname(__DIR__));require BASE_PATH.'/backend/src/autoload.php';require __DIR__.'/operational_checkpoint_support.php';
use BloodMatch\Config\Env;
use BloodMatch\Services\EmailWorker;
use BloodMatch\Services\QueuedSmtpTransport;
$options=getopt('',['deliver','scheduled','job-id:','recipient:','experiment']);$run=isset($options['deliver'])||isset($options['scheduled']);$experiment=isset($options['experiment']);$result=['database'=>'bloodmatch','host'=>'127.0.0.1','port'=>3306,'smtp'=>$run,'status'=>'failed','exit_code'=>1];
$result['experiment']=$experiment;
$result['invocation']=$run ? (isset($options['scheduled'])?'scheduled':'manual') : 'review';
try {
    $pdo=operationalTarget('application');$result['readiness']=operationalReadiness($pdo);
    $cutover=(string)Env::get('EMAIL_WORKER_START_AT_UTC','');$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$cutover,new \DateTimeZone('UTC'));
    $result['cutover_configured']=$parsed!==false && $parsed->format('Y-m-d H:i:s')===$cutover;
    $result['server_time_utc']=(string)$pdo->query('SELECT UTC_TIMESTAMP()')->fetchColumn();
    $result['enabled']=Env::get('EMAIL_WORKER_ENABLED','0')==='1';
    $result['smtp_configured']=\BloodMatch\Services\Mailer::isConfigured();
    $result['real_delivery_ready_at_utc']=$pdo->query("SELECT DATE_ADD(applied_at,INTERVAL 30 MINUTE) FROM schema_migrations WHERE name='023_email_donor_reservations.sql'")->fetchColumn();
    $result['quiet_cutover_ready']=$result['real_delivery_ready_at_utc']!==false && $result['real_delivery_ready_at_utc']<=$result['server_time_utc'];
    $recipient=isset($options['recipient'])?strtolower(trim((string)$options['recipient'])):null;
    if ($recipient!==null && !filter_var($recipient,FILTER_VALIDATE_EMAIL)) { throw new LogicException('Invalid recipient.'); }
    if (isset($options['job-id']) && (!ctype_digit((string)$options['job-id']) || (int)$options['job-id']<1)) { throw new LogicException('Invalid exact job ID.'); }
    if (isset($options['scheduled']) && ($recipient!==null || isset($options['job-id']) || $experiment)) { throw new LogicException('Scheduled sending cannot be combined with manual scope.'); }
    if (!$run) {
        if ($recipient!==null) {
            // Explicitly selected recipient only; never persist their address in worker logs.
            $s=$pdo->prepare('SELECT id,role,verification_status,account_status,email_verified_at FROM users WHERE email=?');$s->execute([$recipient]);
            $accounts=$s->fetchAll(PDO::FETCH_ASSOC);$result['recipient_review']=['email'=>$recipient,'matching_accounts'=>count($accounts),'accounts'=>[]];
            $preview=new EmailWorker(static function(): never { throw new LogicException('Transport forbidden in review.'); },'bloodmatch',3306,'bm-application-email-worker');
            foreach ($accounts as $account) {
                $exact=isset($options['job-id'])?' AND e.id=?':'';
                $s=$pdo->prepare("SELECT e.*,n.type AS notification_type FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.user_id=? AND e.status IN ('pending','leased')$exact ORDER BY e.created_at DESC,e.id DESC LIMIT 20");$s->execute(isset($options['job-id'])?[$account['id'],(int)$options['job-id']]:[$account['id']]);
                $jobs=array_map([$preview,'review'],$s->fetchAll(PDO::FETCH_ASSOC));
                $result['recipient_review']['accounts'][]=['id'=>(int)$account['id'],'role'=>$account['role'],'verification_status'=>$account['verification_status'],
                    'account_status'=>$account['account_status'],'email_verified'=>!empty($account['email_verified_at']),'pending_jobs'=>$jobs];
            }
        }
        $result['status']='review';$result['exit_code']=0;
    }
    else {
        if (!$result['readiness']['ready'] || (!$experiment && !$result['enabled'])) { throw new LogicException('Email worker disabled or schema not ready.'); }
        if ($experiment && (!isset($options['deliver']) || !isset($options['job-id']) || $recipient===null)) { throw new LogicException('Experiment needs --deliver, one --job-id and --recipient.'); }
        // An initial quiet 30-minute cutover avoids inventing historical donor reservations.
        $quiet=$pdo->query("SELECT applied_at<=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 MINUTE) FROM schema_migrations WHERE name='023_email_donor_reservations.sql'")->fetchColumn();
        if (!(bool)$quiet) { throw new LogicException('Wait for the 30-minute cutover window before real delivery.'); }
        if (isset($options['job-id'])) {
            if (!ctype_digit((string)$options['job-id']) || (int)$options['job-id']<1) { throw new LogicException('Invalid exact job ID.'); }
            $ids=[(int)$options['job-id']];
            if ($recipient===null) { throw new LogicException('Manual experiment requires the reviewed recipient.'); }
            $s=$pdo->prepare('SELECT u.email FROM email_outbox e JOIN notifications n ON n.id=e.notification_id JOIN users u ON u.id=n.user_id WHERE e.id=?');$s->execute($ids);
            if (strtolower((string)$s->fetchColumn())!==$recipient) { throw new LogicException('Reviewed job/recipient mismatch.'); }
            if ($experiment) {
                $s=$pdo->prepare('SELECT e.*,n.type AS notification_type FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE e.id=?');$s->execute($ids);$job=$s->fetch(\PDO::FETCH_ASSOC);
                if (!$job) { throw new LogicException('Experiment job missing.'); }
                $preview=new EmailWorker(static function(): never { throw new LogicException('Transport forbidden in preview.'); },'bloodmatch',3306,'bm-application-email-worker');
                \BloodMatch\Services\EmailExperimentPolicy::assertMembershipScope($result['enabled'],false,$ids[0],true,$preview->review($job));
                $result['scope']=['job_ids'=>$ids,'notification_type'=>'verification.decision'];
            }
        } else {
            if (!isset($options['scheduled'])) { throw new LogicException('Manual delivery requires one reviewed job-id.'); }
            if (!$result['cutover_configured']) { throw new LogicException('Explicit automatic-delivery cutover timestamp required.'); }
            $s=$pdo->prepare("SELECT id FROM email_outbox WHERE created_at>=? AND ((status='pending' AND next_attempt_at<=UTC_TIMESTAMP(6)) OR (status='leased' AND lease_expires_at<=UTC_TIMESTAMP(6))) ORDER BY (priority='emergency') DESC,next_attempt_at,id LIMIT 100");
            $s->execute([$cutover]);$ids=array_map('intval',$s->fetchAll(PDO::FETCH_COLUMN));
        }
        if (!is_dir(BASE_PATH.'/logs') && !mkdir(BASE_PATH.'/logs',0700,true)) { throw new LogicException('Logging unavailable.'); }
        $log=fopen(BASE_PATH.'/logs/application-email.jsonl','ab');if (!$log) { throw new LogicException('Logging unavailable.'); }fclose($log);
        $transport=new QueuedSmtpTransport($recipient);
        $result['worker']=(new EmailWorker(\Closure::fromCallable($transport),'bloodmatch',3306,'bm-application-email-worker'))->run($ids,\BloodMatch\Services\Mailer::isConfigured(),$experiment?1:20);
        $result['status']='completed';$result['exit_code']=0;
    }
} catch (Throwable $e) { $result['status']='failed'; }
finally {
    $result['utc']=gmdate('Y-m-d\TH:i:s\Z');
    if ($run && @file_put_contents(BASE_PATH.'/logs/application-email.jsonl',json_encode($result).PHP_EOL,FILE_APPEND|LOCK_EX)===false) { $result['status']='log_failed';$result['exit_code']=1; }
    echo json_encode($result,JSON_UNESCAPED_SLASHES).PHP_EOL;
}
exit($result['exit_code']);
