<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Utils\Request;
use BloodMatch\Utils\Validator;
$alias='markseejr+requester2@gmail.com';$base='markseejr@gmail.com';
$normalized=strtolower((string)Request::str('email',['email'=>' MarkSeeJr+Requester2@gmail.com ']));
$validator=new Validator();$validator->email('email',$normalized);
if ($validator->fails() || $normalized!==$alias || $normalized===$base) { throw new LogicException('Alias validation/preservation failed.'); }
echo 'PASS backend email validation retains +requester2; distinct from base address. No DB, registration or email.'.PHP_EOL;
