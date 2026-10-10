<?php
declare(strict_types=1);
namespace BloodMatch\Services;
/** Fake-only facade: false explicitly simulates proven non-acceptance; exceptions remain uncertain. */
final class TestEmailWorker
{
    public function __construct(private \Closure $fakeTransport) {}
    public function run(array $ids, bool $configured=true, int $limit=20): array
    {
        $fake=function(array $metadata): mixed {
            $result=($this->fakeTransport)($metadata);
            return $result===false ? ['outcome'=>'rejected'] : $result;
        };
        return (new EmailWorker($fake,'bloodmatch_test',3306,'bm-test-email-worker',true))->run($ids,$configured,$limit);
    }
}
