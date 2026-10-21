<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Internal;

use Egbavou\BeninBusinessId\UnavailableReason;
use RuntimeException;
use Throwable;

/** @internal */
final class DirectoryException extends RuntimeException
{
    public function __construct(public readonly UnavailableReason $reason, ?Throwable $previous = null)
    {
        parent::__construct('APIEx directory failure: '.$reason->value, 0, $previous);
    }
}
