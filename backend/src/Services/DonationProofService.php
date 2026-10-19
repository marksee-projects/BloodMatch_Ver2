<?php
declare(strict_types=1);
namespace BloodMatch\Services;
use BloodMatch\Config\Database;
use BloodMatch\Repositories\DonationWorkflowRepository;
use RuntimeException;
final class DonationProofService
{
    public const MAX_FILES=3;
    public const MAX_BYTES=5242880;
    public const MAX_PIXELS=20000000;
    public static function uploads(array $actor): array {
        if (!empty($_FILES['proofs'])) {
            if (!(new \BloodMatch\Repositories\AuthThrottleRepository())->hitAndCheckRateLimit('mutation:donation-proof:'.$actor['id'],10,5,AuthService::nowUtc())) throw new RuntimeException('Too many proof uploads. Please wait a few minutes.',429);
        }
        if (str_contains((string)($_SERVER['CONTENT_TYPE']??''),'multipart/form-data') && (int)($_SERVER['CONTENT_LENGTH']??0)>0 && !$_POST && !$_FILES) throw new RuntimeException('Upload exceeds the server request limit. Ask staff to configure the approved photo limits or submit without photos.',413);
        return self::prepare($_FILES['proofs']??[]);
    }
    public static function prepare(array $files): array {
        if (!$files || !isset($files['tmp_name'])) return [];
        if (!is_array($files['tmp_name']) || count($files['tmp_name'])>self::MAX_FILES) throw new RuntimeException('Choose up to three proof photos.',400);
        $stored=[];
        try {
            foreach($files['tmp_name'] as $i=>$path) {
                $error=$files['error'][$i]??UPLOAD_ERR_NO_FILE;if ($error===UPLOAD_ERR_NO_FILE) continue;
                if ($error!==UPLOAD_ERR_OK || !is_uploaded_file($path)) throw new RuntimeException('Proof upload failed. Choose the photo again.',400);
                if (filesize($path)>self::MAX_BYTES) throw new RuntimeException('Each proof photo must be at most 5 MB.',400);
                if (DocumentCipher::mode()==='legacy') throw new RuntimeException('Encrypted proof storage is not configured. Submit without photos or ask staff to configure protected storage.',503);
                if (!extension_loaded('gd')) throw new RuntimeException('Photo processing is unavailable. Submit without photos or ask staff to enable GD.',503);
                $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
                $size=@getimagesize($path);
                if (!in_array($mime,['image/jpeg','image/png','image/webp'],true) || !$size || $size[0]<1 || $size[1]<1 || $size[0]*$size[1]>self::MAX_PIXELS) throw new RuntimeException('Use a valid JPG, PNG or WEBP photo, no larger than 20 megapixels.',400);
                $image=@imagecreatefromstring((string)file_get_contents($path));if (!$image) throw new RuntimeException('This photo could not be decoded.',400);
                $tmp=tempnam(sys_get_temp_dir(),'bm-proof-');
                if ($tmp===false) { imagedestroy($image);throw new RuntimeException('Photo processing unavailable.',503); }
                try {
                    // Re-encode raster pixels only; EXIF/location metadata is discarded.
                    $ok=$mime==='image/png'?imagepng($image,$tmp):imagejpeg($image,$tmp,90);
                    if (!$ok) throw new RuntimeException('Photo processing failed.',400);
                    $stored[]=DocumentStorageService::store($tmp);
                } finally { imagedestroy($image);if(is_file($tmp)) unlink($tmp); }
            }
            return $stored;
        } catch(\Throwable $e) { self::discard($stored);throw $e; }
    }
    public static function discard(array $files): void {
        foreach($files as $f) {
            try {
                if (OfferChatService::ready()) {
                    $s=Database::pdo()->prepare('SELECT 1 FROM donation_report_evidence WHERE stored_name=?');$s->execute([$f['stored_name']]);
                    if ($s->fetchColumn()) continue; // Never delete a committed file after an ambiguous commit/reply failure.
                }
            } catch(\Throwable) { error_log('[donation-proof] cleanup deferred until file ownership can be checked');continue; }
            $path=DocumentStorageService::pathFor($f['stored_name']);if(is_file($path) && !is_link($path) && !unlink($path)) error_log('[donation-proof] uncommitted file cleanup failed');
        }
    }
    public static function attach(int $reportId,int $version,int $uploader,array $files): void {
        if (!$files) return;
        OfferChatService::requireReady();
        if (count($files)>self::MAX_FILES) throw new RuntimeException('Choose up to three proof photos.',400);
        $p=Database::pdo();$p->prepare('UPDATE donation_report_evidence SET retired_version=? WHERE report_id=? AND retired_version IS NULL')->execute([$version,$reportId]);
        $s=$p->prepare('INSERT INTO donation_report_evidence(report_id,content_version,uploader_id,stored_name,mime_type,original_ext,size_bytes,uploaded_at) VALUES(?,?,?,?,?,?,?,?)');
        foreach($files as $f) $s->execute([$reportId,$version,$uploader,$f['stored_name'],$f['mime_type'],$f['original_ext'],$f['size_bytes'],AuthService::nowUtc()]);
    }
    public static function manifest(int $reportId,int $version): array {
        if (!OfferChatService::ready()) return [];
        $s=Database::pdo()->prepare('SELECT id FROM donation_report_evidence WHERE report_id=? AND content_version<=? AND (retired_version IS NULL OR retired_version>?) ORDER BY id');$s->execute([$reportId,$version,$version]);return array_map('intval',$s->fetchAll(\PDO::FETCH_COLUMN));
    }
    public static function assertAccess(array $actor,array $row,array $request,bool $reviewer=false): void {
        WorkflowLockService::assertActiveActor($actor);
        if (!$reviewer && in_array((int)$actor['id'],[(int)$row['donor_id'],(int)$request['requester_id']],true)) return;
        WorkflowLockService::assertReportReviewer($actor,$request,(int)$row['donor_id']);
        if (strtolower((string)($actor['email']??''))!=='markseejr@gmail.com') throw new RuntimeException('Evidence not found.',404);
    }
    public static function listing(array $actor,int $id): array {
        OfferChatService::requireReady();
        return DonationCompletionService::locked($id,(int)$actor['id'],function($row,$r,$m,$a) {
            self::assertAccess($a,$row,$r);
            $s=Database::pdo()->prepare('SELECT id,content_version,retired_version,mime_type,size_bytes,uploaded_at FROM donation_report_evidence WHERE report_id=? ORDER BY content_version,id');$s->execute([$row['id']]);return $s->fetchAll();
        });
    }
    public static function add(array $actor,int $id,int $version,array $files): array {
        OfferChatService::requireReady();if (!$files) throw new RuntimeException('Choose at least one proof photo.',400);
        return DonationCompletionService::locked($id,(int)$actor['id'],function($row,$r,$m,$a) use($version,$files) {
            if (!in_array((int)$a['id'],[(int)$row['donor_id'],(int)$r['requester_id']],true)) throw new RuntimeException('Only report participants can submit proof.',403);
            DonationCompletionPolicy::assertVersion($row,$version);DonationCompletionPolicy::assertUnresolved($row);
            DonationWorkflowRepository::event($row,(int)$a['id'],'version_superseded','Supporting photos updated.',AuthService::nowUtc());
            $new=$version+1;$p=Database::pdo();
            // A proof update must not erase an already recorded dispute/uncertainty.
            // Retain its exact old acknowledged_version until staff clarification.
            $p->prepare('UPDATE donation_reports SET content_version=? WHERE id=?')->execute([$new,$row['id']]);
            self::attach((int)$row['id'],$new,(int)$a['id'],$files);$row=array_replace($row,['content_version'=>$new]);
            DonationWorkflowRepository::event($row,(int)$a['id'],'proof_updated',null,AuthService::nowUtc());
            foreach(array_unique([(int)$row['donor_id'],(int)$r['requester_id']]) as $recipient) DonationCompletionService::notify($row,$recipient,'proof_updated','Donation proof updated','Review the current report version before responding. Photos do not confirm donation.','proof:'.$new);
            return ['changed'=>true,'content_version'=>$new];
        });
    }
    public static function file(array $actor,int $evidenceId): array {
        OfferChatService::requireReady();$s=Database::pdo()->prepare('SELECT * FROM donation_report_evidence WHERE id=?');$s->execute([$evidenceId]);$file=$s->fetch();if (!$file) throw new RuntimeException('Evidence not found.',404);
        return DonationCompletionService::locked((int)$file['report_id'],(int)$actor['id'],function($row,$r,$m,$a) use($file) {
            self::assertAccess($a,$row,$r);
            if (AuditLogger::log((int)$a['id'],'donation.proof_accessed','donation_report',(string)$row['id'],['evidence_id'=>(int)$file['id']])===null) throw new RuntimeException('Evidence access audit unavailable.',503);
            return ['bytes'=>DocumentStorageService::read($file),'mime_type'=>$file['mime_type']];
        });
    }
    public static function retention(bool $apply,int $donorDays): array {
        if (!OfferChatService::ready()) return ['due_donation_proof'=>0,'due_donation_corrections'=>0];
        $p=Database::pdo();$guard=" FROM donation_reports d JOIN users u ON u.id=d.donor_id LEFT JOIN donation_report_holds h ON h.report_id=d.id
            WHERE d.status<>'PENDING' AND (h.report_id IS NULL OR h.released_at IS NOT NULL)
            AND GREATEST(COALESCE(d.reviewed_at,d.reported_at),COALESCE(d.confirmed_at,d.reported_at),COALESCE(h.released_at,d.reported_at))<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR)
            AND (u.last_verified_donation_at IS NULL OR u.last_verified_donation_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL ".(int)$donorDays." DAY))
            AND NOT EXISTS(SELECT 1 FROM donation_correction_requests c WHERE c.report_id=d.id AND (c.resolved_at IS NULL OR c.resolved_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR)))";
        $reports=$p->query('SELECT d.id'.$guard)->fetchAll(\PDO::FETCH_COLUMN);$counts=['due_donation_proof'=>0,'due_donation_corrections'=>0];
        foreach($reports as $id) {
            $s=$p->prepare('SELECT id,stored_name FROM donation_report_evidence WHERE report_id=?');$s->execute([$id]);$files=$s->fetchAll();$counts['due_donation_proof']+=count($files);
            $s=$p->prepare('SELECT COUNT(*) FROM donation_correction_requests WHERE report_id=?');$s->execute([$id]);$counts['due_donation_corrections']+=(int)$s->fetchColumn();
            if ($apply) WorkflowLockService::transaction(function() use($p,$id,$donorDays) {
                DonationCompletionService::locked((int)$id,null,function($row) use($p,$id,$donorDays) {
                    // Re-evaluate all retention conditions under the report/user locks.
                    $review=$p->prepare("SELECT 1 FROM donation_reports d JOIN users u ON u.id=d.donor_id LEFT JOIN donation_report_holds h ON h.report_id=d.id WHERE d.id=? AND d.status<>'PENDING' AND (h.report_id IS NULL OR h.released_at IS NOT NULL)
                        AND GREATEST(COALESCE(d.reviewed_at,d.reported_at),COALESCE(d.confirmed_at,d.reported_at),COALESCE(h.released_at,d.reported_at))<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR)
                        AND (u.last_verified_donation_at IS NULL OR u.last_verified_donation_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL ".(int)$donorDays." DAY))
                        AND NOT EXISTS(SELECT 1 FROM donation_correction_requests c WHERE c.report_id=d.id AND (c.resolved_at IS NULL OR c.resolved_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR)))");$review->execute([$id]);if(!$review->fetchColumn()) return;
                    $s=$p->prepare('SELECT id,stored_name FROM donation_report_evidence WHERE report_id=? FOR UPDATE');$s->execute([$id]);foreach($s->fetchAll() as $f) { $path=DocumentStorageService::pathFor($f['stored_name']);if(is_link($path)) throw new RuntimeException('Unsafe evidence retention path.');if(is_file($path)&&!unlink($path)) throw new RuntimeException('Evidence retention cleanup failed.');$p->prepare('DELETE FROM donation_report_evidence WHERE id=?')->execute([$f['id']]); }
                    $p->prepare('DELETE FROM donation_correction_requests WHERE report_id=?')->execute([$id]);
                });
            });
        }
        return $counts;
    }
}
