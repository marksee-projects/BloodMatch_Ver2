<?php
declare(strict_types=1);
namespace BloodMatch\Services;
use BloodMatch\Config\Database;
use RuntimeException;
final class RetentionService
{
    public static function run(bool $apply=false): array
    {
        if (PHP_SAPI!=='cli') throw new RuntimeException('CLI only.');
        $p=Database::pdo();$summary=[];
        $pending=$p->query('SELECT id,document_json FROM pending_registrations WHERE expires_at<=UTC_TIMESTAMP()')->fetchAll();
        $summary['expired_registrations']=count($pending);
        if ($apply) foreach($pending as $r) {
            if ($r['document_json']) PendingRegistrationService::removeDocument(json_decode($r['document_json'],true,512,JSON_THROW_ON_ERROR));
            $s=$p->prepare('DELETE FROM pending_registrations WHERE id=? AND expires_at<=UTC_TIMESTAMP()');$s->execute([$r['id']]);
        }
        $closures=$p->query('SELECT c.user_id,u.profile_picture FROM account_closures c JOIN users u ON u.id=c.user_id WHERE c.erase_after<=UTC_TIMESTAMP() AND c.profile_erased_at IS NULL')->fetchAll();
        $summary['due_account_erasures']=count($closures);
        if ($apply) foreach($closures as $r) WorkflowLockService::transaction(function() use($p,$r){
            $id=(int)$r['user_id'];$locked=WorkflowLockService::users([$id])[$id];
            if ($locked['role']==='admin' || $locked['account_status']!=='deactivated') throw new RuntimeException('Unsafe account erasure state.');
            $s=$p->prepare('SELECT stored_name FROM member_documents WHERE user_id=?');$s->execute([$id]);
            foreach($s->fetchAll() as $doc) PendingRegistrationService::removeDocument($doc);
            if ($r['profile_picture']) {
                $path=ProfilePictureStorageService::pathFor((string)$r['profile_picture']);
                if (is_link($path)) throw new RuntimeException('Unsafe profile picture path.');
                if (is_file($path) && !unlink($path)) throw new RuntimeException('Could not remove profile picture.');
            }
            $s=$p->prepare('DELETE FROM member_documents WHERE user_id=?');$s->execute([$id]);
            // Remove whole normal threads involving the closed account to avoid retaining
            // identifying free text in another participant's copy. Scoped report evidence
            // is an independent restricted snapshot and survives until case retention ends.
            $s=$p->prepare('DELETE c FROM conversations c JOIN conversation_participants cp ON cp.conversation_id=c.id WHERE cp.user_id=?');$s->execute([$id]);
            $s=$p->prepare('DELETE FROM chat_requests WHERE user_low=? OR user_high=?');$s->execute([$id,$id]);
            $s=$p->prepare("UPDATE notifications n SET n.title=REPLACE(n.title,?,'Closed account'),n.body=REPLACE(REPLACE(n.body,?,'Closed account'),?,'[removed]') WHERE n.related_type='blood_request' AND (n.related_id IN (SELECT id FROM blood_requests WHERE requester_id=?) OR n.related_id IN (SELECT request_id FROM matches WHERE donor_id=?))");$s->execute([$locked['full_name'],$locked['full_name'],$locked['email'],$id,$id]);
            $s=$p->prepare("UPDATE notifications n SET title='Account activity',body='Activity involving a permanently closed account.' WHERE (n.related_type='blood_request' AND n.related_id IN (SELECT id FROM blood_requests WHERE requester_id=?)) OR (n.related_type='donation_report' AND n.related_id IN (SELECT id FROM donation_reports WHERE donor_id=?))");$s->execute([$id,$id]);
            $keys=['login:','verify:acct:','resend:acct:','resend:cd:','registration:','reset:'];
            $s=$p->prepare('DELETE FROM auth_throttle WHERE identifier=?');foreach($keys as $key) $s->execute([$key.$locked['email']]);
            $s=$p->prepare('DELETE FROM notifications WHERE user_id=?');$s->execute([$id]);
            $s=$p->prepare("UPDATE users SET email=?,first_name='Deleted',middle_name=NULL,last_name='account',phone=NULL,profile_picture=NULL,date_of_birth=NULL,chapter_id=NULL,location_id=NULL,latitude=NULL,longitude=NULL,email_verified_at=NULL WHERE id=?");$s->execute(['closed-'.bin2hex(random_bytes(16)).'@deleted.invalid',$id]);
            $s=$p->prepare('UPDATE account_closures SET profile_erased_at=UTC_TIMESTAMP() WHERE user_id=?');$s->execute([$id]);
        });
        // Final decisions only. Resubmission/re-review resets the reference point.
        $docs=$p->query("SELECT d.id,d.stored_name FROM member_documents d JOIN users u ON u.id=d.user_id WHERE d.doc_type='national_id' AND u.verification_status IN ('verified','rejected') AND d.uploaded_at <= (SELECT MAX(v.created_at) FROM verification_decisions v WHERE v.target_user_id=u.id) AND (SELECT MAX(v.created_at) FROM verification_decisions v WHERE v.target_user_id=u.id)<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 30 DAY)")->fetchAll();
        $summary['due_identity_documents']=count($docs);
        $summary['identity_documents_without_decision_date']=(int)$p->query("SELECT COUNT(*) FROM member_documents d JOIN users u ON u.id=d.user_id WHERE d.doc_type='national_id' AND u.verification_status IN ('verified','rejected') AND NOT EXISTS(SELECT 1 FROM verification_decisions v WHERE v.target_user_id=u.id)")->fetchColumn();
        if ($apply) foreach($docs as $d) {PendingRegistrationService::removeDocument($d);$s=$p->prepare('DELETE FROM member_documents WHERE id=?');$s->execute([$d['id']]);}
        $settings=SystemSettingsService::get();
        $donorDays=365+max((int)$settings['cooldown_days'],(int)ceil($settings['standby_hours']/24));
        $queries=[
            'old_throttle_records'=>"DELETE FROM auth_throttle WHERE updated_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)",
            'old_password_resets'=>"DELETE FROM password_resets WHERE expires_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 DAY)",
            'stale_chat_requests'=>"DELETE FROM chat_requests WHERE status<>'accepted' AND updated_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR)",
            'closed_chat_reports'=>"DELETE FROM chat_reports WHERE status IN ('resolved','dismissed') AND resolved_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR)",
            'stale_conversations'=>"DELETE FROM conversations WHERE updated_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR)",
            'resolved_requests'=>"DELETE br FROM blood_requests br WHERE br.status<>'OPEN' AND br.updated_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR) AND NOT EXISTS(SELECT 1 FROM matches m JOIN donation_reports d ON d.match_id=m.id WHERE m.request_id=br.id AND d.status='PENDING') AND NOT EXISTS(SELECT 1 FROM matches m JOIN donation_reports d ON d.match_id=m.id WHERE m.request_id=br.id AND COALESCE(d.confirmed_at,d.reported_at)>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR)) AND NOT EXISTS(SELECT 1 FROM matches m JOIN users u ON u.id=m.donor_id WHERE m.request_id=br.id AND u.last_verified_donation_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL $donorDays DAY))",
            'aged_audit'=>"DELETE a FROM audit_log a WHERE a.created_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR) AND NOT EXISTS(SELECT 1 FROM users u LEFT JOIN account_closures c ON c.user_id=u.id WHERE a.action='user.registered' AND a.actor_id=u.id AND (u.account_status='active' OR c.closed_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR))) AND NOT EXISTS(SELECT 1 FROM chat_reports r WHERE a.target_type='chat_report' AND r.id=a.target_id AND (r.status IN ('open','under_review') OR r.resolved_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR))) AND NOT EXISTS(SELECT 1 FROM donation_reports d WHERE a.target_type='donation_report' AND d.id=a.target_id AND (d.status='PENDING' OR COALESCE(d.confirmed_at,d.reported_at)>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR))) AND NOT EXISTS(SELECT 1 FROM blood_requests br WHERE a.target_type='blood_request' AND br.id=a.target_id AND (br.status='OPEN' OR br.updated_at>DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR)))",
        ];
        if (\BloodMatch\Repositories\DonationWorkflowRepository::ready()) {
            $queries['resolved_requests'].=' AND NOT EXISTS(SELECT 1 FROM matches hm JOIN donation_report_holds hh ON hh.donor_id=hm.donor_id WHERE hm.request_id=br.id AND hh.released_at IS NULL)';
            $queries['aged_audit'].=" AND NOT EXISTS(SELECT 1 FROM matches hm JOIN donation_report_holds hh ON hh.report_id IN (SELECT hd.id FROM donation_reports hd WHERE hd.match_id=hm.id) WHERE a.target_type='blood_request' AND hm.request_id=a.target_id AND hh.released_at IS NULL)";
            $queries['aged_audit'].=" AND NOT EXISTS(SELECT 1 FROM donation_report_holds hh WHERE a.target_type='donation_report' AND hh.report_id=a.target_id AND hh.released_at IS NULL)";
        }
        foreach($queries as $name=>$sql) {
            $countSql=preg_replace('/^DELETE(?:\s+\w+)?\s+FROM/i','SELECT COUNT(*) FROM',$sql);
            $summary[$name.'_due']=(int)$p->query($countSql)->fetchColumn();
        }
        if ($apply) {
            $p->exec('SET @bloodmatch_retention=1');
            try { foreach($queries as $name=>$sql) $summary[$name]=$p->exec($sql); }
            finally { $p->exec('SET @bloodmatch_retention=0'); }
            // Pending consent expires by created_at in the request API. Do not
            // turn expiry into a cancellation here and restart its retry delay.
        }
        if ($apply) {
            $p->exec("UPDATE chat_requests r LEFT JOIN conversations c ON c.id=r.conversation_id SET r.status='cancelled',r.conversation_id=NULL,r.updated_at=UTC_TIMESTAMP() WHERE r.status='accepted' AND c.id IS NULL");
            $holdGuard=\BloodMatch\Repositories\DonationWorkflowRepository::ready()?' AND NOT EXISTS(SELECT 1 FROM donation_report_holds hh WHERE hh.donor_id=u.id AND hh.released_at IS NULL)':'';
            $s=$p->prepare("UPDATE users u JOIN account_closures c ON c.user_id=u.id SET u.blood_type=NULL,u.blood_type_source=NULL,u.blood_type_verified=0,u.last_verified_donation_at=NULL,u.donor_availability=NULL,u.donor_enrolled_at=NULL WHERE c.profile_erased_at IS NOT NULL AND c.closed_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 YEAR) AND (u.last_verified_donation_at IS NULL OR u.last_verified_donation_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL $donorDays DAY)) AND NOT EXISTS(SELECT 1 FROM donation_reports d WHERE d.donor_id=u.id AND d.status='PENDING')$holdGuard");$s->execute();
            $targetDb=(string)$p->query('SELECT DATABASE()')->fetchColumn();
            $dir=dirname(__DIR__,2).'/storage/maintenance';
            foreach(glob($dir.'/*.sql')?:[] as $path) {
                if (!str_starts_with(basename($path),$targetDb.'-') || is_link($path) || !preg_match('/^bloodmatch(?:_test)?-(\d{8})-(\d{6})-[a-f0-9]+\.sql$/',basename($path),$m)) continue;
                $date=\DateTimeImmutable::createFromFormat('!YmdHis',$m[1].$m[2],new \DateTimeZone('UTC'));
                if ($date && $date->getTimestamp()<time()-90*86400 && !unlink($path)) throw new RuntimeException('Backup aging failed.');
            }
        }
        $summary['mode']=$apply?'applied':'review';return $summary;
    }
}
