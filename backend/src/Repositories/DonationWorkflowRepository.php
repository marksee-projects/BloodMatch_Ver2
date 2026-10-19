<?php
declare(strict_types=1);
namespace BloodMatch\Repositories;

use BloodMatch\Config\Database;
use BloodMatch\Services\AuditLogger;
use RuntimeException;

final class DonationWorkflowRepository
{
    public static function ready(): bool
    {
        static $ready=null;
        if ($ready===null) {
            $s=Database::pdo()->prepare('SELECT 1 FROM schema_migrations WHERE name=?');
            $s->execute(['030_donation_completion_workflow.sql']);
            $ready=$s->fetchColumn()!==false;
        }
        return $ready;
    }

    public static function requireReady(): void
    {
        if (!self::ready()) throw new RuntimeException('Donation completion requires the reviewed migration 030.',503);
    }

    public static function hasHold(int $donorId): bool
    {
        if (!self::ready()) return false;
        $s=Database::pdo()->prepare('SELECT 1 FROM donation_report_holds WHERE donor_id=? AND released_at IS NULL LIMIT 1');
        $s->execute([$donorId]);return $s->fetchColumn()!==false;
    }

    public static function event(array $row, ?int $actorId, string $type, ?string $basis, string $now): int
    {
        $snapshot=array_intersect_key($row,array_flip(['occurred_at','report_note','content_version','workflow_state','requester_response','acknowledged_at','acknowledged_version','status','completion_basis','rejection_reason','reported_at','reviewed_at','confirmed_at']));
        $s=Database::pdo()->prepare('INSERT INTO donation_report_events(report_id,content_version,actor_id,actor_reference,event_type,basis,snapshot_json,created_at) VALUES(?,?,?,?,?,?,?,?)');
        $s->execute([$row['id'],$row['content_version'],$actorId,$actorId,$type,$basis,json_encode($snapshot,JSON_THROW_ON_ERROR),$now]);
        $eventId=(int)Database::pdo()->lastInsertId();
        // Public/general staff audit metadata contains no private notes or reasons.
        if (AuditLogger::log($actorId,'donation.'.$type,'donation_report',(string)$row['id'],['content_version'=>(int)$row['content_version']])===null) {
            throw new RuntimeException('The report event could not be recorded. Please retry.',503);
        }
        return $eventId;
    }

    public static function listFor(array $actor,bool $adminQueue=false): array
    {
        self::requireReady();
        if ($adminQueue && $actor['role']!=='admin') throw new RuntimeException('Forbidden.',403);
        $admin=$adminQueue;
        $where=$admin ? "(dr.status='PENDING' OR EXISTS (SELECT 1 FROM donation_report_holds h WHERE h.report_id=dr.id AND h.released_at IS NULL))"
            : '(dr.donor_id=? OR br.requester_id=?)';
        $s=Database::pdo()->prepare("SELECT dr.*,m.request_id,br.requester_id,br.status AS request_status,br.facility_name,
            donor.full_name AS donor_name,requester.full_name AS requester_name,
            (SELECT h.release_basis FROM donation_report_holds h WHERE h.report_id=dr.id) AS hold_release_basis,
            TIMESTAMPDIFF(HOUR,dr.reported_at,UTC_TIMESTAMP()) AS age_hours,
            EXISTS (SELECT 1 FROM donation_report_holds h WHERE h.report_id=dr.id AND h.released_at IS NULL) AS has_report_hold
            FROM donation_reports dr JOIN matches m ON m.id=dr.match_id JOIN blood_requests br ON br.id=m.request_id
            JOIN users donor ON donor.id=dr.donor_id JOIN users requester ON requester.id=br.requester_id
            WHERE $where ORDER BY dr.reported_at ASC");
        $s->execute($admin?[]:[$actor['id'],$actor['id']]);
        return $s->fetchAll();
    }
}
