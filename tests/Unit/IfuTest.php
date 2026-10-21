<?php

declare(strict_types=1);

use Egbavou\BeninBusinessId\FailureReason;
use Egbavou\BeninBusinessId\Ifu;
use Egbavou\BeninBusinessId\InvalidIdentifierException;
use Egbavou\BeninBusinessId\TaxpayerType;
use Egbavou\BeninBusinessId\Tests\Support\IdentifierCases;
use PHPUnit\Framework\Assert;

it('normalizes valid IFUs and exposes their components', function (string $input, string $value, TaxpayerType $type, int $year): void {
    $ifu = Ifu::from($input);
    Assert::assertTrue(Ifu::isValid($input));
    Assert::assertEquals($ifu, Ifu::tryFrom($input));
    Assert::assertSame($value, $ifu->value);
    Assert::assertSame($value, $ifu->format());
    Assert::assertSame($value, (string) $ifu);
    Assert::assertSame($type, $ifu->taxpayerType);
    Assert::assertSame($year, $ifu->registrationYear);
})->with(IdentifierCases::validIfu());

it('rejects invalid IFUs in the specified check order', function (string $input, FailureReason $reason): void {
    Assert::assertFalse(Ifu::isValid($input));
    Assert::assertNull(Ifu::tryFrom($input));
    try {
        Ifu::from($input);
        Assert::fail('Expected an invalid identifier exception.');
    } catch (InvalidIdentifierException $exception) {
        Assert::assertSame($reason, $exception->reason);
        Assert::assertSame('Invalid IFU: '.$reason->value.'.', $exception->getMessage());
    }
})->with(IdentifierCases::invalidIfu());

it('accepts the IFU year boundaries without a checksum or sequence restriction', function (): void {
    foreach ([2006, (int) date('Y')] as $year) {
        foreach (['00000000', '99999999'] as $tail) {
            Assert::assertTrue(Ifu::isValid('3'.$year.$tail));
        }
    }
});

it('rejects unsupported taxpayer digits', function (int $digit): void {
    try {
        TaxpayerType::fromDigit($digit);
        Assert::fail('Expected an invalid taxpayer type.');
    } catch (InvalidIdentifierException $exception) {
        Assert::assertSame(FailureReason::UnknownTaxpayerType, $exception->reason);
    }
})->with([[-1], [7], [8], [9], [10]]);
