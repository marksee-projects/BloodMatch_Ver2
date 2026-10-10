<?php
declare(strict_types=1);
namespace BloodMatch\Services;
final class EmailExperimentPolicy
{
    public static function assertMembershipScope(bool $queueEnabled, bool $scheduled, int $requestedId, bool $recipientMatches, array $review): void
    {
        if ($queueEnabled || $scheduled || !$recipientMatches || $requestedId<1 || ($review['job_id']??null)!==$requestedId
            || ($review['notification_type']??null)!=='verification.decision' || ($review['priority']??null)!=='normal'
            || ($review['mail_class']??null)!=='account' || !($review['claimable_now']??false) || ($review['policy_block']??null)!==null) {
            throw new \LogicException('Experiment requires general queue disabled and one current membership-decision job for the reviewed recipient.');
        }
    }

    public static function assertEmergencyGroupingScope(bool $queueEnabled, bool $scheduled, array $ids, bool $recipientMatches, array $reviews, string $now): void
    {
        $invalid=$queueEnabled || $scheduled || !$recipientMatches || count($ids)!==2 || count(array_unique($ids))!==2 || min($ids)<1 || count($reviews)!==2;
        $expected=$ids;$actual=array_column($reviews,'job_id');sort($expected);sort($actual);
        $invalid=$invalid || $actual!==$expected || count(array_unique(array_column($reviews,'request_id')))!==2
            || count(array_unique(array_column($reviews,'requester_id')))!==2 || count(array_unique(array_column($reviews,'recipient_user_id')))!==1;
        foreach ($reviews as $review) {
            $invalid=$invalid || ($review['notification_type']??null)!=='match.new' || ($review['priority']??null)!=='emergency'
                || ($review['mail_class']??null)!=='emergency' || ($review['status']??null)!=='pending' || ($review['attempts']??-1)!==0
                || !($review['claimable_now']??false) || ($review['policy_block']??null)!==null
                || (int)($review['requester_id']??0)<1 || (int)($review['request_id']??0)<1 || (int)($review['recipient_user_id']??0)<1;
        }
        if ($invalid) { throw new \LogicException('Emergency experiment needs two fresh current jobs from distinct requesters for the same reviewed donor; general queue disabled.'); }
        usort($reviews,static fn($a,$b)=>strcmp($a['next_attempt_at'],$b['next_attempt_at'])?:($a['job_id']<=>$b['job_id']));
        $first=$reviews[0];$ready=EmailWorkerPolicy::digestReadyAt($first['created_at']);
        if ($now<$ready) { throw new \LogicException('Five-minute grouping window is not ready.'); }
        foreach ($reviews as $review) {
            if ($review['created_at']<$first['created_at'] || $review['created_at']>=$ready) { throw new \LogicException('Selected jobs are outside one five-minute grouping window.'); }
        }
    }
}
