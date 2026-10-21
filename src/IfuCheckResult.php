<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId;

use DateTimeImmutable;

final readonly class IfuCheckResult
{
    /** @internal Results are created by the checker. */
    public function __construct(
        public CheckStatus $status,
        public DateTimeImmutable $checkedAt,
        public string $input,
        public ?Ifu $ifu,
        public ?UnavailableReason $unavailableReason = null,
    ) {}
}
