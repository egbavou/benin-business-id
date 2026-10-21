<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId;

use DateTimeImmutable;
use DateTimeZone;

/** No authorised IFU existence source is currently available. */
final class IfuChecker
{
    public function check(string|Ifu $ifu): IfuCheckResult
    {
        $input = $ifu instanceof Ifu ? $ifu->value : $ifu;
        $identifier = $ifu instanceof Ifu ? $ifu : Ifu::tryFrom($ifu);

        return new IfuCheckResult(
            $identifier === null ? CheckStatus::InvalidFormat : CheckStatus::Unavailable,
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
            $input,
            $identifier,
            unavailableReason: $identifier === null ? null : UnavailableReason::NoSource,
        );
    }
}
