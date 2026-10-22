<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Laravel\Rules;

use Closure;
use Egbavou\BeninBusinessId\Ifu;
use Egbavou\BeninBusinessId\InvalidIdentifierException;
use Egbavou\BeninBusinessId\Rccm;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/** @internal */
abstract class IdentifierRule implements ValidationRule
{
    /** @return class-string<Ifu>|class-string<Rccm> */
    abstract protected function identifierClass(): string;

    final public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $this->fail('invalid_characters', $fail);

            return;
        }

        try {
            $identifier = $this->identifierClass()::from($value);
        } catch (InvalidIdentifierException $exception) {
            $this->fail($exception->reason->value, $fail);

            return;
        }

        $this->validateIdentifier($identifier, $fail);
    }

    /** @param Closure(string, ?string=): PotentiallyTranslatedString $fail */
    protected function validateIdentifier(Ifu|Rccm $identifier, Closure $fail): void {}

    /** @param Closure(string, ?string=): PotentiallyTranslatedString $fail */
    protected function fail(string $key, Closure $fail): void
    {
        $id = $this->identifierClass() === Ifu::class ? 'ifu' : 'rccm';
        $fail('benin-business-id::validation.'.$id.'.'.$key)->translate();
    }
}
