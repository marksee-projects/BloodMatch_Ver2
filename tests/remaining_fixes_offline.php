<?php
declare(strict_types=1);
define('BASE_PATH',dirname(__DIR__));require BASE_PATH.'/backend/src/autoload.php';
require BASE_PATH.'/backend/lib/phpmailer/Exception.php';require BASE_PATH.'/backend/lib/phpmailer/SMTP.php';
use BloodMatch\Services\DocumentCipher;
use BloodMatch\Services\Mailer;
use BloodMatch\Services\SmtpOutcomeTracker;
use BloodMatch\Utils\AggregateCsv;
$passed=0;
function remainingCheck(bool $valid,string $label): void { global $passed;if (!$valid) { throw new LogicException('FAIL '.$label); }$passed++;echo 'PASS '.$label.PHP_EOL; }
$key=random_bytes(32);$id=str_repeat('a',32);$plain="%PDF-1.4\nSynthetic document\n";$context=DocumentCipher::context(str_repeat('b',64),'application/pdf',strlen($plain));
$sealed=DocumentCipher::seal($plain,$context,$key,$id);
remainingCheck(DocumentCipher::open($sealed,$context,$key)===$plain,'authenticated document roundtrip');
remainingCheck(!str_contains($sealed,$plain),'encrypted bytes do not contain original document');
remainingCheck(DocumentCipher::seal($plain,$context,$key,$id)!==$sealed,'fresh nonce changes ciphertext');
foreach (['wrong_key','wrong_context','truncated','tampered'] as $case) {
    $bytes=$sealed;$testKey=$key;$testContext=$context;
    if ($case==='wrong_key') { $testKey=random_bytes(32); }
    if ($case==='wrong_context') { $testContext.='x'; }
    if ($case==='truncated') { $bytes=substr($bytes,0,60); }
    if ($case==='tampered') { $bytes[strlen($bytes)-1]=chr(ord($bytes[strlen($bytes)-1])^1); }
    try { DocumentCipher::open($bytes,$testContext,$testKey);throw new LogicException('Unauthenticated bytes accepted.'); }
    catch (RuntimeException $e) { remainingCheck(true,$case.' refused'); }
}
foreach ([[false,false,null,'rejected'],[true,false,null,'uncertain'],[true,false,451,'rejected'],[true,false,550,'rejected'],[true,true,null,'accepted']] as [$started,$accepted,$code,$outcome]) {
    remainingCheck(SmtpOutcomeTracker::classify($started,$accepted,$code)===$outcome,'SMTP stage outcome '.$outcome);
}
remainingCheck(Mailer::transportOutcome(false)==='uncertain','unstructured false is uncertain');
remainingCheck(Mailer::transportOutcome(['outcome'=>'rejected'])==='rejected','explicit proven rejection accepted');
remainingCheck(Mailer::transportOutcome(['outcome'=>'invalid'])==='uncertain','invalid outcome cannot release a reservation');
foreach (['=1+1',' +CMD',"\t@SUM(1)",'-1+2',"\tformula"] as $value) {
    remainingCheck(str_starts_with(AggregateCsv::cell($value),"'"),'spreadsheet formula/control prefix neutralized');
}
$csv=AggregateCsv::render(['date_from'=>'=1+1','date_to'=>'2026-10-10','chapter_id'=>2,'request_volume'=>['total_requests'=>3,'email'=>'private@example.test'],'donor_pool'=>['total_enrolled'=>4],'email'=>'secret@example.test','phone'=>'secret-phone']);
remainingCheck(str_contains($csv,'total_requests,3') && str_contains($csv,'total_enrolled,4'),'approved aggregate metrics exported');
remainingCheck(!str_contains($csv,'@example.test') && !str_contains($csv,'secret-phone'),'personal/unapproved fields excluded');
remainingCheck(str_contains($csv,"'=1+1"),'CSV metadata injection neutralized');
// External key lookup and staged reads use only an ephemeral synthetic key directory.
$directory=sys_get_temp_dir().DIRECTORY_SEPARATOR.'bm-cipher-test-'.bin2hex(random_bytes(8));
$saved=$_ENV;mkdir($directory,0700);$keyFile=$directory.DIRECTORY_SEPARATOR.$id.'.key';file_put_contents($keyFile,$key);
try {
    $_ENV['DOCUMENT_KEY_DIRECTORY']=$directory;$_ENV['DOCUMENT_ACTIVE_KEY_ID']=$id;$_ENV['DOCUMENT_ENCRYPTION_MODE']='dual';
    remainingCheck(DocumentCipher::key($id)===$key,'external synthetic key lookup');
    remainingCheck(DocumentCipher::decode($sealed,$context)===$plain,'encrypted staged read');
    remainingCheck(DocumentCipher::decode($plain,$context)===$plain,'dual mode allows legacy read');
    $_ENV['DOCUMENT_ENCRYPTION_MODE']='strict';
    try { DocumentCipher::decode($plain,$context);throw new LogicException('Strict mode accepted plaintext.'); }
    catch (RuntimeException $e) { remainingCheck(true,'strict mode refuses legacy bytes'); }
    $_ENV['DOCUMENT_KEY_DIRECTORY']=BASE_PATH;
    try { DocumentCipher::keyDirectory();throw new LogicException('Workspace key directory accepted.'); }
    catch (RuntimeException $e) { remainingCheck(true,'keys inside repository refused'); }
} finally { $_ENV=$saved;unlink($keyFile);rmdir($directory); }
echo "$passed remaining-fix offline checks passed; synthetic external key cleaned; no DB or SMTP.\n";
