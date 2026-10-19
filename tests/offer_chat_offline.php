<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Services\{ChatPolicy,DonationProofService,DocumentCipher,DonationCompletionPolicy};
$checks=0;function ocCheck(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo 'PASS '.$label.PHP_EOL;}
function ocDenied(callable $fn,string $label):void {try{$fn();}catch(RuntimeException){ocCheck(true,$label);return;}throw new RuntimeException('FAIL '.$label);}
$a=['id'=>1,'role'=>'member','account_status'=>'active','verification_status'=>'verified'];$b=array_replace($a,['id'=>2]);$policy=new ChatPolicy();
foreach(['pending_report_hold'=>true,'non_response_standby'=>true,'donor_availability'=>'standby','last_verified_donation_at'=>'2026-10-19 00:00:00'] as $key=>$value)ocCheck($policy->canMessage(array_replace($a,[$key=>$value]),$b),'Donor condition '.$key.' does not deny eligible existing chat');
ocCheck(!$policy->canMessage($a,$a),'No self messaging');
ocCheck(!$policy->canMessage(array_replace($a,['account_status'=>'deactivated']),$b),'Account restriction still blocks messaging');
ocCheck(!$policy->canMessage($a,array_replace($b,['verification_status'=>'pending'])),'Peer verification still required');
$row=['donor_id'=>1];$request=['requester_id'=>2];
DonationProofService::assertAccess($a,$row,$request);ocCheck(true,'Donor can access own report evidence');
DonationProofService::assertAccess($b,$row,$request);ocCheck(true,'Requester can access exact report evidence');
ocDenied(fn()=>DonationProofService::assertAccess(array_replace($a,['id'=>3]),$row,$request),'Other members cannot access proof');
$admin=array_replace($a,['id'=>4,'role'=>'admin','email'=>'markseejr@gmail.com']);DonationProofService::assertAccess($admin,$row,$request,true);ocCheck(true,'Canonical non-conflicted reviewer allowed');
ocDenied(fn()=>DonationProofService::assertAccess(array_replace($admin,['id'=>1]),$row,$request,true),'Donor admin cannot review self');
ocDenied(fn()=>DonationProofService::assertAccess(array_replace($admin,['id'=>2]),$row,$request,true),'Requester admin cannot review own case');
ocDenied(fn()=>DonationProofService::assertAccess(array_replace($admin,['email'=>'other@example.test']),$row,$request,true),'Noncanonical staff cannot review evidence');
ocCheck(DonationProofService::prepare([])===[],'Optional absent proof requires no storage');
ocDenied(fn()=>DonationProofService::prepare(['tmp_name'=>['a','b','c','d']]),'Four photos rejected before processing');
$key=random_bytes(32);$keyId=bin2hex(random_bytes(16));$context=DocumentCipher::context(str_repeat('a',64),'image/png',8);$plain='pixels!!';$sealed=DocumentCipher::seal($plain,$context,$key,$keyId);
ocCheck(DocumentCipher::open($sealed,$context,$key)===$plain,'Existing authenticated document cipher round trips in memory');
ocDenied(fn()=>DocumentCipher::open($sealed,$context.'tampered',$key),'Encrypted proof cannot be rebound to different metadata');
ocCheck(!DonationCompletionPolicy::canFulfill(['status'=>'OPEN','needed_datetime'=>'2099-01-01 00:00:00','quantity_units'=>1],0,'2026-10-19 00:00:00'),'Zero completed matches means offers/chat do not fulfill');
echo "$checks offline offer/chat/proof checks passed; no database, files, HTTP or SMTP.\n";
