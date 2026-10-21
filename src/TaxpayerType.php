<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId;

enum TaxpayerType
{
    case Individual;
    case Company;
    case PublicEntity;
    case InternationalOrganization;
    case Ngo;

    public static function fromDigit(int $digit): self
    {
        return match ($digit) {
            0, 1, 2 => self::Individual,
            3 => self::Company,
            4 => self::PublicEntity,
            5 => self::InternationalOrganization,
            6 => self::Ngo,
            default => throw new InvalidIdentifierException(FailureReason::UnknownTaxpayerType, 'IFU'),
        };
    }
}
