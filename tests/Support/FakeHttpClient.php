<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Tests\Support;

use GuzzleHttp\Psr7\Response;
use LogicException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class FakeHttpClient implements ClientInterface
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @param list<ResponseInterface|Throwable> $responses */
    public function __construct(private array $responses = []) {}

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses);

        if ($response instanceof Throwable) {
            throw $response;
        }

        return $response ?? throw new LogicException('No fake response was queued.');
    }

    /** @param list<array<string, mixed>> $records */
    public static function json(array $records = []): ResponseInterface
    {
        return new Response(200, [], json_encode(['count' => count($records), 'results' => $records], JSON_THROW_ON_ERROR));
    }
}
