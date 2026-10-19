<?php
declare(strict_types=1);
namespace BloodMatch\Services;

use BloodMatch\Config\Database;
use BloodMatch\Repositories\DonationWorkflowRepository;

final class DonationFollowupService
{
    public static function candidates(string $now,int $limit=200,?array $onlyIds=null): array
    {
        DonationWorkflowRepository::requireReady();
        $limit=max(1,min($limit,500));
        if ($onlyIds===[]) return [];
        $scope='';$params=[$now,$now];
        if ($onlyIds!==null) {
            $onlyIds=array_values(array_unique(array_map('intval',$onlyIds)));
            if (count($onlyIds)>500 || array_filter($onlyIds,fn($id)=>$id<1)) throw new \InvalidArgumentException('Invalid bounded report scope.');
            $scope=' AND id IN ('.implode(',',array_fill(0,count($onlyIds),'?')).')';$params=array_merge($params,$onlyIds);
        }
        $s=Database::pdo()->prepare("SELECT id FROM donation_reports WHERE status='PENDING' AND acknowledged_at IS NULL
            AND ((reminder_sent_at IS NULL AND reported_at<=DATE_SUB(?,INTERVAL 24 HOUR))
            OR (escalated_at IS NULL AND reported_at<=DATE_SUB(?,INTERVAL 48 HOUR)))$scope ORDER BY reported_at,id LIMIT $limit");
        $s->execute($params);return array_map('intval',$s->fetchAll(\PDO::FETCH_COLUMN));
    }

    public static function process(string $now,int $limit=200,?array $onlyIds=null): array
    {
        $counts=['reminded'=>0,'escalated'=>0];
        foreach(self::candidates($now,$limit,$onlyIds) as $id) {
            $changes=DonationCompletionService::locked($id,null,function($row,$request) use($now) {
                $changes=['reminded'=>0,'escalated'=>0];
                foreach(DonationCompletionPolicy::dueActions($row,$now) as $action) {
                    if ($action==='remind') {
                        Database::pdo()->prepare('UPDATE donation_reports SET reminder_sent_at=? WHERE id=?')->execute([$now,$row['id']]);
                        DonationCompletionService::notify($row,(int)$request['requester_id'],'reminder','Donation response reminder','A donor is waiting for your response. Silence will not confirm or reject this report.','reminder');
                        DonationWorkflowRepository::event($row,null,'requester_reminded',null,$now);$changes['reminded']++;
                    } else {
                        Database::pdo()->prepare("UPDATE donation_reports SET escalated_at=?,workflow_state=CASE WHEN workflow_state='clarification' THEN workflow_state ELSE 'needs_review' END WHERE id=?")->execute([$now,$row['id']]);
                        DonationCompletionService::notifyAdmins($row,$request,'escalated','Donation report awaiting review for 48 hours','escalation');
                        foreach(array_unique([(int)$row['donor_id'],(int)$request['requester_id']]) as $recipient) DonationCompletionService::notify($row,$recipient,'escalated','Donation report sent for review','No requester acknowledgment was recorded within 48 hours. The report remains unresolved.','escalation');
                        if ($row['workflow_state']!=='clarification') $row['workflow_state']='needs_review';
                        DonationWorkflowRepository::event($row,null,'escalated',null,$now);$changes['escalated']++;
                    }
                }
                return $changes;
            });
            foreach($counts as $key=>$_) $counts[$key]+=$changes[$key];
        }
        return $counts;
    }
}
