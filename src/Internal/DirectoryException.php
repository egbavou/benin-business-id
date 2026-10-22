<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Internal;

use Egbavou\BeninBusinessId\UnavailableReason;
use RuntimeException;
use Throwable;

/** @internal */
final class DirectoryException extends RuntimeException
{
    public function __construct(public readonly UnavailableReason $reason, ?Throwable $previous = null, ?string $message = null)
    {
        parent::__construct($message ?? match ($reason) {
            UnavailableReason::Transport => 'The APIEx request failed before a response was received.',
            UnavailableReason::HttpStatus => 'APIEx returned an unexpected HTTP status.',
            UnavailableReason::InvalidJson => 'APIEx returned invalid JSON.',
            default => 'APIEx returned an unexpected response schema.',
        }, 0, $previous);
    }
}
