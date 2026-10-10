<?php
declare(strict_types=1);
namespace BloodMatch\Services\Exceptions;

final class EmailEnqueueException extends \RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Email notification could not be queued. No notification was committed for this event. Please try again.', 503, $previous);
    }
}
