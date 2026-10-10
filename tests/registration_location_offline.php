<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Services\LocationService;
use BloodMatch\Services\Exceptions\ValidationException;
$barangay = ['municipality_code'=>'030809000','barangay_code'=>'030809023'];
$municipality = ['municipality_code'=>'030809000','barangay_code'=>null];
$cases = [
    [$barangay,$barangay,true,true],
    [$barangay,[],true,true],
    [$municipality,['municipality_code'=>'030809000','barangay_code'=>null],true,true],
    [$municipality,['municipality_code'=>'030809000','barangay_code'=>''],true,true],
    [$barangay,['municipality_code'=>'030803000','barangay_code'=>'030809023'],true,false],
    [$barangay,['municipality_code'=>'030809000','barangay_code'=>'030803001'],true,false],
    [$barangay,['municipality_code'=>'030809000','barangay_code'=>null],true,false],
    [$barangay,['municipality_code'=>['030809000']],true,false],
    [$barangay,['barangay_code'=>['030809023']],true,false],
    [$barangay,$barangay,false,false],
];
foreach ($cases as [$row,$input,$active,$expected]) {
    try { LocationService::validateSelection($row,$input,$active); $valid=true; }
    catch (ValidationException $e) { $valid=false; }
    if ($valid!==$expected) { throw new LogicException('Location selection check failed.'); }
}
echo 'PASS 10 offline location selection checks; no database or mail.'.PHP_EOL;
