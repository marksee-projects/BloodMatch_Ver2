<?php
declare(strict_types=1);

// Explicit, recipient-scoped design samples; never create real account/request events.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/backend/src/autoload.php';

use BloodMatch\Config\{Database,Env};
use BloodMatch\Services\{EmailAttemptBudget,EmailTemplate,Mailer,SmtpOutcomeTracker};

Env::load(dirname(__DIR__).'/.env');
$options = getopt('', ['send']);
$send = isset($options['send']);
$recipient = 'markseejr@gmail.com';
$directory = dirname(__DIR__).'/scratch/email-design-samples';
if (!is_dir($directory) && !mkdir($directory, 0700, true)) { throw new RuntimeException('Preview directory unavailable.'); }
$request = [
    'type'=>'match.new', 'title'=>'Sample emergency request',
    'body'=>'DESIGN SAMPLE ONLY. Fictional request; no blood donation is being requested.',
    'requester_name'=>'Demo Member One', 'required_blood_type'=>'O+',
    'facility_name'=>'Demo Hospital One', 'municipality_name'=>'Balanga',
    'needed_datetime'=>gmdate('Y-m-d H:i:s', time()+86400),
];
$samples = [
    'otp'=>['subject'=>'[BloodMatch SAMPLE 1/3] Email verification code',
        'class'=>EmailAttemptBudget::ACCOUNT, 'template'=>EmailTemplate::otp('123456',600)],
    'membership'=>['subject'=>'[BloodMatch SAMPLE 2/3] Membership verified',
        'class'=>EmailAttemptBudget::NORMAL, 'template'=>EmailTemplate::notifications([
            ['type'=>'verification.decision','title'=>'Your membership has been verified','body'=>'Sample membership approval.'],
        ])],
    'grouped'=>['subject'=>'[BloodMatch SAMPLE 3/3] Grouped emergency matches',
        'class'=>EmailAttemptBudget::EMERGENCY, 'template'=>EmailTemplate::notifications([
            $request, array_replace($request,['requester_name'=>'Demo Member Two',
                'facility_name'=>'Demo Hospital Two','municipality_name'=>'Orani']),
        ])],
];
foreach ($samples as $name=>&$sample) {
    $notice = 'DESIGN SAMPLE ONLY — no account or request was changed. The OTP is not a working code.';
    $sample['template']['html'] = str_replace('<h1 ', '<p style="padding:12px;background:#fff4d6;color:#604500;font-size:13px">'.htmlspecialchars($notice, ENT_QUOTES, 'UTF-8').'</p><h1 ', $sample['template']['html']);
    $sample['template']['text'] = $notice."\n\n".$sample['template']['text'];
    if (file_put_contents($directory.'/'.$name.'.html',$sample['template']['html'])===false) { throw new RuntimeException('Preview write failed.'); }
}
unset($sample);
if (!$send) { echo json_encode(['status'=>'preview','smtp'=>false,'recipient'=>$recipient,'directory'=>$directory,'samples'=>array_keys($samples)]).PHP_EOL; exit; }

// A persisted claim prevents accidental retries after partial or uncertain SMTP delivery.
$statePath = $directory.'/delivery-state.json';
$lock = fopen($directory.'/delivery.lock','c');
if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) { throw new RuntimeException('Another sample run is active.'); }
try {
    $host=(string)Env::get('MAIL_HOST',''); $port=(int)Env::get('MAIL_PORT','587');
    if ($host==='' || $host==='mock' || !in_array($port,[465,587],true)
        || Env::get('BLOODMATCH_TEST_ISOLATION','')==='1'
        || Env::get('MAIL_USER','')==='' || Env::get('MAIL_PASS','')==='') {
        throw new RuntimeException('Real TLS SMTP configuration is required.');
    }
    $target=Database::pdo()->query('SELECT DATABASE() AS name,@@port AS port')->fetch();
    if ($target['name']!=='bloodmatch' || (int)$target['port']!==3306) { throw new RuntimeException('Unexpected accounting database.'); }
    if (!Database::pdo()->query("SHOW TABLES LIKE 'email_attempts'")->fetch()) { throw new RuntimeException('Email accounting unavailable.'); }
    foreach (['Exception','SMTP','PHPMailer'] as $class) {
        if (!class_exists('PHPMailer\\PHPMailer\\'.$class)) { require_once dirname(__DIR__).'/backend/lib/phpmailer/'.$class.'.php'; }
    }
    $state=is_file($statePath)?json_decode(file_get_contents($statePath),true,512,JSON_THROW_ON_ERROR):[];
    foreach ($samples as $name=>$sample) {
        if (isset($state[$name])) { echo json_encode(['sample'=>$name,'status'=>'already_attempted','previous'=>$state[$name]['status']]).PHP_EOL; continue; }
        $state[$name]=['status'=>'started','utc'=>gmdate('c')];
        if (file_put_contents($statePath,json_encode($state,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false) { throw new RuntimeException('Delivery journal unavailable.'); }
        $mail=new PHPMailer\PHPMailer\PHPMailer(true);
        $smtp=new SmtpOutcomeTracker();
        $mail->setSMTPInstance($smtp);$mail->isSMTP();$mail->Host=$host;$mail->Port=$port;
        $mail->SMTPAuth=true;$mail->Username=Env::get('MAIL_USER','');$mail->Password=Env::get('MAIL_PASS','');
        $mail->SMTPSecure=$port===465?'ssl':'tls';$mail->SMTPAutoTLS=false;
        $mail->SMTPOptions=['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]];
        $mail->Timeout=10;$mail->Timelimit=15;$mail->SMTPDebug=0;$mail->CharSet='UTF-8';
        $mail->setFrom((string)Env::get('MAIL_FROM',''),'BloodMatch');$mail->addAddress($recipient);
        $mail->Subject=$sample['subject'];$mail->isHTML(true);
        $mail->Body=$sample['template']['html'];$mail->AltBody=$sample['template']['text'];
        $result=Mailer::attemptWithTransport($sample['class'],static function() use ($mail,$smtp): array {
            try { $mail->send(); } catch (Throwable) { /* Tracker distinguishes rejection and uncertainty. */ }
            finally { $mail->smtpClose(); }
            return ['outcome'=>$smtp->outcome()];
        });
        $state[$name]=$result+['utc'=>gmdate('c')];
        if (file_put_contents($statePath,json_encode($state,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),LOCK_EX)===false) { throw new RuntimeException('Delivery result could not be recorded; do not retry.'); }
        echo json_encode(['sample'=>$name]+$result).PHP_EOL;
    }
} finally { flock($lock,LOCK_UN);fclose($lock); }
