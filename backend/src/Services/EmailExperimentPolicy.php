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
}
