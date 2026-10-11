<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Utils\ConfirmedDonationSummary;
use BloodMatch\Services\Exceptions\ValidationException;
$range=ConfirmedDonationSummary::range('2037-01-02','2037-01-03');
if ($range['start']!=='2037-01-02 00:00:00' || $range['end_exclusive']!=='2037-01-04 00:00:00') throw new LogicException('UTC bounds wrong');
foreach ([['2037-02-30','2037-03-01'],['invalid','2037-01-03'],['2037-01-04','2037-01-03']] as [$from,$to]) {
    try { ConfirmedDonationSummary::range($from,$to); throw new LogicException('Invalid dates accepted'); }
    catch (ValidationException $expected) {}
}
$csv=ConfirmedDonationSummary::csv(['date_from'=>'2037-01-02','date_to'=>'2037-01-03','chapter_id'=>null,'total_confirmed_reports'=>2,
    'daily_confirmations'=>[['confirmation_date_utc'=>'2037-01-02','confirmed_reports'=>2,'donor_name'=>'PRIVATE NAME','email'=>'PRIVATE EMAIL']],
    'documents'=>['PRIVATE DOCUMENT']]);
foreach (['PRIVATE NAME','PRIVATE EMAIL','PRIVATE DOCUMENT'] as $private) if(str_contains($csv,$private)) throw new LogicException('CSV privacy failed');
if(!str_contains($csv,'TOTAL,2') || !str_contains($csv,'2037-01-02,2')) throw new LogicException('CSV counts wrong');
$hostile=ConfirmedDonationSummary::csv(['date_from'=>'=BAD','date_to'=>'2037-01-03','chapter_id'=>'+BAD','total_confirmed_reports'=>0,'daily_confirmations'=>[]]);
if(!str_contains($hostile,"'=BAD") || !str_contains($hostile,"'+BAD")) throw new LogicException('Formula escaping failed');
echo "PASS confirmed summary UTC bounds, invalid ranges, aggregate allowlist and formula escaping; no database/SMTP.\n";
