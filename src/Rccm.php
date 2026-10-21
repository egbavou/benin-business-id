<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId;

use Stringable;

final readonly class Rccm implements Stringable
{
    private const ACTIVE_REGISTRIES = ['COT', 'ABC', 'PNO', 'PKO', 'ABY', 'LKS', 'NAT', 'DGO', 'KDI', 'APL', 'SVL'];

    private const FORMER_REGISTRIES = ['PN', 'PK', 'ODH', 'POB', 'ALD'];

    private function __construct(
        public string $value,
        public string $registryCode,
        public int $year,
        public string $category,
        public string $sequenceNumber,
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
        $cleaned = preg_replace('/[\s\p{Zs}.\-]+/u', '', $input);
        $value = strtoupper($cleaned ?? '');

        if ($cleaned === null || preg_match('/[^A-Z0-9\/]/', $value) === 1) {
            throw new InvalidIdentifierException(FailureReason::InvalidCharacters, 'RCCM');
        }

        if ($value === '') {
            throw new InvalidIdentifierException(FailureReason::InvalidLength, 'RCCM');
        }

        if (preg_match('/^([A-Z]{2})\/?([A-Z]{2,3})\/?(\d{2}|\d{4})\/?([A-Z])\/?(\d+)$/D', $value, $parts) !== 1) {
            throw new InvalidIdentifierException(FailureReason::InvalidStructure, 'RCCM');
        }

        [, $country, $registry, $yearInput, $category, $sequence] = $parts;

        if (strlen($sequence) > 6) {
            throw new InvalidIdentifierException(FailureReason::InvalidLength, 'RCCM');
        }

        if ($country !== 'RB') {
            throw new InvalidIdentifierException(FailureReason::InvalidCountry, 'RCCM');
        }

        $formerRegistry = in_array($registry, self::FORMER_REGISTRIES, true);

        if (! $formerRegistry && ! in_array($registry, self::ACTIVE_REGISTRIES, true)) {
            throw new InvalidIdentifierException(FailureReason::UnknownRegistry, 'RCCM');
        }

        if (! in_array($category, ['A', 'B', 'C', 'D', 'E'], true)) {
            throw new InvalidIdentifierException(FailureReason::UnknownCategory, 'RCCM');
        }

        $currentYear = (int) date('Y');
        $year = (int) $yearInput;

        if (strlen($yearInput) === 2) {
            $year += $year > $currentYear % 100 ? 1900 : 2000;
        } elseif ($year < 1990 || $year > 2020) {
            throw new InvalidIdentifierException(FailureReason::InvalidYear, 'RCCM');
        }

        if ($year < 1990 || $year > $currentYear) {
            throw new InvalidIdentifierException(FailureReason::InvalidYear, 'RCCM');
        }

        if ($formerRegistry && $year > 2020) {
            throw new InvalidIdentifierException(FailureReason::UnknownRegistry, 'RCCM');
        }

        return new self("RB/{$registry}/{$yearInput} {$category} {$sequence}", $registry, $year, $category, $sequence);
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
