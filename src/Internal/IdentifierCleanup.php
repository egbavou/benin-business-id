<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Internal;

/** @internal */
final class IdentifierCleanup
{
    public const PATTERN = '/[\p{Z}\x{0009}-\x{000D}\x{0085}.\-]+/u';
}
