<?php

declare(strict_types=1);

arch('keeps the core independent of Illuminate', function (): void {
    expect('Egbavou\\BeninBusinessId')
        ->not->toUse('Illuminate')
        ->ignoring('Egbavou\\BeninBusinessId\\Laravel');
});
