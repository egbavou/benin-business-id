<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Laravel\Rules;

use Closure;
use Egbavou\BeninBusinessId\CheckStatus;
use Egbavou\BeninBusinessId\Ifu;
use Egbavou\BeninBusinessId\IfuChecker;
use Egbavou\BeninBusinessId\Rccm;
use Egbavou\BeninBusinessId\RccmChecker;
use Illuminate\Container\Container;

/** @internal */
abstract class ExistsRule extends IdentifierRule
{
    private bool $strict = false;

    public function failWhenUnavailable(): static
    {
        $this->strict = true;

        return $this;
    }

    protected function validateIdentifier(Ifu|Rccm $identifier, Closure $fail): void
    {
        $container = Container::getInstance();
        $unavailableKey = 'unavailable';

        if ($identifier instanceof Ifu) {
            $status = $container->make(IfuChecker::class)->check($identifier)->status;
        } else {
            $checker = $container->make(RccmChecker::class);
            $status = $checker->check($identifier)->status;
            $unavailableKey = $checker->isEnabled() ? 'unavailable' : 'verification_disabled';
        }

        if ($status === CheckStatus::NotFound) {
            $this->fail('not_found', $fail);
        } elseif ($status === CheckStatus::Unavailable && $this->strict) {
            $this->fail($unavailableKey, $fail);
        }
    }
}
