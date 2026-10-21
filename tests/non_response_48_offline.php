<?php
declare(strict_types=1);
// Regression for retirement of the former 48-hour rule. No database/SMTP.
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Services\{NonResponseStandbyService as Standby,DonorEligibilityService as Eligibility,BloodCompatibilityService,SystemSettingsService};
(new ReflectionProperty(BloodCompatibilityService::class,'cache'))->setValue(null,['O+'=>['O+','O-']]);
(new ReflectionProperty(SystemSettingsService::class,'cache'))->setValue(null,['standby_hours'=>42,'cooldown_days'=>90]);
$checks=0;
function checkRetired(bool $ok,string $label): void { global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n"; }
$now='2026-10-21 12:00:00';
$donor=['id'=>1,'role'=>'member','account_status'=>'active','verification_status'=>'verified','email_verified_at'=>'2026-01-01 00:00:00',
 'donor_enrolled_at'=>'2026-01-01 00:00:00','donor_availability'=>'available','date_of_birth'=>'1990-01-01','has_parental_consent'=>false,
 'pending_report_hold'=>false,'non_response_standby'=>'2026-10-19 00:00:00','last_verified_donation_at'=>null,'blood_type'=>'O+'];
$request=['id'=>2,'requester_id'=>3,'status'=>'OPEN','needed_datetime'=>'2026-10-22 12:00:00','required_blood_type'=>'O+'];
checkRetired(Eligibility::evaluateForRequest($donor,$request,$now)['eligible'],'Legacy non-response hold does not block eligible donor');
foreach(['pending_report_hold'=>true,'account_status'=>'deactivated','verification_status'=>'pending','donor_availability'=>'unavailable','blood_type'=>'AB+','last_verified_donation_at'=>'2026-10-19 12:00:00'] as $key=>$value) {
 checkRetired(!Eligibility::evaluateForRequest(array_replace($donor,[$key=>$value]),$request,$now)['eligible'],'Safety prerequisite preserved: '.$key);
}
checkRetired(Eligibility::evaluateWindows('2026-10-18 23:37:00',$now)['which']==='cooldown','Recorded donation still starts cooldown');
checkRetired(Standby::state(1)['standby_started_at']===null,'Legacy state is neutral without database access');
Standby::opportunity(2,1,1,10);Standby::resolve(2,1,'answered');
checkRetired(Standby::processDue()===0 && Standby::processDue([1])===0,'Older scheduled callers cannot create standby');
checkRetired(!Standby::acknowledge(1) && !Standby::ready(),'Retired acknowledgment does not mutate records or send notifications');
echo "$checks retirement regression checks passed; no database, worker, SMTP or application data used.\n";
