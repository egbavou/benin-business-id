<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId;

use Stringable;

final readonly class Ifu implements Stringable
{
    private function __construct(
        public string $value,
        public TaxpayerType $taxpayerType,
        public int $registrationYear,
    ) {}

    public static function isValid(string $input): bool
    {
        return self::tryFrom($input) !== null;
    }

    public static function tryFrom(string $input): ?self
    {
        try {
            return self::from($input);
        } catch (InvalidIdentifierException) {
            return null;
        }
    }

    public static function from(string $input): self
    {
        $value = preg_replace('/[\s\p{Zs}.\-]+/u', '', $input);

        if ($value === null || preg_match('/[^0-9]/', $value) === 1) {
            throw new InvalidIdentifierException(FailureReason::InvalidCharacters, 'IFU');
        }

        if (strlen($value) !== 13) {
            throw new InvalidIdentifierException(FailureReason::InvalidLength, 'IFU');
        }

        $type = TaxpayerType::fromDigit((int) $value[0]);
        $year = (int) substr($value, 1, 4);

        if ($year < 2006 || $year > (int) date('Y')) {
            throw new InvalidIdentifierException(FailureReason::InvalidYear, 'IFU');
        }

        return new self($value, $type, $year);
    }

    public function format(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
