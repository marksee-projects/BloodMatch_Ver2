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
echo "PASS $passed offline membership experiment scope checks; no DB or SMTP.\n";
