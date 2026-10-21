<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Laravel\Rules;

use Closure;
use Egbavou\BeninBusinessId\CheckStatus;
use Egbavou\BeninBusinessId\InvalidIdentifierException;
use Egbavou\BeninBusinessId\Rccm;
use Egbavou\BeninBusinessId\RccmChecker;
use Illuminate\Contracts\Validation\ValidationRule;

final class RccmExistsRule implements ValidationRule
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
            $fail('benin-business-id::validation.rccm.invalid_characters')->translate();

            return;
        }

        try {
            $identifier = Rccm::from($value);
        } catch (InvalidIdentifierException $exception) {
            $fail('benin-business-id::validation.rccm.'.$exception->reason->value)->translate();

            return;
        }

        $checker = app(RccmChecker::class);
        $status = $checker->check($identifier)->status;

        if ($status === CheckStatus::NotFound) {
            $fail('benin-business-id::validation.rccm.not_found')->translate();
        } elseif ($status === CheckStatus::Unavailable && $this->strict) {
            $key = $checker->isEnabled() ? 'unavailable' : 'verification_disabled';
            $fail('benin-business-id::validation.rccm.'.$key)->translate();
        }
    }
}
