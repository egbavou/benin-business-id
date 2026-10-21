<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Laravel;

use Egbavou\BeninBusinessId\IfuChecker;
use Egbavou\BeninBusinessId\RccmChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class BeninBusinessIdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/benin-business-id.php', 'benin-business-id');
        $this->app->singleton(IfuChecker::class);
        $this->app->singleton(RccmChecker::class, function (Application $app): RccmChecker {
            $config = $app->make(Repository::class);
            $timeout = $config->get('benin-business-id.rccm.apiex.timeout');
            $companiesUrl = $config->get('benin-business-id.rccm.apiex.companies_url');
            $establishmentsUrl = $config->get('benin-business-id.rccm.apiex.establishments_url');

            return new RccmChecker(
                new Client(['timeout' => is_numeric($timeout) && (float) $timeout > 0 ? (float) $timeout : 10.0]),
                new HttpFactory,
                enabled: filter_var($config->get('benin-business-id.rccm.apiex.enabled'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false,
                companiesUrl: is_string($companiesUrl) && $companiesUrl !== '' ? $companiesUrl : RccmChecker::DEFAULT_COMPANIES_URL,
                establishmentsUrl: is_string($establishmentsUrl) && $establishmentsUrl !== '' ? $establishmentsUrl : RccmChecker::DEFAULT_ESTABLISHMENTS_URL,
            );
        });
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'benin-business-id');
        $this->publishes([
            __DIR__.'/../../config/benin-business-id.php' => $this->app->configPath('benin-business-id.php'),
        ], 'benin-business-id-config');
        $this->publishes([
            __DIR__.'/../../lang' => $this->app->langPath('vendor/benin-business-id'),
        ], 'benin-business-id-lang');
    }
}
