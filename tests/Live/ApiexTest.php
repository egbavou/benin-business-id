<?php

declare(strict_types=1);

use Egbavou\BeninBusinessId\CheckStatus;
use Egbavou\BeninBusinessId\RccmChecker;
use Egbavou\BeninBusinessId\RccmRegister;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Assert;

beforeEach(function (): void {
    if (getenv('BENIN_BUSINESS_ID_LIVE') !== '1') {
        Assert::markTestSkipped('Set BENIN_BUSINESS_ID_LIVE=1 to allow live APIEx requests.');
    }
});

it('finds a public company in the live APIEx directory', function (string $number): void {
    $checker = new RccmChecker(new Client(['timeout' => 10]), new HttpFactory, enabled: true);
    $result = $checker->check($number);
    Assert::assertSame(CheckStatus::Found, $result->status);
    Assert::assertNull($result->unavailableReason);
    if ($number === 'RB/COT/18 B 22272') {
        Assert::assertSame(RccmRegister::Companies, $result->register);
        Assert::assertNotNull($result->name);
        Assert::assertNotNull($result->creationDate);
    }
})->with(['RB/COT/18 B 22272', 'RB/COT/07 B 2058'])
    ->group('live');

it('returns not found for an absent well-formed number in the live APIEx directory', function (): void {
    $checker = new RccmChecker(new Client(['timeout' => 10]), new HttpFactory, enabled: true);
    $result = $checker->check('RB/COT/05 B 0041');
    Assert::assertSame(CheckStatus::NotFound, $result->status);
    Assert::assertNull($result->unavailableReason);
})->group('live');
