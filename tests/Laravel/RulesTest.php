<?php

declare(strict_types=1);

use Egbavou\BeninBusinessId\FailureReason;
use Egbavou\BeninBusinessId\IfuChecker;
use Egbavou\BeninBusinessId\Laravel\Rules\IfuExistsRule;
use Egbavou\BeninBusinessId\Laravel\Rules\IfuRule;
use Egbavou\BeninBusinessId\Laravel\Rules\RccmExistsRule;
use Egbavou\BeninBusinessId\Laravel\Rules\RccmRule;
use Egbavou\BeninBusinessId\RccmChecker;
use Egbavou\BeninBusinessId\Tests\Support\FakeHttpClient;
use Egbavou\BeninBusinessId\Tests\Support\IdentifierCases;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Assert;

use function Egbavou\BeninBusinessId\Tests\Support\translatedMessage;
use function Egbavou\BeninBusinessId\Tests\Support\validateIdentifier;

require_once __DIR__.'/../Support/RuleHelpers.php';

it('translates every IFU format failure in both languages', function (string $input, FailureReason $reason, string $locale, ValidationRule $rule): void {
    $validator = validateIdentifier($input, $rule, $locale);

    // Laravel skips non-implicit rules on an empty string.
    if (trim($input) === '') {
        Assert::assertFalse($validator->fails());
    } else {
        Assert::assertTrue($validator->fails());
        Assert::assertSame(translatedMessage('ifu', $reason->value, $locale), $validator->errors()->first('identifier'));
    }
})->with(IdentifierCases::invalidIfu())->with(['en', 'fr'])->with([new IfuRule, new IfuExistsRule]);

it('translates every RCCM format failure in both languages', function (string $input, FailureReason $reason, string $locale, ValidationRule $rule): void {
    $validator = validateIdentifier($input, $rule, $locale);

    if (trim($input) === '') {
        Assert::assertFalse($validator->fails());
    } else {
        Assert::assertTrue($validator->fails());
        Assert::assertSame(translatedMessage('rccm', $reason->value, $locale), $validator->errors()->first('identifier'));
    }
})->with(IdentifierCases::invalidRccm())->with(['en', 'fr'])->with([new RccmRule, new RccmExistsRule]);

it('rejects non-string values with the invalid characters message', function (mixed $value, string $locale, string $id, ValidationRule $rule): void {
    $validator = validateIdentifier($value, $rule, $locale);
    Assert::assertTrue($validator->fails());
    Assert::assertSame(translatedMessage($id, 'invalid_characters', $locale), $validator->errors()->first('identifier'));
})->with([[123], [true], [false], [null], [[123]], [new stdClass]])
    ->with(['en', 'fr'])
    ->with([
        ['ifu', new IfuRule],
        ['ifu', new IfuExistsRule],
        ['rccm', new RccmRule],
        ['rccm', new RccmExistsRule],
    ]);

it('validates formats without resolving a network checker', function (string $input, ValidationRule $rule): void {
    foreach ([IfuChecker::class, RccmChecker::class] as $checker) {
        App::bind($checker, function (): never {
            throw new LogicException('Format validation must not resolve a checker.');
        });
    }

    $validator = validateIdentifier($input, $rule);
    Assert::assertFalse($validator->fails());
})->with([
    ['RB/COT/18 B 22272', new RccmRule],
    ['3202444139959', new IfuRule],
]);

it('accepts unavailable IFU checks by default and rejects them in strict mode', function (string $locale, bool $strict): void {
    $rule = new IfuExistsRule;
    if ($strict) {
        $rule->failWhenUnavailable();
    }

    $validator = validateIdentifier('3202444139959', $rule, $locale);
    Assert::assertSame($strict, $validator->fails());
    if ($strict) {
        Assert::assertSame(translatedMessage('ifu', 'unavailable', $locale), $validator->errors()->first('identifier'));
    }
    Assert::assertSame(app(IfuChecker::class), app(IfuChecker::class));
})->with(['en', 'fr'])->with([false, true]);

it('uses the dedicated disabled RCCM verification message in strict mode', function (string $locale, bool $strict): void {
    $rule = new RccmExistsRule;
    if ($strict) {
        $rule->failWhenUnavailable();
    }

    $validator = validateIdentifier('RB/COT/18 B 22272', $rule, $locale);
    Assert::assertSame($strict, $validator->fails());
    if ($strict) {
        Assert::assertSame(translatedMessage('rccm', 'verification_disabled', $locale), $validator->errors()->first('identifier'));
    }
})->with(['en', 'fr'])->with([false, true]);

it('uses the unavailable message for enabled checks that fail technically', function (string $locale, bool $strict): void {
    $client = new FakeHttpClient([new Response(500)]);
    App::instance(RccmChecker::class, new RccmChecker($client, new HttpFactory, enabled: true));
    $rule = new RccmExistsRule;
    if ($strict) {
        $rule->failWhenUnavailable();
    }

    $validator = validateIdentifier('RB/COT/18 B 22272', $rule, $locale);
    Assert::assertSame($strict, $validator->fails());
    if ($strict) {
        Assert::assertSame(translatedMessage('rccm', 'unavailable', $locale), $validator->errors()->first('identifier'));
    }
})->with(['en', 'fr'])->with([false, true]);

it('rejects numbers absent from both registers', function (string $locale): void {
    $client = new FakeHttpClient(array_fill(0, 4, FakeHttpClient::json()));
    App::instance(RccmChecker::class, new RccmChecker($client, new HttpFactory, enabled: true));
    $validator = validateIdentifier('RB/COT/18 B 22272', new RccmExistsRule, $locale);
    Assert::assertTrue($validator->fails());
    Assert::assertSame(translatedMessage('rccm', 'not_found', $locale), $validator->errors()->first('identifier'));
})->with(['en', 'fr']);

it('accepts a found number in strict mode', function (): void {
    $client = new FakeHttpClient([FakeHttpClient::json([['Numéro RC' => 'RB/COT/18 B 22272']])]);
    App::instance(RccmChecker::class, new RccmChecker($client, new HttpFactory, enabled: true));
    $validator = validateIdentifier('RB/COT/18 B 22272', (new RccmExistsRule)->failWhenUnavailable());
    Assert::assertFalse($validator->fails());
});
