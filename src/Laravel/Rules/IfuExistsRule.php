<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Laravel\Rules;

use Closure;
use Egbavou\BeninBusinessId\CheckStatus;
use Egbavou\BeninBusinessId\Ifu;
use Egbavou\BeninBusinessId\IfuChecker;
use Egbavou\BeninBusinessId\InvalidIdentifierException;
use Illuminate\Contracts\Validation\ValidationRule;

final class IfuExistsRule implements ValidationRule
{
    private bool $strict = false;

    public function failWhenUnavailable(): static
    {
        $this->strict = true;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('benin-business-id::validation.ifu.invalid_characters')->translate();

            return;
        }

        try {
            $identifier = Ifu::from($value);
        } catch (InvalidIdentifierException $exception) {
            $fail('benin-business-id::validation.ifu.'.$exception->reason->value)->translate();

            return;
        }

        $checker = app(IfuChecker::class);
        $status = $checker->check($identifier)->status;

        if ($status === CheckStatus::NotFound) {
            $fail('benin-business-id::validation.ifu.not_found')->translate();
        } elseif ($status === CheckStatus::Unavailable && $this->strict) {
            $fail('benin-business-id::validation.ifu.unavailable')->translate();
        }
    }
}
