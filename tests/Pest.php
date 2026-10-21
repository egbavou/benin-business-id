<?php

declare(strict_types=1);

use Egbavou\BeninBusinessId\Tests\Laravel\TestCase;

uses()->group('laravel')->in('Laravel');

// The core suite can be loaded without Testbench or any Illuminate packages.
if (class_exists(Orchestra\Testbench\TestCase::class)) {
    uses(TestCase::class)->in('Laravel');
}
