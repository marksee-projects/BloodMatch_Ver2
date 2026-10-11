<?php
declare(strict_types=1);
namespace BloodMatch\Services;

/** Pure migration preview/preservation rules; no connection or mutation. */
final class StaffRoleMigration
{
    public const NAME = '024_consolidate_staff_role.sql';
    public const PERMISSIONS = [
        'Global member-management list and role/chapter/activation actions; existing self-change bans remain.',
        'Membership review and member-document access across chapters; no self-verification, no public documents.',
        'Donation-report review across chapters; no self-confirmation/rejection, invalid/closed offers cannot be confirmed.',
        'Request/match inspection, cancellation and rematching across chapters; editing remains owner-only.',
        'Global dashboard, demand map, analytics, aggregate-only CSV/print reports and redacted audit logs.',
        'Staff remain read-only for donor responses, withdrawals, enrollment and donation reporting.',
    ];

    public static function preview(array $rows): array
    {
        $affected = [];
        foreach ($rows as $row) {
            if (!in_array($row['role'],['member','officer','admin'],true)) { throw new \LogicException('Unexpected stored role; review account integrity before conversion.'); }
            if ($row['role'] !== 'officer') { continue; }
            $affected[] = array_intersect_key($row, array_flip([
                'id','full_name','email','chapter_id','account_status','verification_status',
            ])) + ['from_role'=>'officer','to_role'=>'admin','ui_label'=>'Admin/Officer'];
        }
        return ['affected_accounts'=>$affected,'affected_count'=>count($affected),
            'newly_granted_permissions'=>self::PERMISSIONS,
            'deactivated_accounts'=>'Stay deactivated; global staff access becomes available only if later reactivated.',
            'preserved'=>'All non-role user fields, chapter associations, documents, requests, offers, reports, notification/job states and email attempts.',
            'review_sha256'=>hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
    }

    public static function assertPreserved(array $before, array $after): void
    {
        $expected = array_map(static function (array $row): array {
            if ($row['role'] === 'officer') { $row['role'] = 'admin'; }
            return $row;
        }, $before);
        if ($expected !== $after) { throw new \LogicException('User data differs beyond the approved role conversion. Retain backups and investigate.'); }
    }

    public static function readiness(string $shape, bool $recorded, int $officers): array
    {
        $canonical = $shape === "enum('member','admin')";
        $ready = $canonical && $recorded && $officers === 0;
        $inconsistent = ($recorded && !$ready) || ($canonical && $officers !== 0);
        return ['ready'=>$ready, 'status'=>$ready?'ready':($inconsistent?'inconsistent':'review_required'),
            'remaining_officers'=>$officers];
    }
}
