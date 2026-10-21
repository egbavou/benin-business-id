<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId;

use DateTimeImmutable;

final readonly class RccmCheckResult
{
    /** @internal Results are created by the checker. */
    public function __construct(
        public CheckStatus $status,
        public DateTimeImmutable $checkedAt,
        public string $input,
        public ?Rccm $rccm,
        public ?string $name = null,
        public ?string $tradeName = null,
        public ?DateTimeImmutable $creationDate = null,
        public ?string $municipality = null,
        public ?string $district = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $mainActivity = null,
        public ?string $formerNumber = null,
        public ?RccmRegister $register = null,
        public ?UnavailableReason $unavailableReason = null,
    ) {}
}
