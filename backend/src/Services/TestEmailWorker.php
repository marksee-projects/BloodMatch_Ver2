<?php
declare(strict_types=1);
namespace BloodMatch\Services;

use BloodMatch\Config\Database;
use BloodMatch\Repositories\EmailOutboxRepository;
use BloodMatch\Repositories\UserRepository;

/** Manual test-only orchestration: callers MUST supply a fake transport.
 * No SMTP adapter/automatic schedule exists in this checkpoint.
 */
final class TestEmailWorker
{
    public function __construct(private \Closure $fakeTransport) {}

    public function run(array $ids, bool $configured = true, int $limit = 20): array
    {
        $pdo = Database::pdo();
        if ($pdo->inTransaction() || $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'bloodmatch_test'
            || (int) $pdo->query('SELECT @@port')->fetchColumn() !== 3306) {
            throw new \LogicException('Test worker requires idle bloodmatch_test:3306.');
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (count($ids)>100 || array_filter($ids, fn ($id)=>$id<1) || $limit<1 || $limit>20) { throw new \LogicException('Invalid bounded job scope.'); }
        $summary = ['status'=>'completed','claimed'=>0,'sent'=>0,'failed'=>0,'cancelled'=>0,'deferred'=>0,'lost'=>0];
        // Serializes recipient acceptance limits across manual/parallel test runs.
        $lockName = 'bm-test-email-worker';
        $s = $pdo->prepare('SELECT GET_LOCK(?,0)'); $s->execute([$lockName]);
        if ((int) $s->fetchColumn()!==1) { $summary['status']='overlap_skipped'; return $summary; }
        $start = microtime(true); $repo = new EmailOutboxRepository();
        try {
            while ($summary['claimed']<$limit && microtime(true)-$start<45) {
                $job = $repo->claim($ids,$this->now());
                if ($job===null) { break; }
                $summary['claimed']++;
                $now = $this->now();
                [$context,$cancel] = $this->context($job,$now);
                if ($cancel!==null) {
                    $ok=$repo->finish($job,'cancelled',$now,$cancel); $summary[$ok?'cancelled':'lost']++; continue;
                }
                if ((int) $job['attempts']>=6) {
                    $ok=$repo->finish($job,'failed',$now,'attempts_exhausted'); $summary[$ok?'failed':'lost']++; continue;
                }
                if (!$configured) {
                    $ok=$repo->finish($job,'pending',$now,'smtp_unconfigured',$this->later($now,300)); $summary[$ok?'deferred':'lost']++; continue;
                }
                if ($job['priority']==='normal') {
                    $s=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND emailed_at>DATE_SUB(?,INTERVAL 1 HOUR)');
                    $s->execute([$context['user']['id'],$now]);
                    if ((int) $s->fetchColumn()>=5) {
                        $ok=$repo->finish($job,'pending',$now,'recipient_limit',$this->later($now,60)); $summary[$ok?'deferred':'lost']++; continue;
                    }
                }
                $jobs=[$job];
                if ($job['priority']==='emergency') {
                    $ready=EmailWorkerPolicy::digestReadyAt($job['created_at']);
                    if ($now<$ready) {
                        $ok=$repo->finish($job,'pending',$now,'digest_grouping',$ready); $summary[$ok?'deferred':'lost']++; continue;
                    }
                    $s=$pdo->prepare("SELECT COUNT(DISTINCT e.sent_at) FROM email_outbox e JOIN notifications n ON n.id=e.notification_id
                        WHERE n.user_id=? AND e.priority='emergency' AND e.status='sent' AND e.sent_at>DATE_SUB(?,INTERVAL 30 MINUTE)");
                    $s->execute([$context['user']['id'],$now]);
                    if ((int)$s->fetchColumn()>=2) {
                        $ok=$repo->finish($job,'pending',$now,'emergency_recipient_limit',$this->later($now,60)); $summary[$ok?'deferred':'lost']++; continue;
                    }
                    $jobs=$repo->claimDigest($job,$ids,$limit-$summary['claimed']+1,$now);
                    $summary['claimed']+=count($jobs)-1;
                }
                $active=[];
                foreach ($jobs as $candidate) {
                    [, $cancel]=$this->context($candidate,$this->now());
                    if ($cancel!==null || (int)$candidate['attempts']>=6) {
                        $ok=$repo->finish($candidate,$cancel===null?'failed':'cancelled',$this->now(),$cancel??'attempts_exhausted');
                        $summary[$ok?($cancel===null?'failed':'cancelled'):'lost']++;
                    } else { $active[]=$candidate; }
                }
                if ($active===[]) { continue; }
                $attemptStarted=false;
                $result=Mailer::attemptWithTransport($context['mail_class'], function () use ($repo,&$active,$context,&$attemptStarted,&$summary) {
                    foreach ($active as $index=>$candidate) {
                        [, $cancel]=$this->context($candidate,$this->now());
                        if ($cancel!==null) {
                            $ok=$repo->finish($candidate,'cancelled',$this->now(),$cancel);
                            $summary[$ok?'cancelled':'lost']++; unset($active[$index]);
                        }
                    }
                    $active=array_values($active);
                    if ($active===[]) { return false; }
                    $attemptStarted=$repo->beginDigestAttempt($active,$this->now());
                    if (!$attemptStarted) { return false; }
                    $keys=array_column($active,'delivery_key'); sort($keys,SORT_STRING);
                    return ($this->fakeTransport)([
                        'job_id'=>(int)$active[0]['id'], 'job_ids'=>array_map('intval',array_column($active,'id')),
                        'message_id'=>'<'.hash('sha256',implode('|',$keys)).'@bloodmatch.invalid>',
                        'mail_class'=>$context['mail_class'],
                    ]);
                });
                $now=$this->now();
                if ($active===[]) { continue; }
                if ($result['status']==='deferred') {
                    $ok=$repo->finishDigest($active,'pending',$now,$result['code'],$this->later($now,60));
                    $summary[$ok?'deferred':'lost']+=count($active); continue;
                }
                if (!$attemptStarted) { $summary['lost']+=count($active); continue; }
                if ($result['status']==='sent') {
                    $ok=$repo->finishDigest($active,'sent',$now,$result['code']); $summary[$ok?'sent':'lost']+=count($active);
                } else {
                    foreach ($active as $candidate) {
                        $next=EmailWorkerPolicy::retry((int)$candidate['attempts']+1,$now);
                        $ok=$repo->finish($candidate,$next['status'],$now,$result['code'],$next['next']);
                        $summary[$ok?($next['status']==='pending'?'deferred':'failed'):'lost']++;
                    }
                }
            }
            return $summary;
        } finally { $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]); }
    }

