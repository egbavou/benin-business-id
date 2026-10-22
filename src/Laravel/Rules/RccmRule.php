<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Laravel\Rules;

use Egbavou\BeninBusinessId\Rccm;

final class RccmRule extends IdentifierRule
{
    /** @return class-string<Rccm> */
    protected function identifierClass(): string
    {
        return Rccm::class;
    }
}
