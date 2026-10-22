<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Internal;

/** @internal */
final class IdentifierCleanup
{
    public const PATTERN = '/[\s.\-]+/u';
}
