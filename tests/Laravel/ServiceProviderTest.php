<?php

declare(strict_types=1);

use Egbavou\BeninBusinessId\CheckStatus;
use Egbavou\BeninBusinessId\Internal\ApiexDirectory;
use Egbavou\BeninBusinessId\Laravel\BeninBusinessIdServiceProvider;
use Egbavou\BeninBusinessId\RccmChecker;
use GuzzleHttp\Client;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Assert;

it('merges defaults and publishes configuration and translations', function (): void {
    $config = app(Repository::class);
    Assert::assertFalse($config->get('benin-business-id.rccm.apiex.enabled'));
    Assert::assertSame(10, $config->get('benin-business-id.rccm.apiex.timeout'));
    Assert::assertSame(RccmChecker::DEFAULT_COMPANIES_URL, $config->get('benin-business-id.rccm.apiex.companies_url'));
    Assert::assertSame(RccmChecker::DEFAULT_ESTABLISHMENTS_URL, $config->get('benin-business-id.rccm.apiex.establishments_url'));
    $configuration = ServiceProvider::pathsToPublish(BeninBusinessIdServiceProvider::class, 'benin-business-id-config');
    $translations = ServiceProvider::pathsToPublish(BeninBusinessIdServiceProvider::class, 'benin-business-id-lang');
    Assert::assertCount(1, $configuration);
    Assert::assertCount(1, $translations);
    $configurationPath = array_values($configuration)[0];
    $translationPath = array_values($translations)[0];
    Assert::assertIsString($configurationPath);
    Assert::assertIsString($translationPath);
    Assert::assertStringEndsWith('config/benin-business-id.php', $configurationPath);
    Assert::assertStringEndsWith('vendor/benin-business-id', $translationPath);
    $checker = app(RccmChecker::class);
    Assert::assertSame($checker, app(RccmChecker::class));
    Assert::assertFalse($checker->isEnabled());
    Assert::assertSame(CheckStatus::Unavailable, $checker->check('RB/COT/18 B 22272')->status);
});

it('builds the checker with the configured switch URLs and timeout', function (): void {
    $config = app(Repository::class);
    $config->set('benin-business-id.rccm.apiex.enabled', true);
    $config->set('benin-business-id.rccm.apiex.timeout', 3);
    $config->set('benin-business-id.rccm.apiex.companies_url', 'https://example.test/companies');
    $config->set('benin-business-id.rccm.apiex.establishments_url', 'https://example.test/establishments');
    $checker = app(RccmChecker::class);
    Assert::assertTrue($checker->isEnabled());
    $directory = (new ReflectionProperty($checker, 'directory'))->getValue($checker);
    Assert::assertInstanceOf(ApiexDirectory::class, $directory);
    Assert::assertSame('https://example.test/companies', (new ReflectionProperty($directory, 'companiesUrl'))->getValue($directory));
    Assert::assertSame('https://example.test/establishments', (new ReflectionProperty($directory, 'establishmentsUrl'))->getValue($directory));
    $client = (new ReflectionProperty($directory, 'httpClient'))->getValue($directory);
    Assert::assertInstanceOf(Client::class, $client);
    Assert::assertSame(3.0, $client->getConfig('timeout'));
});

it('normalizes the configured enabled switch', function (mixed $enabled, bool $expected): void {
    app(Repository::class)->set('benin-business-id.rccm.apiex.enabled', $enabled);

    Assert::assertSame($expected, app(RccmChecker::class)->isEnabled());
})->with([
    ['1', true],
    ['true', true],
    [1, true],
    ['yes', true],
    ['on', true],
    [true, true],
    ['0', false],
    ['false', false],
    [0, false],
    [false, false],
    ['garbage', false],
    [null, false],
]);

it('falls back to the default URLs for empty or non-string configuration', function (mixed $url): void {
    $config = app(Repository::class);
    $config->set('benin-business-id.rccm.apiex.companies_url', $url);
    $config->set('benin-business-id.rccm.apiex.establishments_url', $url);

    $checker = app(RccmChecker::class);
    $directory = (new ReflectionProperty($checker, 'directory'))->getValue($checker);
    Assert::assertInstanceOf(ApiexDirectory::class, $directory);
    Assert::assertSame(RccmChecker::DEFAULT_COMPANIES_URL, (new ReflectionProperty($directory, 'companiesUrl'))->getValue($directory));
    Assert::assertSame(RccmChecker::DEFAULT_ESTABLISHMENTS_URL, (new ReflectionProperty($directory, 'establishmentsUrl'))->getValue($directory));
})->with([[null], [''], [false], [123]]);

it('normalizes the configured timeout', function (mixed $timeout, float $expected): void {
    app(Repository::class)->set('benin-business-id.rccm.apiex.timeout', $timeout);

    $checker = app(RccmChecker::class);
    $directory = (new ReflectionProperty($checker, 'directory'))->getValue($checker);
    Assert::assertInstanceOf(ApiexDirectory::class, $directory);
    $client = (new ReflectionProperty($directory, 'httpClient'))->getValue($directory);
    Assert::assertInstanceOf(Client::class, $client);
    Assert::assertSame($expected, $client->getConfig('timeout'));
})->with([
    [3, 3.0],
    [2.5, 2.5],
    ['2.5', 2.5],
    [0, 10.0],
    [-1, 10.0],
    ['0', 10.0],
    ['-2.5', 10.0],
    ['garbage', 10.0],
    [null, 10.0],
    [false, 10.0],
]);
