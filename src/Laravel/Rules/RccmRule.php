<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Laravel\Rules;

use Closure;
use Egbavou\BeninBusinessId\InvalidIdentifierException;
use Egbavou\BeninBusinessId\Rccm;
use Illuminate\Contracts\Validation\ValidationRule;

final class RccmRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('benin-business-id::validation.rccm.invalid_characters')->translate();

            return;
        }

        try {
            Rccm::from($value);
        } catch (InvalidIdentifierException $exception) {
            $fail('benin-business-id::validation.rccm.'.$exception->reason->value)->translate();

            return;
        }
    }
}
