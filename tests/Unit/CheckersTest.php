<?php

declare(strict_types=1);

use Egbavou\BeninBusinessId\CheckStatus;
use Egbavou\BeninBusinessId\Ifu;
use Egbavou\BeninBusinessId\IfuChecker;
use Egbavou\BeninBusinessId\Rccm;
use Egbavou\BeninBusinessId\RccmChecker;
use Egbavou\BeninBusinessId\RccmRegister;
use Egbavou\BeninBusinessId\Tests\Support\FakeHttpClient;
use Egbavou\BeninBusinessId\UnavailableReason;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

it('validates before checking the disabled switch without making requests', function (): void {
    $client = new FakeHttpClient;
    foreach ([false, true] as $enabled) {
        $checker = new RccmChecker($client, new HttpFactory, enabled: $enabled);
        $result = $checker->check('bad input');
        Assert::assertSame(CheckStatus::InvalidFormat, $result->status);
        Assert::assertNull($result->rccm);
        Assert::assertNull($result->unavailableReason);
        Assert::assertSame('bad input', $result->input);
    }
    $checker = new RccmChecker($client, new HttpFactory);
    Assert::assertFalse($checker->isEnabled());
    $result = $checker->check(' rb/cot/18-B-22272 ');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::Disabled, $result->unavailableReason);
    Assert::assertSame(' rb/cot/18-B-22272 ', $result->input);
    Assert::assertNotNull($result->rccm);
    Assert::assertSame('UTC', $result->checkedAt->getTimezone()->getName());
    Assert::assertSame([], $client->requests);
});

it('maps all company fields from the first exact request', function (): void {
    $client = new FakeHttpClient([FakeHttpClient::json([[
        'Numéro RC' => 'RB/COT/18 B 22272',
        'Dénomination' => 'Example Company',
        'Nom commercial' => 'Example Trade',
        'Date création' => '2018-08-27',
        'Commune' => ['key' => 'city', 'value' => 'Example Municipality'],
        'Quartier' => ['key' => 'district', 'value' => 'Example District'],
        'Téléphone' => '+229 01 00 00 00 00',
        'Email' => 'company@example.test',
        'Activité principale' => 'Ignored company activity',
    ]])]);
    $identifier = Rccm::from('RB/COT/18 B 22272');
    $result = (new RccmChecker($client, new HttpFactory, enabled: true))->check($identifier);
    Assert::assertSame(CheckStatus::Found, $result->status);
    Assert::assertNull($result->unavailableReason);
    Assert::assertSame($identifier, $result->rccm);
    Assert::assertSame($identifier->value, $result->input);
    Assert::assertSame('Example Company', $result->name);
    Assert::assertSame('Example Trade', $result->tradeName);
    Assert::assertSame('2018-08-27', $result->creationDate?->format('Y-m-d'));
    Assert::assertSame('Example Municipality', $result->municipality);
    Assert::assertSame('Example District', $result->district);
    Assert::assertSame('+229 01 00 00 00 00', $result->phone);
    Assert::assertSame('company@example.test', $result->email);
    Assert::assertNull($result->mainActivity);
    Assert::assertNull($result->formerNumber);
    Assert::assertSame(RccmRegister::Companies, $result->register);
    Assert::assertSame('UTC', $result->checkedAt->getTimezone()->getName());
    Assert::assertLessThanOrEqual(new DateTimeImmutable('now'), $result->checkedAt);
    Assert::assertCount(1, $client->requests);
});

it('extracts each specified former number after the contains request', function (string $annotation, string $former): void {
    $client = new FakeHttpClient([
        FakeHttpClient::json(),
        FakeHttpClient::json([['Numéro RC' => 'RB/COT/07 B 2058 '.$annotation]]),
    ]);
    $result = (new RccmChecker($client, new HttpFactory, enabled: true))->check('RB/COT/07 B 2058');
    Assert::assertSame(CheckStatus::Found, $result->status);
    Assert::assertNull($result->unavailableReason);
    Assert::assertSame($former, $result->formerNumber);
    Assert::assertCount(2, $client->requests);
})->with([
    ['(Ancien n°: 02 B 1545)', '02 B 1545'],
    ['(ANCIEN N° : 21.130 B)', '21.130 B'],
    ['(Ancien no: RB/PK/11 B 059)', 'RB/PK/11 B 059'],
    ['(Ancien n°RCCM RB/PNO/05 B 146)', 'RB/PNO/05 B 146'],
]);

