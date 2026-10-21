<?php
declare(strict_types=1);
namespace BloodMatch\Services;
use BloodMatch\Config\Database;
use BloodMatch\Config\Env;
/** Already-accounted transport only. Never call Mailer::send here (would reserve twice). */
final class QueuedSmtpTransport
{
    public function __construct(private ?string $expectedRecipient=null)
    {
        foreach (['Exception','SMTP','PHPMailer'] as $class) {
            if (!class_exists('PHPMailer\\PHPMailer\\'.$class)) { require_once \BloodMatch\Config\AppConfig::basePath().'/backend/lib/phpmailer/'.$class.'.php'; }
        }
    }
    public function __invoke(array $metadata): array
    {
        $smtp=new SmtpOutcomeTracker();$mail=new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $ids=$metadata['job_ids'];$marks=implode(',',array_fill(0,count($ids),'?'));
            $s=Database::pdo()->prepare("SELECT n.user_id,n.title,n.body,n.type,u.email,u.email_verified_at,br.required_blood_type,br.facility_name,br.needed_datetime,loc.municipality_name,TRIM(CONCAT_WS(' ',requester.first_name,requester.last_name)) AS requester_name FROM email_outbox e JOIN notifications n ON n.id=e.notification_id JOIN users u ON u.id=n.user_id LEFT JOIN blood_requests br ON n.related_type='blood_request' AND br.id=n.related_id LEFT JOIN users requester ON requester.id=br.requester_id LEFT JOIN bataan_locations loc ON loc.id=br.location_id WHERE e.id IN ($marks) ORDER BY e.id");
            $s->execute($ids);$rows=$s->fetchAll(\PDO::FETCH_ASSOC);
            if (count($rows)!==count($ids) || count(array_unique(array_column($rows,'user_id')))!==1) { return ['outcome'=>'rejected']; }
            if ($this->expectedRecipient!==null && strtolower($rows[0]['email'])!==strtolower($this->expectedRecipient)) { return ['outcome'=>'rejected']; }
            foreach ($rows as $row) {
                if (empty($row['email_verified_at']) || !in_array($row['type'],['match.new','verification.decision'],true)) { return ['outcome'=>'rejected']; }
                $domain=strtolower((string)substr(strrchr($row['email'],'@')?:'',1));
                if (!filter_var($row['email'],FILTER_VALIDATE_EMAIL) || preg_match('/(^|\.)(local|test|invalid)$/',$domain)) { return ['outcome'=>'rejected']; }
            }
            $host=(string)Env::get('MAIL_HOST','');$port=(int)Env::get('MAIL_PORT','587');
            if ($host==='' || $host==='mock' || !in_array($port,[465,587],true)) { return ['outcome'=>'rejected']; }
            $mail->setSMTPInstance($smtp);$mail->isSMTP();$mail->Host=$host;$mail->Port=$port;
            $mail->SMTPAuth=true;$mail->Username=Env::get('MAIL_USER','');$mail->Password=Env::get('MAIL_PASS','');
            $mail->SMTPSecure=$port===465 ? 'ssl' : 'tls';$mail->SMTPAutoTLS=false;
            $mail->SMTPOptions=['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]];
            $mail->Timeout=10;$mail->Timelimit=15;$mail->SMTPDebug=0;$mail->CharSet='UTF-8';
            $mail->setFrom((string)Env::get('MAIL_FROM',''),'BloodMatch');$mail->addAddress($rows[0]['email']);
            $mail->MessageID=$metadata['message_id'];$mail->Subject=($rows[0]['type']==='verification.decision' && !str_contains(strtolower($rows[0]['title']),'rejected')) ? '[BloodMatch] Your account is now verified' : (count($rows)>1 ? '[BloodMatch] Emergency donation opportunities' : '[BloodMatch] '.$rows[0]['title']);
            $template=EmailTemplate::notifications($rows);
            $mail->isHTML(true);$mail->Body=$template['html'];$mail->AltBody=$template['text'];
            $mail->send();
        } catch (\Throwable $e) { /* No raw errors, addresses or SMTP secrets in status/logs. */ }
        finally { $mail->smtpClose(); }
        return ['outcome'=>$smtp->outcome()];
    }
}
