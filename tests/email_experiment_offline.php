<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Services\EmailExperimentPolicy;
$review=['job_id'=>1,'notification_type'=>'verification.decision','priority'=>'normal','mail_class'=>'account','claimable_now'=>true,'policy_block'=>null];
EmailExperimentPolicy::assertMembershipScope(false,false,1,true,$review);$passed=1;
$cases=[[true,false,1,true,$review],[false,true,1,true,$review],[false,false,1,false,$review],[false,false,2,true,$review],
    [false,false,1,true,array_replace($review,['notification_type'=>'match.new','priority'=>'emergency','mail_class'=>'emergency'])],
    [false,false,1,true,array_replace($review,['policy_block'=>'superseded'])],
    [false,false,1,true,array_replace($review,['claimable_now'=>false])],
    [false,false,1,true,array_replace($review,['mail_class'=>'normal'])]];
foreach ($cases as $case) {
    $blocked=false;try { EmailExperimentPolicy::assertMembershipScope(...$case); } catch (LogicException $e) { $blocked=true; }
    if (!$blocked) { throw new RuntimeException('Unapproved experiment scope accepted.'); }$passed++;
}
$first=['job_id'=>10,'notification_type'=>'match.new','priority'=>'emergency','mail_class'=>'emergency','claimable_now'=>true,'policy_block'=>null,
    'status'=>'pending','attempts'=>0,'request_id'=>50,'requester_id'=>137,'recipient_user_id'=>64,'created_at'=>'2026-10-10 10:00:00.000000','next_attempt_at'=>'2026-10-10 10:00:00.000000'];
$second=array_replace($first,['job_id'=>11,'request_id'=>51,'requester_id'=>200,'created_at'=>'2026-10-10 10:02:00.000000','next_attempt_at'=>'2026-10-10 10:02:00.000000']);
$now='2026-10-10 10:05:00.000000';
EmailExperimentPolicy::assertEmergencyGroupingScope(false,false,[10,11],true,[$first,$second],$now);$passed++;
$groups=[[true,false,[10,11],true,[$first,$second],$now],[false,true,[10,11],true,[$first,$second],$now],
    [false,false,[10,11],false,[$first,$second],$now],[false,false,[10,10],true,[$first,$second],$now],
    [false,false,[10,11],true,[$first,array_replace($second,['requester_id'=>137])],$now],
    [false,false,[10,11],true,[$first,array_replace($second,['recipient_user_id'=>65])],$now],
    [false,false,[10,11],true,[$first,array_replace($second,['policy_block'=>'stale_generation'])],$now],
    [false,false,[10,11],true,[$first,array_replace($second,['attempts'=>1])],$now],
    [false,false,[10,11],true,[$first,$second],'2026-10-10 10:04:59.999999'],
    [false,false,[10,11],true,[$first,array_replace($second,['created_at'=>'2026-10-10 10:05:00.000000'])],$now]];
foreach ($groups as $group) {
    $blocked=false;try { EmailExperimentPolicy::assertEmergencyGroupingScope(...$group); } catch (LogicException $e) { $blocked=true; }
    if (!$blocked) { throw new RuntimeException('Unapproved Emergency grouping scope accepted.'); }$passed++;
}
echo "PASS $passed offline membership/Emergency experiment scope checks; no DB or SMTP.\n";