it('compares the entire identifier rather than matching a prefix', function (): void {
    $records = [];
    for ($number = 22270; $number <= 22279; $number++) {
        $records[] = ['Numéro RC' => 'RB/COT/18 B '.$number];
    }
    $records[] = ['Numéro RC' => 'RB/ABC/18 B 2227'];
    $records[] = ['Numéro RC' => 'RB/COT/17 B 2227'];
    $records[] = ['Numéro RC' => 'RB/COT/18 C 2227'];
    $records[] = ['Numéro RC' => 'invalid identifier'];
    $client = new FakeHttpClient([FakeHttpClient::json(), FakeHttpClient::json($records), FakeHttpClient::json(), FakeHttpClient::json()]);
    $result = (new RccmChecker($client, new HttpFactory, enabled: true))->check('RB/COT/18 B 2227');
    Assert::assertSame(CheckStatus::NotFound, $result->status);
    Assert::assertNull($result->unavailableReason);
    Assert::assertCount(4, $client->requests);
    foreach (['name', 'tradeName', 'creationDate', 'municipality', 'district', 'phone', 'email', 'mainActivity', 'formerNumber', 'register'] as $field) {
        Assert::assertNull($result->{$field});
    }
});

it('compares equivalent year forms and sequence numbers', function (string $input, string $stored): void {
    $client = new FakeHttpClient([FakeHttpClient::json([['Numéro RC' => $stored]])]);
    $result = (new RccmChecker($client, new HttpFactory, enabled: true))->check($input);
    Assert::assertSame(CheckStatus::Found, $result->status);
    Assert::assertNull($result->unavailableReason);
    Assert::assertSame($input, $result->rccm?->value);
})->with([
    ['RB/ABC/12 B 180', 'RB/ABC/2012 B 180'],
    ['RB/COT/2005 B 40', 'RB/COT/2005 B 0040'],
    ['RB/COT/2005 B 0040', 'RB/COT/05-B-40'],
    ['RB/COT/18 B 000000', 'RB/COT/18 B 0'],
]);

it('searches establishments first for category A and never exposes personal fields', function (): void {
    $client = new FakeHttpClient([FakeHttpClient::json([[
        'Numéro RC' => 'RB/PKO/23 A 18689',
        'Nom commercial' => 'Example Establishment',
        'Date de création' => '2023-01-02',
        'Commune' => 'Example Municipality',
        'Quartier' => 'Example District',
        'Activité principale' => 'Example Activity',
        'Dénomination' => 'Untrusted establishment name',
        'Prénom' => 'Private given name sentinel',
        'Nom' => 'Private surname sentinel',
        'Téléphone' => 'Private phone sentinel',
        'Email' => 'private@example.test',
    ]])]);
    $result = (new RccmChecker($client, new HttpFactory, enabled: true))->check('RB/PKO/23 A 18689');
    Assert::assertSame(CheckStatus::Found, $result->status);
    Assert::assertNull($result->unavailableReason);
    Assert::assertSame(RccmRegister::Establishments, $result->register);
    Assert::assertStringContainsString('gdb.monentreprise.bj', (string) $client->requests[0]->getUri());
    Assert::assertSame('Example Establishment', $result->tradeName);
    Assert::assertSame('2023-01-02', $result->creationDate?->format('Y-m-d'));
    Assert::assertSame('Example Municipality', $result->municipality);
    Assert::assertSame('Example District', $result->district);
    Assert::assertSame('Example Activity', $result->mainActivity);
    Assert::assertNull($result->name);
    Assert::assertNull($result->phone);
    Assert::assertNull($result->email);
    $serialized = serialize($result);
    foreach (['Private given name sentinel', 'Private surname sentinel', 'Private phone sentinel', 'private@example.test'] as $private) {
        Assert::assertStringNotContainsString($private, $serialized);
    }
});

it('falls back to the other register in the specified order', function (string $category): void {
    $number = 'RB/COT/18 '.$category.' 1234';
    $client = new FakeHttpClient([FakeHttpClient::json(), FakeHttpClient::json(), FakeHttpClient::json([['Numéro RC' => $number]])]);
    $result = (new RccmChecker($client, new HttpFactory, enabled: true, companiesUrl: 'https://example.test/companies', establishmentsUrl: 'https://example.test/establishments'))->check($number);
    Assert::assertSame(CheckStatus::Found, $result->status);
    Assert::assertNull($result->unavailableReason);
    $first = $category === 'A' ? '/establishments' : '/companies';
    $last = $category === 'A' ? '/companies' : '/establishments';
    Assert::assertSame($first, $client->requests[0]->getUri()->getPath());
    Assert::assertSame($first, $client->requests[1]->getUri()->getPath());
    Assert::assertSame($last, $client->requests[2]->getUri()->getPath());
})->with(['A', 'B', 'C', 'D', 'E']);

