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
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use PHPUnit\Framework\Assert;

function validateIdentifier(mixed $value, ValidationRule $rule, string $locale = 'en'): Validator
{
    App::setLocale($locale);

    return ValidatorFacade::make(['identifier' => $value], ['identifier' => [$rule]]);
}

function translatedMessage(string $identifier, string $reason, string $locale): string
{
    $messages = require __DIR__.'/../../lang/'.$locale.'/validation.php';
    Assert::assertIsArray($messages);
    Assert::assertIsArray($messages[$identifier]);
    Assert::assertIsString($messages[$identifier][$reason]);

    return str_replace(':attribute', 'identifier', $messages[$identifier][$reason]);
}

it('translates every IFU format failure in both languages', function (string $input, FailureReason $reason): void {
    foreach (['en', 'fr'] as $locale) {
        foreach ([new IfuRule, new IfuExistsRule] as $rule) {
            $validator = validateIdentifier($input, $rule, $locale);
            // Laravel skips non-implicit rules on an empty string.
            if (trim($input) === '') {
                Assert::assertFalse(validateIdentifier($input, $rule, $locale)->fails());

                continue;
            }
            Assert::assertTrue($validator->fails());
            Assert::assertSame(translatedMessage('ifu', $reason->value, $locale), $validator->errors()->first('identifier'));
        }
    }
})->with(IdentifierCases::invalidIfu());

it('translates every RCCM format failure in both languages', function (string $input, FailureReason $reason): void {
    foreach (['en', 'fr'] as $locale) {
        foreach ([new RccmRule, new RccmExistsRule] as $rule) {
            if (trim($input) === '') {
                Assert::assertFalse(validateIdentifier($input, $rule, $locale)->fails());

                continue;
            }
            $validator = validateIdentifier($input, $rule, $locale);
            Assert::assertTrue($validator->fails());
            Assert::assertSame(translatedMessage('rccm', $reason->value, $locale), $validator->errors()->first('identifier'));
        }
    }
})->with(IdentifierCases::invalidRccm());

it('rejects non-string values with the invalid characters message', function (mixed $value): void {
    foreach (['en', 'fr'] as $locale) {
        foreach (['ifu' => [new IfuRule, new IfuExistsRule], 'rccm' => [new RccmRule, new RccmExistsRule]] as $id => $rules) {
            foreach ($rules as $rule) {
                $validator = validateIdentifier($value, $rule, $locale);
                Assert::assertTrue($validator->fails());
                Assert::assertSame(translatedMessage($id, 'invalid_characters', $locale), $validator->errors()->first('identifier'));
            }
        }
    }
})->with([[123], [true], [false], [null], [[123]], [new stdClass]]);

it('validates formats without resolving a network checker', function (): void {
    App::bind(RccmChecker::class, function (): never {
        throw new LogicException('Format validation must not resolve a checker.');
    });
    Assert::assertFalse(validateIdentifier('RB/COT/18 B 22272', new RccmRule)->fails());
    Assert::assertFalse(validateIdentifier('3202444139959', new IfuRule)->fails());
});

it('accepts unavailable IFU checks by default and rejects them in strict mode', function (): void {
    Assert::assertFalse(validateIdentifier('3202444139959', new IfuExistsRule)->fails());
    foreach (['en', 'fr'] as $locale) {
        $validator = validateIdentifier('3202444139959', (new IfuExistsRule)->failWhenUnavailable(), $locale);
        Assert::assertTrue($validator->fails());
        Assert::assertSame(translatedMessage('ifu', 'unavailable', $locale), $validator->errors()->first('identifier'));
    }
    Assert::assertSame(app(IfuChecker::class), app(IfuChecker::class));
});

it('uses the dedicated disabled RCCM verification message in strict mode', function (): void {
    Assert::assertFalse(validateIdentifier('RB/COT/18 B 22272', new RccmExistsRule)->fails());
    foreach (['en', 'fr'] as $locale) {
        $validator = validateIdentifier('RB/COT/18 B 22272', (new RccmExistsRule)->failWhenUnavailable(), $locale);
        Assert::assertTrue($validator->fails());
        Assert::assertSame(translatedMessage('rccm', 'verification_disabled', $locale), $validator->errors()->first('identifier'));
    }
});

it('uses the unavailable message for enabled checks that fail technically', function (): void {
    $client = new FakeHttpClient(array_fill(0, 3, new Response(500)));
    App::instance(RccmChecker::class, new RccmChecker($client, new HttpFactory, enabled: true));
    Assert::assertFalse(validateIdentifier('RB/COT/18 B 22272', new RccmExistsRule)->fails());
    foreach (['en', 'fr'] as $locale) {
        $validator = validateIdentifier('RB/COT/18 B 22272', (new RccmExistsRule)->failWhenUnavailable(), $locale);
        Assert::assertTrue($validator->fails());
        Assert::assertSame(translatedMessage('rccm', 'unavailable', $locale), $validator->errors()->first('identifier'));
    }
});

it('rejects numbers absent from both registers', function (): void {
    $client = new FakeHttpClient(array_fill(0, 8, FakeHttpClient::json()));
    App::instance(RccmChecker::class, new RccmChecker($client, new HttpFactory, enabled: true));
    foreach (['en', 'fr'] as $locale) {
        $validator = validateIdentifier('RB/COT/18 B 22272', new RccmExistsRule, $locale);
        Assert::assertTrue($validator->fails());
        Assert::assertSame(translatedMessage('rccm', 'not_found', $locale), $validator->errors()->first('identifier'));
    }
});

it('accepts a found number in strict mode', function (): void {
    $client = new FakeHttpClient([FakeHttpClient::json([['Numéro RC' => 'RB/COT/18 B 22272']])]);
    App::instance(RccmChecker::class, new RccmChecker($client, new HttpFactory, enabled: true));
    Assert::assertFalse(validateIdentifier('RB/COT/18 B 22272', (new RccmExistsRule)->failWhenUnavailable())->fails());
});
