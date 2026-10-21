<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Laravel\Rules;

use Closure;
use Egbavou\BeninBusinessId\Ifu;
use Egbavou\BeninBusinessId\InvalidIdentifierException;
use Illuminate\Contracts\Validation\ValidationRule;

final class IfuRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('benin-business-id::validation.ifu.invalid_characters')->translate();

            return;
        }

        try {
            Ifu::from($value);
        } catch (InvalidIdentifierException $exception) {
            $fail('benin-business-id::validation.ifu.'.$exception->reason->value)->translate();

            return;
        }
    }
}