it('returns unavailable immediately for technical and schema failures', function (ResponseInterface|Throwable $response, UnavailableReason $reason): void {
    $client = new FakeHttpClient([$response]);
    $result = (new RccmChecker($client, new HttpFactory, enabled: true))->check('RB/COT/18 B 22272');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame($reason, $result->unavailableReason);
    Assert::assertNotNull($result->rccm);
    Assert::assertNull($result->register);
    Assert::assertNull($result->name);
    Assert::assertCount(1, $client->requests);
})->with([
    'PSR-18 exception' => [new class('Fake transport failure.') extends RuntimeException implements ClientExceptionInterface {}, UnavailableReason::Transport],
    'unexpected runtime exception' => [new RuntimeException('Fake runtime failure.'), UnavailableReason::Transport],
    'server error' => [new Response(500), UnavailableReason::HttpStatus],
    'redirect' => [new Response(301), UnavailableReason::HttpStatus],
    'invalid JSON' => [new Response(200, [], '<html>Unavailable</html>'), UnavailableReason::InvalidJson],
    'missing results' => [new Response(200, [], '{"count":0}'), UnavailableReason::Schema],
    'missing count' => [new Response(200, [], '{"results":[]}'), UnavailableReason::Schema],
    'string count' => [new Response(200, [], '{"count":"0","results":[]}'), UnavailableReason::Schema],
    'object results' => [new Response(200, [], '{"count":0,"results":{}}'), UnavailableReason::Schema],
    'non-list results' => [new Response(200, [], '{"count":1,"results":{"1":{}}}'), UnavailableReason::Schema],
    'non-object row' => [new Response(200, [], '{"count":1,"results":[[]]}'), UnavailableReason::Schema],
    'missing number' => [FakeHttpClient::json([['Other field' => 'Changed schema']]), UnavailableReason::Schema],
    'top-level list' => [new Response(200, [], '[]'), UnavailableReason::Schema],
    'null JSON' => [new Response(200, [], 'null'), UnavailableReason::Schema],
    'schema failure after matching record' => [FakeHttpClient::json([['Numéro RC' => 'RB/COT/18 B 22272'], ['Other field' => 'Changed schema']]), UnavailableReason::Schema],
]);

it('returns transport unavailable for an internal PHP type error', function (): void {
    $client = new FakeHttpClient([new TypeError('Fake PHP type error.')]);
    $result = (new RccmChecker($client, new HttpFactory, enabled: true))->check('RB/COT/18 B 22272');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::Transport, $result->unavailableReason);
});

it('maps missing empty null and malformed optional fields to null', function (mixed $date): void {
    $client = new FakeHttpClient([FakeHttpClient::json([[
        'Numéro RC' => 'RB/COT/18 B 22272',
        'Dénomination' => '   ',
        'Nom commercial' => null,
        'Date création' => $date,
        'Commune' => ['key' => 'missing-value'],
        'Quartier' => false,
    ]])]);
    $result = (new RccmChecker($client, new HttpFactory, enabled: true))->check('RB/COT/18 B 22272');
    Assert::assertSame(CheckStatus::Found, $result->status);
    Assert::assertNull($result->unavailableReason);
    Assert::assertNull($result->name);
    Assert::assertNull($result->tradeName);
    Assert::assertNull($result->creationDate);
    Assert::assertNull($result->municipality);
    Assert::assertNull($result->district);
    Assert::assertNull($result->phone);
    Assert::assertNull($result->email);
})->with([[null], [''], ['27/08/2018'], ['2018-02-30'], ['2018-8-27'], ['2018-08-27T00:00:00Z'], [123]]);

it('defines exactly the six unavailable reasons in order', function (): void {
    Assert::assertSame([
        'NoSource' => 'no_source',
        'Disabled' => 'disabled',
        'Transport' => 'transport',
        'HttpStatus' => 'http_status',
        'InvalidJson' => 'invalid_json',
        'Schema' => 'schema',
    ], array_column(UnavailableReason::cases(), 'value', 'name'));
});

it('returns an IFU result without an existence source', function (): void {
    $checker = new IfuChecker;
    $invalid = $checker->check('invalid');
    Assert::assertSame(CheckStatus::InvalidFormat, $invalid->status);
    Assert::assertSame('invalid', $invalid->input);
    Assert::assertNull($invalid->ifu);
    Assert::assertNull($invalid->unavailableReason);
    foreach ([' 3202444139959 ', Ifu::from('3202444139959')] as $input) {
        $result = $checker->check($input);
        Assert::assertSame(CheckStatus::Unavailable, $result->status);
        Assert::assertSame(UnavailableReason::NoSource, $result->unavailableReason);
        Assert::assertNotNull($result->ifu);
        Assert::assertSame('3202444139959', $result->ifu->value);
        Assert::assertSame(is_string($input) ? $input : $input->value, $result->input);
        Assert::assertSame('UTC', $result->checkedAt->getTimezone()->getName());
    }
});

