<?php

declare(strict_types=1);

arch('keeps the core independent of Illuminate and Guzzle', function (): void {
    expect('Egbavou\\BeninBusinessId')
        ->not->toUse(['Illuminate', 'GuzzleHttp'])
        ->ignoring('Egbavou\\BeninBusinessId\\Laravel');
});
