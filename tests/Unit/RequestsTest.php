<?php

declare(strict_types=1);

use Egbavou\BeninBusinessId\RccmChecker;
use Egbavou\BeninBusinessId\Tests\Support\FakeHttpClient;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\Assert;

it('sends the exact expected equality and delimited contains filters', function (): void {
    $client = new FakeHttpClient(array_fill(0, 4, FakeHttpClient::json()));
    (new RccmChecker($client, new HttpFactory, enabled: true))->check('RB/COT/2005 B 40');
    Assert::assertCount(4, $client->requests);
    foreach ($client->requests as $index => $request) {
        Assert::assertSame('GET', $request->getMethod());
        Assert::assertSame('egbavou/benin-business-id', $request->getHeaderLine('User-Agent'));
        Assert::assertSame('application/json', $request->getHeaderLine('Accept'));
        parse_str($request->getUri()->getQuery(), $query);
        Assert::assertSame('1', $query['page']);
        Assert::assertSame('50', $query['page_size']);
        Assert::assertIsString($query['filters']);
        Assert::assertStringNotContainsString('\\/', $query['filters']);
        $filters = json_decode($query['filters'], true, 512, JSON_THROW_ON_ERROR);
        $exact = $index % 2 === 0;
        $expected = [];
        foreach (['2005', '05'] as $year) {
            foreach ($exact ? ['40', '040', '0040', '00040'] : ['40'] as $sequence) {
                foreach (["RB/COT/{$year} B {$sequence}", "RB/COT/{$year}-B-{$sequence}"] as $form) {
                    foreach ($exact ? [''] : [' ', '('] as $suffix) {
                        $expected[] = [
                            'gate' => $expected === [] ? '&&' : '||',
                            'type' => 'string',
                            'field_id' => $index < 2 ? 307 : 141,
                            'logic' => $exact ? '==' : 'icontains',
                            'value' => $form.$suffix,
                        ];
                    }
                }
            }
        }
        Assert::assertSame($expected, $filters);
    }
});

it('uses only base sequences in contains filters and preserves leading zeros', function (): void {
    $client = new FakeHttpClient(array_fill(0, 4, FakeHttpClient::json()));
    (new RccmChecker($client, new HttpFactory, enabled: true))->check('RB/COT/05 B 0040');
    parse_str($client->requests[1]->getUri()->getQuery(), $query);
    Assert::assertIsString($query['filters']);
    $filters = json_decode($query['filters'], true, 512, JSON_THROW_ON_ERROR);
    Assert::assertIsArray($filters);
    Assert::assertCount(16, $filters);
    $values = [];
    foreach ($filters as $filter) {
        Assert::assertIsArray($filter);
        $values[] = $filter['value'];
    }
    Assert::assertContains('RB/COT/05 B 0040(', $values);
    Assert::assertContains('RB/COT/2005-B-40 ', $values);
    Assert::assertNotContains('RB/COT/05 B 00040(', $values);
});

it('does not generate unsupported four digit years for recent registrations', function (): void {
    $client = new FakeHttpClient(array_fill(0, 4, FakeHttpClient::json()));
    (new RccmChecker($client, new HttpFactory, enabled: true))->check('RB/COT/24 B 12345');
    parse_str($client->requests[0]->getUri()->getQuery(), $query);
    Assert::assertIsString($query['filters']);
    $filters = json_decode($query['filters'], true, 512, JSON_THROW_ON_ERROR);
    Assert::assertIsArray($filters);
    Assert::assertCount(2, $filters);
    Assert::assertStringNotContainsString('2024', $query['filters']);
});

it('appends request parameters to a configured URL with an existing query', function (): void {
    $client = new FakeHttpClient(array_fill(0, 4, FakeHttpClient::json()));
    (new RccmChecker($client, new HttpFactory, enabled: true,
        companiesUrl: 'https://example.test/companies?token=example',
        establishmentsUrl: 'https://example.test/establishments?token=example',
    ))->check('RB/COT/18 B 22272');

    Assert::assertCount(4, $client->requests);
    foreach ($client->requests as $request) {
        Assert::assertStringStartsWith('token=example&page=1&', $request->getUri()->getQuery());
        parse_str($request->getUri()->getQuery(), $query);
        Assert::assertSame('example', $query['token']);
        Assert::assertSame('50', $query['page_size']);
        Assert::assertArrayHasKey('filters', $query);
    }
});
