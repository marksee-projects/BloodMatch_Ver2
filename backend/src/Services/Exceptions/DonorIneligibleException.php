<?php

declare(strict_types=1);

namespace BloodMatch\Services\Exceptions;

use RuntimeException;

final class DonorIneligibleException extends RuntimeException
{
    public function __construct(private array $eligibility)
    {
        parent::__construct((string) $eligibility['reason'], 403);
    }

    public function eligibility(): array
    {
        return $this->eligibility;
    }
}
