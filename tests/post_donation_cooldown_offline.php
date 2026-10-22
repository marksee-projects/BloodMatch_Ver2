<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Services\{DonorEligibilityService as Eligibility,SystemSettingsService};
(new ReflectionProperty(SystemSettingsService::class,'cache'))->setValue(null,['standby_hours'=>42,'cooldown_days'=>90]);
$anchor='2026-10-22 03:00:00';$n=0;
function checkCooldown(bool $ok,string $message):void{global $n;if(!$ok)throw new RuntimeException($message);$n++;}
foreach(['2026-10-22 03:00:00','2026-10-22 03:00:01','2026-10-23 20:59:59','2026-10-23 21:00:00','2027-01-20 02:59:59'] as $now){$w=Eligibility::evaluateWindows($anchor,$now);checkCooldown($w['blocked']&&$w['which']==='cooldown','Cooldown must apply without a standby phase at '.$now);checkCooldown($w['ends_at_utc']==='2027-01-20 03:00:00','Keep exact 90-day anchor');}
checkCooldown(!Eligibility::evaluateWindows($anchor,'2027-01-20 03:00:00')['blocked'],'Cooldown ends at exact 90-day boundary');
checkCooldown(!Eligibility::evaluateWindows(null,'2026-10-22 03:00:00')['blocked'],'No donation means no post-donation window');
echo "PASS $n cooldown-only boundary checks; legacy 42-hour settings cannot restore standby. No database or SMTP.\n";
