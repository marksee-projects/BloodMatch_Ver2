<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Services\StaffRoleMigration;
use BloodMatch\Services\WorkflowLockService;
use BloodMatch\Services\RequestLifecycleService;
use BloodMatch\Services\DonorEligibilityService;
use BloodMatch\Services\ProfileViewPolicy;
use BloodMatch\Utils\AuditDateRange;
use BloodMatch\Services\CapabilityMatrix;
$checks=0;
function staffCheck(bool $ok,string $label): void { global $checks; if (!$ok) { throw new LogicException('FAIL '.$label); } $checks++; echo 'PASS '.$label.PHP_EOL; }
function staffDenied(callable $fn,string $label): void {
    try { $fn(); } catch (RuntimeException $e) { staffCheck($e->getCode()===403,$label); return; }
    throw new LogicException('FAIL '.$label);
}
$base=['id'=>1,'full_name'=>'Synthetic user','email'=>'fixture@example.test','role'=>'member','chapter_id'=>1,'account_status'=>'active','verification_status'=>'pending','updated_at'=>'2026-10-01 00:00:00','password_hash'=>'PRIVATE HASH','email_otp_hash'=>'PRIVATE OTP HASH'];
$rows=[$base,array_replace($base,['id'=>2,'role'=>'officer']),array_replace($base,['id'=>3,'role'=>'officer','chapter_id'=>null,'account_status'=>'deactivated']),array_replace($base,['id'=>4,'role'=>'admin'])];
$preview=StaffRoleMigration::preview($rows);
staffCheck($preview['affected_count']===2,'preview includes active and deactivated legacy officers only');
staffCheck($preview['affected_accounts'][1]['chapter_id']===null,'preview preserves missing chapter assignment');
staffCheck(!isset($preview['affected_accounts'][0]['updated_at']),'preview emits only approved impact fields');
staffCheck(!isset($preview['affected_accounts'][0]['password_hash'],$preview['affected_accounts'][0]['email_otp_hash']),'impact preview excludes credential/OTP hashes');
try { StaffRoleMigration::preview([array_replace($base,['role'=>''])]); throw new RuntimeException('Unexpected acceptance'); }
catch (LogicException) { staffCheck(true,'invalid stored role stops review before conversion'); }
$after=$rows;$after[1]['role']='admin';$after[2]['role']='admin';
StaffRoleMigration::assertPreserved($rows,$after);staffCheck(true,'only officer-to-admin conversion is permitted');
foreach (['chapter_id','verification_status','account_status','updated_at'] as $field) {
    $bad=$after;$bad[1][$field]='changed';
    try { StaffRoleMigration::assertPreserved($rows,$bad); throw new RuntimeException('Unexpected acceptance'); }
    catch (LogicException) { staffCheck(true,'preservation rejects changed '.$field); }
}
$admin=array_replace($base,['role'=>'admin','chapter_id'=>null]);
$request=['requester_id'=>9,'request_chapter_id'=>2];
WorkflowLockService::assertReportReviewer($admin,$request,8);staffCheck(true,'canonical staff can review across chapters without an assignment');
staffDenied(fn()=>WorkflowLockService::assertReportReviewer($admin,$request,1),'self donation review remains forbidden');
staffDenied(fn()=>WorkflowLockService::assertReportReviewer(array_replace($admin,['account_status'=>'deactivated']),$request,8),'inactive staff cannot review');
foreach (['member','officer'] as $role) { staffDenied(fn()=>WorkflowLockService::assertReportReviewer(array_replace($admin,['role'=>$role]),$request,8),'noncanonical '.$role.' cannot use staff privileges'); }
staffCheck(RequestLifecycleService::cancellationMode($admin,$request)==='admin','global staff cancellation retains canonical audit identity');
staffDenied(fn()=>RequestLifecycleService::assertCanEdit($admin,$request),'request edits remain owner-only');
staffCheck((new ProfileViewPolicy())->relation($admin,array_replace($base,['id'=>8,'chapter_id'=>2]))==='admin','staff member-profile access spans chapters');
staffCheck((new ProfileViewPolicy())->relation($admin,array_replace($base,['id'=>8,'account_status'=>'deactivated']))===null,'inactive target profile remains private');
staffCheck(DonorEligibilityService::evaluateDonor($admin)['code']==='staff_account','staff donor response eligibility remains read-only');
staffCheck(!CapabilityMatrix::evaluate(array_replace($admin,['verification_status'=>'verified']))['appear_as_donor'],'staff capability does not falsely advertise donor eligibility');
staffDenied(fn()=>WorkflowLockService::assertDonorActor($admin),'staff cannot mutate legacy donor offers/reports');
WorkflowLockService::assertDonorActor($base);staffCheck(true,'active members retain donor-action access checks');
staffCheck(AuditDateRange::bound('2026-10-11',true)===[' < ?','2026-10-12 00:00:00'],'audit end date includes the entire UTC day');
staffCheck(AuditDateRange::bound('2026-12-31',true)===[' < ?','2027-01-01 00:00:00'],'audit end-date rollover preserves full-day semantics');
staffCheck(AuditDateRange::bound('2026-10-11 12:30:00',true)===[' <= ?','2026-10-11 12:30:00'],'explicit timestamp bounds retain existing semantics');
echo "PASS $checks offline staff-role/preservation/date checks; no database, SMTP or tasks.".PHP_EOL;
