<?php
declare(strict_types=1);
// Pure policies; no environment loading, PDO, HTTP, tasks or SMTP.
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Services\{DonationCompletionPolicy as Policy,WorkflowLockService,DonorEligibilityService,BloodCompatibilityService};
$checks=0;
function donationCheck(bool $ok,string $label): void { global $checks;if (!$ok) throw new RuntimeException('FAIL '.$label);$checks++;echo "PASS $label\n"; }
function donationDenied(callable $work,int $status,string $label): void { try {$work();} catch(RuntimeException $e) {donationCheck((int)$e->getCode()===$status,$label);return;}throw new RuntimeException('FAIL '.$label); }
$submitted='2026-10-19 04:00:00';$response='2026-10-18 01:00:00';
donationCheck(Policy::occurrence('2026-10-19T12:00:00+08:00',$response,$submitted)===$submitted,'Manila picker instant is stored in UTC');
donationCheck(Policy::occurrence('2026-10-19T04:00:00Z',$response,$submitted)===$submitted,'Explicit UTC supported');
donationDenied(fn()=>Policy::occurrence('2026-10-19T12:01:00+08:00',$response,$submitted),400,'Future occurrence rejected');
donationDenied(fn()=>Policy::occurrence('2026-10-18T00:59:59Z',$response,$submitted),400,'Occurrence before recorded response rejected');
donationDenied(fn()=>Policy::occurrence('2026-02-30T00:00:00Z',$response,$submitted),400,'Calendar rollover rejected');
donationDenied(fn()=>Policy::occurrence('2026-10-19T04:00:00',$response,$submitted),400,'Ambiguous timezone rejected');
donationDenied(fn()=>Policy::occurrence('2026-10-19T04:00:00+99:00',$response,$submitted),400,'Invalid offset rejected');
donationDenied(fn()=>Policy::occurrence(null,$response,$submitted),400,'Legacy missing date requires clarification');
donationCheck(Policy::restrictionAnchor('2026-10-19 04:00:00','2026-10-18 03:00:00')===$submitted,'Late report cannot shorten an established anchor');
donationCheck(Policy::restrictionAnchor(null,$response)===$response,'First occurrence establishes an anchor');
donationCheck(Policy::restrictionAnchor($response,$submitted)===$submitted,'Later occurrence advances the anchor');
$r=['status'=>'PENDING','workflow_state'=>'awaiting_requester','reported_at'=>'2026-10-17 04:00:00','reminder_sent_at'=>null,'escalated_at'=>null,'acknowledged_at'=>null,'content_version'=>2,'donor_id'=>1];
donationCheck(Policy::dueActions($r,'2026-10-18 03:59:59')===[],'No early reminder');
donationCheck(Policy::dueActions($r,'2026-10-18 04:00:00')===['remind'],'One reminder due at exactly 24h');
donationCheck(Policy::dueActions($r,'2026-10-19 04:00:00')===['remind','escalate'],'Delayed worker catches up both deadlines without a decision');
donationCheck(Policy::dueActions(array_replace($r,['reminder_sent_at'=>'2026-10-18 04:00:00']),$submitted)===['escalate'],'Sent reminder is never repeated');
donationCheck(Policy::dueActions(array_replace($r,['reminder_sent_at'=>$submitted,'escalated_at'=>$submitted]),$submitted)===[],'Escalation retries are silent');
donationCheck(Policy::dueActions(array_replace($r,['acknowledged_at'=>'2026-10-18 04:00:00']),$submitted)===[],'Requester response stops silence timers');
donationCheck(Policy::dueActions(array_replace($r,['status'=>'REJECTED']),$submitted)===[],'Rejected reports are never auto-confirmed or timed out');
donationCheck(Policy::dueActions(array_replace($r,['reported_at'=>'invalid']),$submitted)===[],'Malformed dates do not produce timer actions');
donationDenied(fn()=>Policy::assertVersion($r,1),409,'Stale report version rejected');
Policy::assertVersion($r,2);donationCheck(true,'Exact version accepted');
$request=['requester_id'=>2,'status'=>'OPEN','quantity_units'=>1,'needed_datetime'=>'2026-10-20 00:00:00'];
Policy::assertRequester(['id'=>2,'account_status'=>'active'],$request,$r);donationCheck(true,'Owner can acknowledge another donor');
donationDenied(fn()=>Policy::assertRequester(['id'=>3,'account_status'=>'active'],$request,$r),403,'Outsider cannot acknowledge');
donationDenied(fn()=>Policy::assertRequester(['id'=>1,'account_status'=>'active'],array_replace($request,['requester_id'=>1]),$r),403,'Self-acknowledgment rejected');
donationDenied(fn()=>WorkflowLockService::assertReportReviewer(['id'=>2,'role'=>'admin','account_status'=>'active'],$request,1),403,'Requester cannot self-review administratively');
donationCheck(Policy::canFulfill($request,1,$submitted),'Open need fulfills at requested count');
donationCheck(!Policy::canFulfill($request,0,$submitted),'Insufficient count cannot fulfill');
foreach(['CANCELLED','EXPIRED','FULFILLED'] as $state) donationCheck(!Policy::canFulfill(array_replace($request,['status'=>$state]),2,$submitted),"Late donation preserves $state request");
donationCheck(!Policy::canFulfill(array_replace($request,['needed_datetime'=>'2026-10-18 00:00:00']),1,$submitted),'Effectively expired need cannot fulfill');
// A pending-report hold has its own eligibility code and cannot masquerade as cooldown.
$donor=['id'=>1,'role'=>'member','account_status'=>'active','verification_status'=>'verified','email_verified_at'=>$response,'donor_enrolled_at'=>$response,'pending_report_hold'=>true];
donationCheck(DonorEligibilityService::evaluateForRequest($donor,$request)['code']==='pending_report_hold','Report hold independently blocks new offers');
echo "$checks pure donation-completion checks passed; no database or SMTP.\n";
