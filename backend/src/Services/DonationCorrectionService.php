<?php
declare(strict_types=1);
namespace BloodMatch\Services;
use BloodMatch\Config\Database;
use BloodMatch\Repositories\DonationWorkflowRepository;
use RuntimeException;
final class DonationCorrectionService {
    public static function request(array $actor,int $id,int $version,string $reason): array {
        OfferChatService::requireReady();$reason=DonationCompletionPolicy::basis($reason);
        return DonationCompletionService::locked($id,(int)$actor['id'],function($row,$r,$m,$a) use($version,$reason) {
            if (!in_array((int)$a['id'],[(int)$row['donor_id'],(int)$r['requester_id']],true)) throw new RuntimeException('Only report participants can request correction.',403);
            DonationCompletionPolicy::assertVersion($row,$version);
            if ($row['status']!=='CONFIRMED') throw new RuntimeException('Use the pending report review process for this report.',409);
            $p=Database::pdo();$s=$p->prepare('SELECT id FROM donation_correction_requests WHERE report_id=? AND content_version=? AND actor_reference=?');$s->execute([$row['id'],$version,$a['id']]);if($s->fetchColumn()) return ['changed'=>false];
            $p->prepare('INSERT INTO donation_correction_requests(report_id,content_version,actor_id,actor_reference,reason,created_at) VALUES(?,?,?,?,?,?)')->execute([$row['id'],$version,$a['id'],$a['id'],$reason,AuthService::nowUtc()]);
            DonationWorkflowRepository::event($row,(int)$a['id'],'completed_correction_requested',$reason,AuthService::nowUtc());
            DonationCompletionService::notifyAdmins($row,$r,'review_needed','Completed donation correction requested','correction:'.$version.':'.$a['id']);
            return ['changed'=>true];
        });
    }
    public static function listing(array $actor,int $id): array {
        OfferChatService::requireReady();return DonationCompletionService::locked($id,(int)$actor['id'],function($row,$r,$m,$a) {
            DonationProofService::assertAccess($a,$row,$r);$s=Database::pdo()->prepare('SELECT id,actor_reference,reason,created_at,resolved_at,resolution FROM donation_correction_requests WHERE report_id=? ORDER BY id');$s->execute([$row['id']]);return $s->fetchAll();
        });
    }
    public static function resolve(array $actor,int $id,int $correctionId,int $version,string $basis): array {
        OfferChatService::requireReady();$basis=DonationCompletionPolicy::basis($basis);
        return DonationCompletionService::locked($id,(int)$actor['id'],function($row,$r,$m,$a) use($correctionId,$version,$basis) {
            DonationProofService::assertAccess($a,$row,$r,true);DonationCompletionPolicy::assertVersion($row,$version);
            $p=Database::pdo();$s=$p->prepare('SELECT * FROM donation_correction_requests WHERE id=? AND report_id=? FOR UPDATE');$s->execute([$correctionId,$row['id']]);$q=$s->fetch();if(!$q) throw new RuntimeException('Correction request not found.',404);if($q['resolved_at']) return ['changed'=>false];
            $p->prepare('UPDATE donation_correction_requests SET resolved_at=?,resolved_by=?,resolution=? WHERE id=?')->execute([AuthService::nowUtc(),$a['id'],$basis,$q['id']]);
            DonationWorkflowRepository::event($row,(int)$a['id'],'completed_correction_response',$basis,AuthService::nowUtc());
            foreach(array_unique([(int)$row['donor_id'],(int)$r['requester_id']]) as $recipient) DonationCompletionService::notify($row,$recipient,'correction_response','Staff responded to your correction request','Open the private report history. Donation credit, fulfillment and restrictions have not been reversed.','correction-response:'.$q['id']);
            return ['changed'=>true,'donation_changed'=>false];
        });
    }
}
