<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Services\NonResponseStandbyService as Standby;
$through='2026-10-10 12:00:00.123456';
$cases=[['2026-10-10 12:00:00.123455',$through,true],[$through,$through,true],['2026-10-10 12:00:00.123457',$through,false],[$through,null,false]];
foreach ($cases as [$due,$watermark,$expected]) {
    if (Standby::consumed($due,$watermark)!==$expected) { throw new LogicException('Wrong due-at retirement boundary.'); }
}
echo "4 offline acknowledgment boundary checks passed; no database calls.\n";
