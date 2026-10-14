<?php
declare(strict_types=1);
require dirname(__DIR__).'/backend/src/autoload.php';
use BloodMatch\Services\SessionIdentityPolicy;
$original=['password_hash'=>password_hash('OriginalExample123',PASSWORD_BCRYPT)];
$session=['password_fingerprint'=>SessionIdentityPolicy::fingerprint($original)];
$reset=['password_hash'=>password_hash('ResetExample456',PASSWORD_BCRYPT)];
foreach ([
    'login session matches'=>SessionIdentityPolicy::matches($original,$session),
    'password reset revokes old session'=>!SessionIdentityPolicy::matches($reset,$session),
    'legacy session requires sign-in'=>!SessionIdentityPolicy::matches($original,[]),
    'invalid fingerprint denied'=>!SessionIdentityPolicy::matches($original,['password_fingerprint'=>[]]),
    'new password session works'=>SessionIdentityPolicy::matches($reset,['password_fingerprint'=>SessionIdentityPolicy::fingerprint($reset)]),
] as $label=>$ok) { if (!$ok) throw new LogicException('FAIL '.$label); echo 'PASS '.$label.PHP_EOL; }
