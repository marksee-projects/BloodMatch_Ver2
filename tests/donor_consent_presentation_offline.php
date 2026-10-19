<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Services\ChatConsentService as Consent;
$a=['id'=>1,'role'=>'member','account_status'=>'active','verification_status'=>'verified'];$b=array_replace($a,['id'=>2]);$now=1792396800;$checks=0;
function checkConsent(bool $ok,string $label):void { global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo "PASS $label\n"; }
$base=['status'=>null,'created_at'=>null,'updated_at'=>null];
checkConsent(Consent::pairPresentation($a,$b,$base,$now)['can_request'],'No consent permits only a message request');
$pending=['status'=>'pending','sender_id'=>1,'created_at'=>gmdate('Y-m-d H:i:s',$now-60),'updated_at'=>gmdate('Y-m-d H:i:s',$now-60)];
$v=Consent::pairPresentation($a,$b,$pending,$now);checkConsent(!$v['can_request']&&!$v['incoming']&&$v['conversation_id']===null,'Outgoing pending consent cannot open chat');
checkConsent(Consent::pairPresentation($a,$b,array_replace($pending,['sender_id'=>2]),$now)['incoming'],'Incoming request must be reviewed');
checkConsent(Consent::pairPresentation($a,$b,array_replace($pending,['created_at'=>gmdate('Y-m-d H:i:s',$now-7*86400)]),$now)['can_request'],'Expired pending request can be requested again');
$declined=array_replace($pending,['status'=>'declined']);
checkConsent(!Consent::pairPresentation($a,$b,$declined,$now)['can_request'],'Declined request preserves one-day wait');
checkConsent(Consent::pairPresentation($a,$b,array_replace($declined,['updated_at'=>gmdate('Y-m-d H:i:s',$now-86400)]),$now)['can_request'],'Retry allowed at existing one-day boundary');
$accepted=array_replace($pending,['status'=>'accepted','conversation_id'=>5]);
checkConsent(Consent::pairPresentation($a,$b,$accepted,$now)['conversation_id']===5,'Accepted authorized pair reuses conversation');
checkConsent(Consent::pairPresentation($a,$b,array_replace($accepted,['conversation_id'=>null]),$now)['status']==='unavailable','Missing authorized conversation cannot become new consent');
checkConsent(Consent::pairPresentation($a,array_replace($b,['account_status'=>'deactivated']),$accepted,$now)['conversation_id']===null,'Account restrictions still hide chat action');
checkConsent(Consent::pairPresentation(array_replace($a,['pending_report_hold'=>true]),$b,$accepted,$now)['conversation_id']===5,'Report hold alone does not block existing consent');
echo "$checks consent-presentation checks passed; no database or SMTP.\n";
