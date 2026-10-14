<?php
declare(strict_types=1);
namespace BloodMatch\Services;
use BloodMatch\Config\Database;
use BloodMatch\Repositories\{UserRepository,DocumentRepository};
use RuntimeException;
final class PendingRegistrationService
{
    public static function stage(array $payload, ?array $file): array
    {
        $p=Database::pdo();
        // Check migration availability before persisting an uploaded file.
        $p->query('SELECT id FROM pending_registrations LIMIT 0');
        $email=$payload['email'];
        $key='registration:'.$email;
        if (!(new \BloodMatch\Repositories\AuthThrottleRepository())->hitAndCheckRateLimitSeconds($key,3,900,AuthService::nowUtc())) throw new RuntimeException('Please wait before registering again.',429);
        $existing=$p->prepare('SELECT id,expires_at,document_json FROM pending_registrations WHERE email=?');$existing->execute([$email]);
        $prior=$existing->fetch();
        if ($prior && $prior['expires_at']<=AuthService::nowUtc()) {
            if ($prior['document_json']) self::removeDocument(json_decode($prior['document_json'],true,512,JSON_THROW_ON_ERROR));
            $expired=$p->prepare('DELETE FROM pending_registrations WHERE id=? AND expires_at<=UTC_TIMESTAMP()');$expired->execute([$prior['id']]);$prior=false;
        }
        if ($prior) return ['email'=>$email,'email_verified'=>false,'pending_registration'=>true,'existing_pending'=>true];
        $stored=null;
        if ($file!==null && ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
            if (($file['error']??-1)!==UPLOAD_ERR_OK) throw new RuntimeException('Choose an ID file up to 5 MB.',400);
            $stored=DocumentStorageService::store((string)$file['tmp_name']);
        }
        $code=sprintf('%06d',random_int(0,999999));
        try {
            $s=$p->prepare('INSERT INTO pending_registrations(email,payload_json,document_json,code_hash,code_expires_at,expires_at) VALUES(?,?,?,?,?,?)');
            $s->execute([$email,json_encode($payload,JSON_THROW_ON_ERROR),$stored?json_encode($stored,JSON_THROW_ON_ERROR):null,password_hash($code,PASSWORD_BCRYPT),gmdate('Y-m-d H:i:s',time()+600),gmdate('Y-m-d H:i:s',time()+86400)]);
        } catch(\Throwable $e) {
            if ($stored) self::removeDocument($stored);
            throw $e;
        }
        self::mail($email,$code);
        return ['email'=>$email,'email_verified'=>false,'pending_registration'=>true];
    }
    public static function verify(string $email,string $code): bool
    {
        return WorkflowLockService::transaction(function() use($email,$code): bool {
            $p=Database::pdo();$s=$p->prepare('SELECT * FROM pending_registrations WHERE email=? FOR UPDATE');$s->execute([$email]);$r=$s->fetch();
            if (!$r) return false;
            if (!preg_match('/^\d{6}$/',$code) || $r['expires_at']<=AuthService::nowUtc() || $r['code_expires_at']<=AuthService::nowUtc() || !password_verify($code,$r['code_hash'])) throw new RuntimeException('Invalid or expired verification code.',400);
            $payload=json_decode($r['payload_json'],true,512,JSON_THROW_ON_ERROR);$payload['email_verified_at']=AuthService::nowUtc();
            $repo=new UserRepository();$id=$repo->create($payload);
            if ($r['document_json']) {
                $doc=json_decode($r['document_json'],true,512,JSON_THROW_ON_ERROR);
                $docId=(new DocumentRepository())->create($id,'national_id',$doc,AuthService::nowUtc());
                NotificationService::notifyVerificationRequested($id,trim($payload['first_name'].' '.$payload['last_name']),$docId);
            }
            if (AuditLogger::log($id,'user.registered','user',(string)$id,['email_verified'=>true,'verification_status'=>'pending','privacy_notice_version'=>$payload['privacy_notice_version']??'unrecorded','privacy_acknowledged_at'=>$payload['privacy_acknowledged_at']??null])===null) throw new RuntimeException('Account creation could not be recorded. Try again.',503);
            $s=$p->prepare('DELETE FROM pending_registrations WHERE id=?');$s->execute([$r['id']]);
            return true;
        });
    }
    public static function resend(string $email): bool
    {
        $code=sprintf('%06d',random_int(0,999999));
        $s=Database::pdo()->prepare('UPDATE pending_registrations SET code_hash=?,code_expires_at=LEAST(?,expires_at) WHERE email=? AND expires_at>UTC_TIMESTAMP()');
        $s->execute([password_hash($code,PASSWORD_BCRYPT),gmdate('Y-m-d H:i:s',time()+600),$email]);
        if (!$s->rowCount()) return false;
        self::mail($email,$code);return true;
    }
    private static function mail(string $email,string $code): void
    {
        if (!Mailer::isConfigured()) return;
        try { Mailer::send($email,'[BloodMatch] Verify your registration','<p>Your verification code is <strong>'.htmlspecialchars($code,ENT_QUOTES,'UTF-8').'</strong>. It expires within 10 minutes or when your pending registration expires.</p>',EmailAttemptBudget::ACCOUNT); }
        catch(\Throwable $e) { error_log('[mailer] registration verification delivery unavailable'); }
    }
    public static function removeDocument(array $doc): void
    {
        $path=DocumentStorageService::pathFor((string)$doc['stored_name']);
        if (is_link($path)) throw new RuntimeException('Unsafe document path.');
        if (is_file($path) && !unlink($path)) throw new RuntimeException('Could not remove private document.');
    }
}
