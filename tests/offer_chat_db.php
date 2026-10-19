<?php
declare(strict_types=1);
// USER-RUN ONLY. bloodmatch_test:3306; fixture transaction rolls back. No files/SMTP.
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/backend/src/autoload.php';require dirname(__DIR__).'/database/operational_checkpoint_support.php';
use BloodMatch\Services\{OfferChatService as Offers,DonationCompletionService as Completion,DonationCorrectionService as Corrections,DonationProofService as Proof,ChatPolicy,ChatConsentService,AuthService};
use BloodMatch\Repositories\{UserRepository,MatchRepository,DonationWorkflowRepository};
$p=operationalTarget('test');Offers::requireReady();$repo=new UserRepository();$nonce=bin2hex(random_bytes(8));$checks=0;
// Fail before fixtures rather than crashing inside chat policy's online-status lookup.
if (!$p->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='user_presence'")->fetchColumn()) {
    fwrite(STDERR,"Missing prerequisite: user_presence (028_user_presence.sql). Review database/review_test_presence_migration.php before retrying; migration 031 must not be rerun.\n");exit(1);
}
function ocDb(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo "PASS $label\n";}
function ocFail(callable $fn,int $status,string $label):void {global $p;$p->exec('SAVEPOINT offer_expected_error');try{$fn();}catch(RuntimeException $e){$p->exec('ROLLBACK TO SAVEPOINT offer_expected_error');$p->exec('RELEASE SAVEPOINT offer_expected_error');ocDb((int)$e->getCode()===$status,$label);return;}throw new RuntimeException('FAIL '.$label);}
function ocUser(string $tag):array {global $p,$repo,$nonce;$s=$p->prepare("INSERT INTO users(email,first_name,last_name,role,verification_status,account_status,date_of_birth,blood_type,blood_type_source,blood_type_verified,donor_enrolled_at,donor_availability,email_verified_at) VALUES(?,'Offer Test',?,'member','verified','active','1990-01-01','O+','self_reported',0,UTC_TIMESTAMP(),'available',UTC_TIMESTAMP())");$s->execute(["offer-$nonce-$tag@example.test",$tag]);return $repo->findById((int)$p->lastInsertId());}
function ocRequest(array $owner,string $urgency='routine',int $quantity=2):int {global $p;$s=$p->prepare("INSERT INTO blood_requests(requester_id,required_blood_type,quantity_units,facility_name,urgency,needed_datetime,status,review_status) VALUES(?,'O+',?,'Synthetic offer coordination',?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),'OPEN','not_required')");$s->execute([$owner['id'],$quantity,$urgency]);return(int)$p->lastInsertId();}
function ocOffer(int $request,array $donor):int {global $p;$s=$p->prepare("INSERT INTO matches(request_id,donor_id,status,responded_at,offer_cycle) VALUES(?,?,'RESPONDED',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY),1)");$s->execute([$request,$donor['id']]);return(int)$p->lastInsertId();}
$p->beginTransaction();
try {
    $owner=ocUser('requester');$donor=ocUser('donor');$other=ocUser('other');$outsider=ocUser('outsider');$adminId=$p->query("SELECT id FROM users WHERE role='admin' AND email='markseejr@gmail.com' AND account_status='active'")->fetchColumn();if(!$adminId)throw new RuntimeException('Canonical test admin required.');$admin=$repo->findById((int)$adminId);
    $contexts=[];
    // Suggested-donor messaging must not act as a donation offer, at any urgency.
    foreach(['routine','urgent','emergency'] as $urgency) {
        $suggestedRequest=ocRequest($owner,$urgency);
        $p->prepare("INSERT INTO matches(request_id,donor_id,status,offer_cycle) VALUES(?,?,'NOTIFIED',0)")->execute([$suggestedRequest,$outsider['id']]);
        $suggestedMatch=(int)$p->lastInsertId();
        $messageRequest=ChatConsentService::request($owner,(int)$outsider['id']);
        if($messageRequest['status']==='pending')ChatConsentService::decide($outsider,(int)$messageRequest['id'],'accept');
        $safe=(new \BloodMatch\Services\MatchService())->privacySafeMatches($suggestedRequest,null,$owner);
        ocDb($safe[0]['status']==='NOTIFIED'&&$safe[0]['chat']['conversation_id']!==null,"$urgency message acceptance grants chat only, not an offer");
        $private=(new \BloodMatch\Services\MatchService())->privacySafeMatches($suggestedRequest,null,$admin);
        ocDb($private[0]['chat']===null,"$urgency other viewer receives no owner's pair consent metadata");
        $s=$p->prepare('SELECT COUNT(*) FROM donation_chat_contexts WHERE match_id=?');$s->execute([$suggestedMatch]);ocDb((int)$s->fetchColumn()===0,"$urgency messaging creates no accepted donation context");
        $s=$p->prepare('SELECT COUNT(*) FROM donation_reports WHERE match_id=?');$s->execute([$suggestedMatch]);ocDb((int)$s->fetchColumn()===0&&!DonationWorkflowRepository::hasHold((int)$outsider['id'])&&MatchRepository::countCompleted($suggestedRequest)===0,"$urgency messaging creates no report, hold or donated units");
    }
    $responseRequest=ocRequest($owner);$responseService=new \BloodMatch\Services\MatchResponseService();
    $offered=$responseService->respond($donor,$responseRequest,'offer_chat_test');
    ocDb($offered['chat_request']===null,'Actual donor offer does not send a second message request');
    $s=$p->prepare('SELECT COUNT(*) FROM chat_requests WHERE user_low=? AND user_high=?');$s->execute([min($owner['id'],$donor['id']),max($owner['id'],$donor['id'])]);ocDb((int)$s->fetchColumn()===0,'Offer alone creates no pair consent');
    $responseService->withdraw($donor,$offered['match_id']);$responseService->respond($donor,$responseRequest,'offer_chat_test');
    ocFail(fn()=>Offers::accept($owner,$offered['match_id'],1),409,'Withdraw/re-offer invalidates the original offer cycle');
    foreach(['routine','urgent','emergency'] as $urgency) {
        $r=ocRequest($owner,$urgency);$m=ocOffer($r,$donor);
        ocDb(Offers::ownOffer($donor,$m)['conversation_id']===null,"$urgency offer alone grants no chat consent/context");
        ocFail(fn()=>Offers::accept($outsider,$m,1),403,"$urgency outsider cannot accept");
        $accepted=Offers::accept($owner,$m,1);$contexts[]=[$r,$m,$accepted];
        ocDb((new ChatPolicy())->canAccessConversation($donor,$accepted['conversation_id']),"$urgency acceptance satisfies existing chat gates");
        ocDb(Offers::accept($owner,$m,1)['changed']===false,"$urgency repeated acceptance is idempotent");
        ocDb(MatchRepository::countCompleted($r)===0,"$urgency acceptance contributes zero units");
    }
    [$r,$m,$accepted]=$contexts[0];$conversation=$accepted['conversation_id'];
    ocDb(count(array_unique(array_map(fn($v)=>$v[2]['conversation_id'],$contexts)))===1,'Same pair reuses one conversation across distinct requests');
    ocDb(count(Offers::contexts($owner,$conversation))===3,'Conversation exposes three exact request contexts');
    $second=ocOffer($r,$other);$secondContext=Offers::accept($owner,$second,1);ocDb($secondContext['conversation_id']!==$conversation,'Another donor has a separate private conversation');
    ocFail(fn()=>Offers::contexts($outsider,$conversation),404,'Outsider cannot read context card/report');
    ocDb(Offers::signal($owner,$accepted['context_id'],'prompt_donor',0)['changed'],'Requester can prompt donor');
    ocDb(Offers::signal($owner,$accepted['context_id'],'prompt_donor',0)['changed']===false,'Repeated prompt is silent/idempotent');
    ocDb(!DonationWorkflowRepository::hasHold((int)$donor['id']),'Requester prompt creates no donor hold');
    $s=$p->prepare('SELECT COUNT(*) FROM donation_reports WHERE match_id=?');$s->execute([$m]);ocDb((int)$s->fetchColumn()===0,'Prompt creates no submitted report or timer');
    $service=new Completion();$time=gmdate('Y-m-d\TH:i:s\Z',time()-3600);$submitted=$service->submit($donor,$m,$time,null);$id=$submitted['id'];
    ocDb(DonationWorkflowRepository::hasHold((int)$donor['id']),'Donor submission creates report hold');
    ocDb((new ChatPolicy())->canAccessConversation($repo->findById((int)$donor['id']),$conversation),'Report hold does not deny existing chat');
    ocDb(Offers::accept($owner,$m,1)['changed']===false,'Existing accepted offer remains usable while held');
    // Synthetic metadata only: no actual file is created or streamed by this suite.
    $fake=['stored_name'=>bin2hex(random_bytes(32)),'mime_type'=>'image/png','original_ext'=>'png','size_bytes'=>100];
    $beforeProof=$p->prepare('SELECT reported_at FROM donation_reports WHERE id=?');$beforeProof->execute([$id]);$submissionTime=$beforeProof->fetchColumn();
    Proof::add($donor,$id,1,[$fake]);ocDb(count(Proof::listing($owner,$id))===1,'Exact requester can list versioned private proof metadata');
    ocDb(MatchRepository::countCompleted($r)===0 && DonationWorkflowRepository::hasHold((int)$donor['id']),'Photo alone neither confirms donation nor releases hold');
    $beforeProof->execute([$id]);ocDb($beforeProof->fetchColumn()===$submissionTime,'Proof update does not restart original report timers');
    ocFail(fn()=>Proof::listing($outsider,$id),403,'Other donor/member cannot access proof metadata');
    ocFail(fn()=>$service->acknowledge($owner,$id,1,'received',null),409,'Adding proof invalidates old-version acknowledgment');
    $service->acknowledge($owner,$id,2,'received',null);ocDb(MatchRepository::countCompleted($r)===1,'Mutual acknowledgment counts one confirmed match as one unit');
    ocDb((new ChatPolicy())->canAccessConversation($repo->findById((int)$donor['id']),$conversation),'Confirmed donation cooldown does not deny existing chat');
    Corrections::request($owner,$id,2,'Please review the recorded detail.');ocDb(Corrections::request($owner,$id,2,'Retry')['changed']===false,'Completed correction request is idempotent');
    ocDb(in_array((int)$id,array_map('intval',array_column(DonationWorkflowRepository::listFor($admin,true),'id')),true),'Open completed-record correction stays visible in staff queue');
    $case=Corrections::listing($admin,$id)[0];$historyBefore=MatchRepository::countCompleted($r);$anchor=$repo->findById((int)$donor['id'])['last_verified_donation_at'];
    Corrections::resolve($admin,$id,(int)$case['id'],2,'Reviewed; no automatic reversal is authorized.');ocDb(MatchRepository::countCompleted($r)===$historyBefore && $repo->findById((int)$donor['id'])['last_verified_donation_at']===$anchor,'Staff correction response changes no credit or restriction');
    ocDb(!in_array((int)$id,array_map('intval',array_column(DonationWorkflowRepository::listFor($admin,true),'id')),true),'Responded correction leaves queue without changing completed donation');
    ocDb((int)(new \BloodMatch\Repositories\DonationReportRepository())->findById($id)['offer_context_id']===(int)$accepted['context_id'],'Report remains bound to its exact accepted offer context');
    [$lateR,$lateM,$lateAccepted]=$contexts[1];$p->prepare("UPDATE blood_requests SET status='EXPIRED' WHERE id=?")->execute([$lateR]);
    ocDb((new ChatPolicy())->canAccessConversation($donor,$conversation),'Request expiry preserves prior authorized messaging');
    $cards=Offers::contexts($owner,$conversation);$lateCard=array_values(array_filter($cards,fn($c)=>(int)$c['match_id']===$lateM))[0];ocDb($lateCard['active']===false,'Expired request coordination is inactive');
    ocFail(fn()=>Offers::signal($owner,$lateAccepted['context_id'],'still_arranging',0),409,'Still arranging cannot reactivate expired coordination');
    $duplicate=$service->submit($donor,$lateM,$time,null);ocFail(fn()=>$service->review($admin,$duplicate['id'],1,'confirm','Synthetic review'),409,'Same donor occurrence cannot receive credit on another request');
    [$lastR,$lastM,$lastAccepted]=$contexts[2];$p->prepare('UPDATE matches SET offer_cycle=2 WHERE id=?')->execute([$lastM]);ocFail(fn()=>Offers::accept($owner,$lastM,1),409,'Old acceptance cannot accept a new offer cycle');
    $s=$p->prepare("SELECT COUNT(*) FROM email_outbox e JOIN notifications n ON n.id=e.notification_id WHERE n.user_id IN (?,?,?,?)");$s->execute([$owner['id'],$donor['id'],$other['id'],$outsider['id']]);ocDb((int)$s->fetchColumn()===0,'New coordination/report actions enqueue no email');
    echo "$checks offer/chat DB checks passed; fixtures rolled back; no files or SMTP.\n";
}finally{$p->rollBack();}