    private function now(): string { return (string)Database::pdo()->query('SELECT UTC_TIMESTAMP(6)')->fetchColumn(); }
    private function later(string $now,int $seconds): string { return gmdate('Y-m-d H:i:s',strtotime($now.' UTC')+$seconds); }

    private function context(array $job,string $now): array
    {
        $pdo=Database::pdo();
        $s=$pdo->prepare('SELECT * FROM notifications WHERE id=?'); $s->execute([$job['notification_id']]); $n=$s->fetch(\PDO::FETCH_ASSOC);
        if ($n===false) { return [null,'notification_missing']; }
        $class=EmailWorkerPolicy::mailClass($n['type'],$job['priority']);
        if ($class===null) { return [null,'channel_excluded']; }
        if (EmailWorkerPolicy::expired($job['expires_at'],$now)) { return [null,'expired']; }
        $user=(new UserRepository())->findById((int)$n['user_id']);
        if ($user===null || !filter_var($user['email'],FILTER_VALIDATE_EMAIL)) { return [null,'recipient_invalid']; }
        if (empty($user['email_verified_at'])) { return [null,'recipient_unverified']; }
        if ($class===EmailAttemptBudget::ACCOUNT) {
            if (!preg_match('/^verification:(\d+):(verified|rejected):decision:(\d+)$/D',(string)$n['dedup_key'],$event)
                || (int)$event[1] !== (int)$user['id'] || $n['related_type']!=='verification' || (int)$n['related_id']!==(int)$user['id']) { return [null,'invalid_event']; }
            $s=$pdo->prepare('SELECT id,decision FROM verification_decisions WHERE target_user_id=? ORDER BY id DESC LIMIT 1');
            $s->execute([$user['id']]); $decision=$s->fetch(\PDO::FETCH_ASSOC);
            if ($decision===false || (int)$decision['id']!==(int)$event[3] || $decision['decision']!==$event[2]
                || $user['verification_status']!==$event[2]) { return [null,'superseded']; }
        } else {
            if ($n['related_type']!=='blood_request') { return [null,'invalid_event']; }
            $s=$pdo->prepare('SELECT * FROM blood_requests WHERE id=?'); $s->execute([$n['related_id']]); $request=$s->fetch(\PDO::FETCH_ASSOC);
            if ($request===false || $request['status']!=='OPEN' || $request['urgency']!=='emergency') { return [null,'request_closed']; }
            $s=$pdo->prepare('SELECT generation,status FROM matches WHERE request_id=? AND donor_id=?'); $s->execute([$request['id'],$user['id']]); $match=$s->fetch(\PDO::FETCH_ASSOC);
            if ($match===false || (int)$match['generation']!==(int)$n['generation']) { return [null,'stale_generation']; }
            if (!in_array($match['status'],['POTENTIAL','NOTIFIED'],true)) { return [null,'offer_resolved']; }
            $eligible=DonorEligibilityService::evaluateForRequest($user,$request,substr($now,0,19));
            if (!$eligible['eligible']) { return [null,'donor_ineligible']; }
        }
        return [['user'=>$user,'mail_class'=>$class],null];
    }
}
