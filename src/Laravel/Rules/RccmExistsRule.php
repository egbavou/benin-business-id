<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Laravel\Rules;

use Egbavou\BeninBusinessId\Rccm;

final class RccmExistsRule extends ExistsRule
{
    /** @return class-string<Rccm> */
    protected function identifierClass(): string
    {
        return Rccm::class;
    }
}
