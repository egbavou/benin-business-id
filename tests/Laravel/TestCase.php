<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Tests\Laravel;

use Egbavou\BeninBusinessId\Laravel\BeninBusinessIdServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @param  Application  $app
     * @return list<class-string<ServiceProvider>>
     */
    protected function getPackageProviders($app): array
    {
        return [BeninBusinessIdServiceProvider::class];
    }
}
