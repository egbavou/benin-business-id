<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Laravel\Rules;

use Egbavou\BeninBusinessId\Ifu;

final class IfuRule extends IdentifierRule
{
    /** @return class-string<Ifu> */
    protected function identifierClass(): string
    {
        return Ifu::class;
    }
}