it('selects the exact record after several near misses', function (): void {
    $records = [
        ['Numéro RC' => 'RB/COT/18 B 22270', 'Dénomination' => 'Wrong sequence'],
        ['Numéro RC' => 'RB/ABC/18 B 22272', 'Dénomination' => 'Wrong registry'],
        ['Numéro RC' => 'RB/COT/17 B 22272', 'Dénomination' => 'Wrong year'],
        ['Numéro RC' => 'RB/COT/18 C 22272', 'Dénomination' => 'Wrong category'],
        [
            'Numéro RC' => 'RB/COT/18 B 22272 (Ancien no: 02 B 1234)',
            'Dénomination' => 'Matched Company',
            'Nom commercial' => 'Matched Trade',
            'Date création' => '2018-08-27',
            'Commune' => 'Matched Municipality',
            'Quartier' => 'Matched District',
            'Téléphone' => '+229 01 00 00 00 00',
            'Email' => 'matched@example.test',
        ],
    ];
    $client = new FakeHttpClient([FakeHttpClient::json($records)]);
    $result = (new RccmChecker($client, new HttpFactory, enabled: true))->check('RB/COT/18 B 22272');
    Assert::assertSame(CheckStatus::Found, $result->status);
    Assert::assertNull($result->unavailableReason);
    Assert::assertSame(RccmRegister::Companies, $result->register);
    Assert::assertSame('Matched Company', $result->name);
    Assert::assertSame('Matched Trade', $result->tradeName);
    Assert::assertSame('2018-08-27', $result->creationDate?->format('Y-m-d'));
    Assert::assertSame('Matched Municipality', $result->municipality);
    Assert::assertSame('Matched District', $result->district);
    Assert::assertSame('+229 01 00 00 00 00', $result->phone);
    Assert::assertSame('matched@example.test', $result->email);
    Assert::assertSame('02 B 1234', $result->formerNumber);
    Assert::assertCount(1, $client->requests);
});

it('treats incomplete results as schema unavailable unless a row matches', function (string $stored, CheckStatus $expected): void {
    $client = new FakeHttpClient([new Response(200, [], json_encode([
        'count' => 51,
        'results' => [['Numéro RC' => $stored]],
    ], JSON_THROW_ON_ERROR))]);
    $result = (new RccmChecker($client, new HttpFactory, enabled: true))->check('RB/COT/18 B 22272');
    Assert::assertSame($expected, $result->status);
    Assert::assertSame($expected === CheckStatus::Unavailable ? UnavailableReason::Schema : null, $result->unavailableReason);
    Assert::assertCount(1, $client->requests);
})->with([
    ['RB/COT/18 B 22273', CheckStatus::Unavailable],
    ['RB/COT/18 B 22272', CheckStatus::Found],
]);

it('returns schema unavailable for a nonempty count with no received rows', function (): void {
    $client = new FakeHttpClient([new Response(200, [], '{"count":1,"results":[]}')]);
    $result = (new RccmChecker($client, new HttpFactory, enabled: true))->check('RB/COT/18 B 22272');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::Schema, $result->unavailableReason);
    Assert::assertCount(1, $client->requests);
});

it('returns transport unavailable when the request factory rejects a URL', function (): void {
    $client = new FakeHttpClient;
    $factory = new class implements RequestFactoryInterface
    {
        public function createRequest(string $method, $uri): RequestInterface
        {
            throw new InvalidArgumentException('Rejected URL.');
        }
    };
    $result = (new RccmChecker($client, $factory, enabled: true))->check('RB/COT/18 B 22272');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::Transport, $result->unavailableReason);
    Assert::assertSame([], $client->requests);
});

it('returns schema unavailable for unexpected errors after receiving a 200 response', function (): void {
    $response = new class extends Response
    {
        public function getBody(): StreamInterface
        {
            throw new TypeError('Fake response processing failure.');
        }
    };
    $client = new FakeHttpClient([$response]);
    $result = (new RccmChecker($client, new HttpFactory, enabled: true))->check('RB/COT/18 B 22272');
    Assert::assertSame(CheckStatus::Unavailable, $result->status);
    Assert::assertSame(UnavailableReason::Schema, $result->unavailableReason);
});

it('normalizes numeric and trimmed text fields including catalog values', function (mixed $raw, ?string $expected): void {
    $client = new FakeHttpClient([FakeHttpClient::json([[
        'Numéro RC' => 'RB/COT/18 B 22272',
        'Dénomination' => $raw,
        'Commune' => ['value' => $raw],
    ]])]);
    $result = (new RccmChecker($client, new HttpFactory, enabled: true))->check('RB/COT/18 B 22272');
    Assert::assertSame(CheckStatus::Found, $result->status);
    Assert::assertNull($result->unavailableReason);
    Assert::assertSame($expected, $result->name);
    Assert::assertSame($expected, $result->municipality);
})->with([
    [42, '42'],
    [0, '0'],
    [12.5, '12.5'],
    ['  Example  ', 'Example'],
    [" \t\n ", null],
    [false, null],
    [[], null],
]);
