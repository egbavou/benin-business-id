<?php

declare(strict_types=1);

use Egbavou\BeninBusinessId\FailureReason;
use Egbavou\BeninBusinessId\InvalidIdentifierException;
use Egbavou\BeninBusinessId\Rccm;
use Egbavou\BeninBusinessId\Tests\Support\IdentifierCases;
use PHPUnit\Framework\Assert;

it('normalizes valid RCCMs and preserves year spelling and leading zeros', function (string $input, string $value, int $year): void {
    $rccm = Rccm::from($input);
    Assert::assertTrue(Rccm::isValid($input));
    Assert::assertEquals($rccm, Rccm::tryFrom($input));
    Assert::assertSame($value, $rccm->value);
    Assert::assertSame($value, $rccm->format());
    Assert::assertSame($value, (string) $rccm);
    Assert::assertSame($year, $rccm->year);
    Assert::assertSame(explode('/', $value)[1], $rccm->registryCode);
    Assert::assertSame(explode(' ', $value)[1], $rccm->category);
    Assert::assertSame(explode(' ', $value)[2], $rccm->sequenceNumber);
})->with(IdentifierCases::validRccm());

it('rejects invalid RCCMs in the specified check order', function (string $input, FailureReason $reason): void {
    Assert::assertFalse(Rccm::isValid($input));
    Assert::assertNull(Rccm::tryFrom($input));
    try {
        Rccm::from($input);
        Assert::fail('Expected an invalid identifier exception.');
    } catch (InvalidIdentifierException $exception) {
        Assert::assertSame($reason, $exception->reason);
        Assert::assertSame('Invalid RCCM: '.$reason->value.'.', $exception->getMessage());
    }
})->with(IdentifierCases::invalidRccm());

it('accepts all active registries and all former registries through 2020', function (): void {
    foreach (['COT', 'ABC', 'PNO', 'PKO', 'ABY', 'LKS', 'NAT', 'DGO', 'KDI', 'APL', 'SVL'] as $registry) {
        Assert::assertTrue(Rccm::isValid('RB/'.$registry.'/'.date('y').' B 0'));
    }
    foreach (['PN', 'PK', 'ODH', 'POB', 'ALD'] as $registry) {
        Assert::assertTrue(Rccm::isValid('RB/'.$registry.'/2020 B 000000'));
        Assert::assertFalse(Rccm::isValid('RB/'.$registry.'/21 B 1'));
    }
});

it('applies the dynamic century pivot and the four digit year boundaries', function (): void {
    $current = (int) date('Y');
    $next = ($current + 1) % 100;
    Assert::assertSame($current, Rccm::from('RB/COT/'.date('y').' B 1')->year);
    $previousCentury = 1900 + $next;
    $input = 'RB/COT/'.sprintf('%02d', $next).' B 1';
    if ($previousCentury < 1990) {
        Assert::assertNull(Rccm::tryFrom($input));
    } else {
        Assert::assertSame($previousCentury, Rccm::from($input)->year);
    }
    Assert::assertSame(1990, Rccm::from('RB/COT/1990 B 1')->year);
    Assert::assertSame(2020, Rccm::from('RB/COT/2020 B 1')->year);
    Assert::assertNull(Rccm::tryFrom('RB/COT/1989 B 1'));
    Assert::assertNull(Rccm::tryFrom('RB/COT/'.$current.' B 1'));
});
