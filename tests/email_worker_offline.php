<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Services\EmailWorkerPolicy as Policy;
$passed=0;
function check(bool $ok,string $label): void {
    global $passed; if(!$ok){throw new RuntimeException('FAIL '.$label);} $passed++; echo 'PASS '.$label.PHP_EOL;
}
check(Policy::mailClass('verification.decision','normal')==='account','verification priority normal, budget account');
check(Policy::mailClass('match.new','emergency')==='emergency','Emergency budget classification');
foreach(['account.status_changed','donation.confirmed','verification.resubmitted','match.responded','request.expired'] as $type){check(Policy::mailClass($type,'normal')===null,"$type excluded");}
check(Policy::mailClass('match.new','normal')===null && Policy::mailClass('verification.decision','emergency')===null,'invalid channel/priority combinations excluded');
$now='2026-10-10 12:00:00';
check(Policy::digestReadyAt($now)==='2026-10-10 12:05:00','five-minute Emergency grouping');
check(Policy::digestReadyAt('2026-10-10 23:58:00')==='2026-10-11 00:03:00','grouping crosses midnight in UTC');
check(Policy::digestReadyAt('2026-10-10 12:00:00.123456')==='2026-10-10 12:05:00.123456','grouping preserves microsecond boundary');
foreach([1=>60,2=>300,3=>900,4=>3600,5=>14400] as $attempt=>$seconds){$r=Policy::retry($attempt,$now);check($r['status']==='pending' && strtotime($r['next'].' UTC')-strtotime($now.' UTC')===$seconds,"retry delay $attempt");}
check(Policy::retry(6,$now)['status']==='failed','six attempts exhausted');
check(!Policy::expired($now,$now),'exact deadline second retained');
check(Policy::expired('2026-10-10 11:59:59',$now),'past expiry rejected');
check(!Policy::expired('2026-10-10 12:00:01',$now),'future expiry retained');
try { Policy::retry(0,$now); check(false,'invalid attempt'); } catch(LogicException $e){check(true,'invalid attempt rejected');}
echo "$passed offline worker checks passed\n";
