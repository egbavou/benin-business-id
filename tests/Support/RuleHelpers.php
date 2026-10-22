<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Tests\Support;

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
