<?php
declare(strict_types=1);
// USER-RUN ONLY. Fixed test target, no SMTP; every fixture write rolls back.
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/backend/src/autoload.php';
require dirname(__DIR__).'/database/operational_checkpoint_support.php';
use BloodMatch\Services\{DonationCompletionService as Completion,DonationFollowupService,AuthService,DonorEligibilityService};
use BloodMatch\Repositories\{DonationWorkflowRepository as Workflow,DonationReportRepository,UserRepository,DonationStorageRepository as Storage};
$p=operationalTarget('test');Workflow::requireReady();
$admin=$p->query("SELECT id FROM users WHERE role='admin' AND email='markseejr@gmail.com' AND account_status='active'")->fetchColumn();
if(!$admin)throw new RuntimeException('The canonical test administrator is required.');
$checks=0;$nonce=bin2hex(random_bytes(8));$service=new Completion();$users=new UserRepository();$reports=new DonationReportRepository();
function dcCheck(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException('FAIL '.$label);$checks++;echo "PASS $label\n";}
function dcDenied(callable $work,int $status,string $label): void {global $p;$p->exec('SAVEPOINT donation_expected_error');try{$work();}catch(RuntimeException $e){$p->exec('ROLLBACK TO SAVEPOINT donation_expected_error');$p->exec('RELEASE SAVEPOINT donation_expected_error');dcCheck((int)$e->getCode()===$status,$label);return;}throw new RuntimeException('FAIL '.$label);}
function dcUser(string $tag): array {global $p,$nonce,$users;$s=$p->prepare("INSERT INTO users(email,first_name,last_name,role,verification_status,account_status,date_of_birth,blood_type,blood_type_source,blood_type_verified,donor_enrolled_at,donor_availability,email_verified_at) VALUES(?,'Donation',?,'member','verified','active','1990-01-01','O+','self_reported',0,UTC_TIMESTAMP(),'available',UTC_TIMESTAMP())");$s->execute(["donation-$nonce-$tag@example.test",$tag]);return $users->findById((int)$p->lastInsertId());}
function dcRequest(int $owner,int $quantity=1): int {global $p;$s=$p->prepare("INSERT INTO blood_requests(requester_id,required_blood_type,quantity_units,facility_name,urgency,needed_datetime,status,review_status) VALUES(?,'O+',?,'Synthetic donation test','routine',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),'OPEN','not_required')");$s->execute([$owner,$quantity]);return(int)$p->lastInsertId();}
function dcMatch(int $request,int $donor): int {global $p;$s=$p->prepare("INSERT INTO matches(request_id,donor_id,status,responded_at) VALUES(?,?,'RESPONDED',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 72 HOUR))");$s->execute([$request,$donor]);return(int)$p->lastInsertId();}
function dcSubmit(array $donor,int $match): int {global $service;static $occurrenceOffset=0;return(int)$service->submit($donor,$match,gmdate('Y-m-d\TH:i:s\Z',time()-3600-(++$occurrenceOffset)),'Private synthetic reference')['id'];}
function dcStatus(int $request): string {global $p;$s=$p->prepare('SELECT status FROM blood_requests WHERE id=?');$s->execute([$request]);return$s->fetchColumn();}
$p->beginTransaction();
try {
 $a=dcUser('a');$b=dcUser('b');$owner=dcUser('owner');$outsider=dcUser('outsider');$admin=$users->findById((int)$admin);
 $request=dcRequest((int)$owner['id']);$ma=dcMatch($request,(int)$a['id']);$mb=dcMatch($request,(int)$b['id']);$ra=dcSubmit($a,$ma);$rb=dcSubmit($b,$mb);
 $responseRepo=new \BloodMatch\Repositories\BloodRequestRepository();
 dcCheck(in_array($ma,array_map('intval',array_column($responseRepo->listByRespondedDonor((int)$a['id']),'match_id')),true),'My Responses SQL returns the donor response');
 dcCheck($responseRepo->listByRespondedDonor((int)$outsider['id'])===[],'My Responses SQL excludes other donors');
 dcCheck(Workflow::hasHold((int)$a['id']),'Submission creates a distinct report hold');
 dcCheck(DonorEligibilityService::evaluateDonor($users->findById((int)$a['id']))['code']==='pending_report_hold','Held donor cannot make a new offer');
 dcDenied(fn()=>$service->acknowledge($outsider,$ra,1,'received',null),403,'Outsider cannot acknowledge');
 dcDenied(fn()=>$service->events($outsider,$ra),404,'Private evidence history denies outsiders');
 $result=$service->acknowledge($owner,$ra,1,'received',null);
 dcCheck($result['completion_basis']==='mutual' && dcStatus($request)==='FULFILLED','Agreement atomically credits and fulfills');
 dcCheck(!Workflow::hasHold((int)$a['id']),'Recorded donation replaces only its own hold with restrictions');
 dcCheck($users->findById((int)$a['id'])['last_verified_donation_at']===$reports->findById($ra)['occurred_at'],'Safety anchor uses actual occurrence');
 $result=$service->acknowledge($owner,$rb,1,'received',null);
 dcCheck($result['workflow_state']==='completed' && dcStatus($request)==='FULFILLED','Genuine excess donation recorded after fulfillment');
 $s=$p->prepare(Storage::consolidated()?'SELECT COUNT(*) FROM donation_reports WHERE credit_recorded_at IS NOT NULL AND match_id IN (?,?)':'SELECT COUNT(*) FROM donation_credits WHERE match_id IN (?,?)');$s->execute([$ma,$mb]);dcCheck((int)$s->fetchColumn()===2,'Each real match has one credit');
 $s=$p->prepare("SELECT COUNT(*) FROM notifications WHERE dedup_key LIKE ?");$s->execute(["donation-workflow:$ra:%"]);$before=(int)$s->fetchColumn();
 dcCheck($service->acknowledge($owner,$ra,1,'received',null)['changed']===false,'Repeated acknowledgment is idempotent');
 $s->execute(["donation-workflow:$ra:%"]);dcCheck((int)$s->fetchColumn()===$before,'Repeated acknowledgment emits no new alert');
 dcDenied(fn()=>$service->review($admin,$ra,1,'reject','Conflicting review'),409,'Final donation cannot receive a conflicting decision');
 foreach(['CANCELLED','EXPIRED'] as $state) {
   $r=dcRequest((int)$owner['id']);$m=dcMatch($r,(int)$a['id']);$id=dcSubmit($a,$m);$p->prepare('UPDATE blood_requests SET status=? WHERE id=?')->execute([$state,$r]);
   $service->review($admin,$id,1,'confirm','Checked synthetic private supporting reference.');
   dcCheck(dcStatus($r)===$state,"Late admin confirmation preserves $state");
 }
 // Two claims pre-exist, so resolving one cannot erase the other hold.
 $r=dcRequest((int)$owner['id'],2);$m=dcMatch($r,(int)$outsider['id']);$id=dcSubmit($outsider,$m);
 $r2=dcRequest((int)$owner['id']);$m2=dcMatch($r2,(int)$outsider['id']);$id2=dcSubmit($outsider,$m2);
 $service->acknowledge($owner,$id,1,'did_not_donate','No donation received.');
 dcCheck($reports->findById($id)['status']==='PENDING','Disagreement does not automatically reject');
 dcDenied(fn()=>$service->review($admin,$id,1,'reject',' '),400,'Whitespace rejection reason is rejected');
 $service->review($admin,$id,1,'reject','Supporting reference did not establish a donation.');
 dcCheck(Workflow::hasHold((int)$outsider['id']),'Rejection alone retains the hold');
 $availability=$users->findById((int)$outsider['id'])['donor_availability'];
 dcDenied(fn()=>$service->releaseHold($admin,$id,1,'Insufficient certainty',false),400,'Release requires explicit no-donation conclusion');
 $service->releaseHold($admin,$id,1,'Verified with both participants that no donation occurred.',true);
 dcCheck(Workflow::hasHold((int)$outsider['id']),'Releasing one claim leaves another claim hold active');
 dcCheck($service->releaseHold($admin,$id,1,'Repeat request',true)['changed']===false,'Repeated hold release is idempotent');
 dcCheck($users->findById((int)$outsider['id'])['donor_availability']===$availability,'Hold release never changes availability');
 $service->acknowledge($owner,$id2,1,'not_sure',null);dcCheck($reports->findById($id2)['workflow_state']==='needs_review','Uncertainty goes to review');
 $service->review($admin,$id2,1,'clarify','Please correct the donation date and reference.');
 $originalSubmitted=$reports->findById($id2)['reported_at'];
 $service->revise($outsider,$id2,1,gmdate('Y-m-d\TH:i:s\Z',time()-1800),'Corrected private synthetic reference.');
 dcCheck($reports->findById($id2)['reported_at']===$originalSubmitted,'Correction does not restart original timers');
 dcDenied(fn()=>$service->acknowledge($owner,$id2,1,'received',null),409,'Old-version acknowledgment is rejected');
 $p->prepare('UPDATE users SET last_verified_donation_at=? WHERE id=?')->execute([AuthService::nowUtc(),$outsider['id']]);$anchor=$users->findById((int)$outsider['id'])['last_verified_donation_at'];
 $service->acknowledge($owner,$id2,2,'received',null);dcCheck($users->findById((int)$outsider['id'])['last_verified_donation_at']===$anchor,'Late correction does not shorten established restriction');
 $history=$service->events($owner,$id2);dcCheck(count(array_unique(array_column($history,'content_version')))===2,'Private history preserves original and corrected versions');
 dcCheck(count(array_filter($history,fn($e)=>$e['event_type']==='requester_responded' && (int)$e['actor_reference']===(int)$owner['id']))===2,'Acknowledgments retain actor and exact version references');
 $selfRequest=dcRequest((int)$admin['id']);$selfMatch=dcMatch($selfRequest,(int)$a['id']);$selfReport=dcSubmit($a,$selfMatch);
 dcDenied(fn()=>$service->review($admin,$selfReport,1,'confirm','Self review is forbidden.'),403,'Requester administrator cannot review their own request');
 $r=dcRequest((int)$owner['id']);$m=dcMatch($r,(int)$b['id']);$legacy=$reports->insert($m,(int)$b['id'],null,AuthService::nowUtc());
 Storage::startHold($legacy,(int)$b['id'],AuthService::nowUtc());
 dcDenied(fn()=>$service->review($admin,$legacy,1,'confirm','Legacy report review.'),409,'Legacy missing occurrence cannot receive invented credit');
 $r=dcRequest((int)$owner['id']);$m=dcMatch($r,(int)$b['id']);$timer=dcSubmit($b,$m);
 $p->prepare('UPDATE donation_reports SET reported_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 49 HOUR),occurred_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 50 HOUR) WHERE id=?')->execute([$timer]);
 $due=DonationFollowupService::process(AuthService::nowUtc(),200,[$timer]);dcCheck($due===['reminded'=>1,'escalated'=>1],'Overdue silence generates one reminder and escalation');
 dcCheck($reports->findById($timer)['status']==='PENDING','Silence never decides the report');
 dcCheck(DonationFollowupService::process(AuthService::nowUtc(),200,[$timer])===['reminded'=>0,'escalated'=>0],'Follow-up retry has no repeated actions');
 $s=$p->prepare("SELECT COUNT(*) FROM email_outbox e JOIN notifications n ON n.id=e.notification_id JOIN donation_reports d ON n.related_type='donation_report' AND n.related_id=d.id JOIN users u ON u.id=d.donor_id WHERE u.email LIKE ?");$s->execute(["donation-$nonce-%@example.test"]);
 dcCheck((int)$s->fetchColumn()===0,'Donation workflow does not enqueue email');
 echo "$checks fixed-target donation database checks passed; fixtures rolled back; SMTP false.\n";
} finally { $p->rollBack(); }
