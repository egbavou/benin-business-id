<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId;

use InvalidArgumentException;

final class InvalidIdentifierException extends InvalidArgumentException
{
    public function __construct(public readonly FailureReason $reason, string $identifier)
    {
        parent::__construct("Invalid {$identifier}: {$reason->value}.");
    }
}
