<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId\Internal;

use DateTimeImmutable;
use DateTimeZone;
use Egbavou\BeninBusinessId\Rccm;
use Egbavou\BeninBusinessId\RccmRegister;
use Egbavou\BeninBusinessId\UnavailableReason;
use JsonException;
use LogicException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use stdClass;
use Throwable;

/** @internal */
final class ApiexDirectory
{
    public const COMPANIES_FIELD_ID = 307;

    public const ESTABLISHMENTS_FIELD_ID = 141;

    private const USER_AGENT = 'egbavou/benin-business-id (+https://github.com/egbavou/benin-business-id)';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
    ) {}

    /** @return list<stdClass> */
    public function records(string $url, RccmRegister $register, Rccm $rccm, bool $exact): array
    {
        try {
            $query = http_build_query([
                'page' => 1,
                'page_size' => 50,
                'filters' => json_encode($this->filters($register, $rccm, $exact), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ], '', '&', PHP_QUERY_RFC3986);
            $request = $this->requestFactory->createRequest('GET', $url.(str_contains($url, '?') ? '&' : '?').$query)
                ->withHeader('User-Agent', self::USER_AGENT)
                ->withHeader('Accept', 'application/json');
            $response = $this->httpClient->sendRequest($request);
        } catch (Throwable $exception) {
            throw new DirectoryException(UnavailableReason::Transport, $exception);
        }

        if ($response->getStatusCode() !== 200) {
            throw new DirectoryException(UnavailableReason::HttpStatus);
        }

        try {
            $body = json_decode((string) $response->getBody(), false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new DirectoryException(UnavailableReason::InvalidJson, $exception);
        }

        if (! $body instanceof stdClass || ! isset($body->count) || ! is_int($body->count)
            || ! isset($body->results) || ! is_array($body->results) || ! array_is_list($body->results)) {
            throw new DirectoryException(UnavailableReason::Schema);
        }

        $records = [];

        foreach ($body->results as $record) {
            if (! $record instanceof stdClass || ! property_exists($record, 'Numéro RC')) {
                throw new DirectoryException(UnavailableReason::Schema);
            }

            $records[] = $record;
        }

        if ($body->count > count($records)) {
            foreach ($records as $record) {
                if (self::matches($record, $rccm)) {
                    return $records;
                }
            }

            throw new DirectoryException(UnavailableReason::Schema);
        }

        return $records;
    }

    /** @return list<array{gate: string, type: string, field_id: int, logic: string, value: string}> */
    public function filters(RccmRegister $register, Rccm $rccm, bool $exact): array
    {
        $yearInput = self::typedYear($rccm);
        $years = [$yearInput];

        if ($rccm->year <= 2020) {
            $years[] = strlen($yearInput) === 2 ? (string) $rccm->year : substr($yearInput, -2);
        }

        $numbers = [$rccm->sequenceNumber, self::sequenceKey($rccm)];

        if ($exact && $rccm->sequenceNumber[0] !== '0' && strlen($rccm->sequenceNumber) < 5) {
            foreach ([3, 4, 5] as $length) {
                $numbers[] = str_pad($rccm->sequenceNumber, $length, '0', STR_PAD_LEFT);
            }
        }

        $values = [];

        foreach ($years as $year) {
            foreach (array_unique($numbers) as $number) {
                foreach (["RB/{$rccm->registryCode}/{$year} {$rccm->category} {$number}", "RB/{$rccm->registryCode}/{$year}-{$rccm->category}-{$number}"] as $form) {
                    foreach ($exact ? [''] : [' ', '('] as $suffix) {
                        $values[] = $form.$suffix;
                    }
                }
            }
        }

        $filters = [];

        foreach ($values as $value) {
            $filters[] = [
                'gate' => $filters === [] ? '&&' : '||',
                'type' => 'string',
                'field_id' => $register === RccmRegister::Companies ? self::COMPANIES_FIELD_ID : self::ESTABLISHMENTS_FIELD_ID,
                'logic' => $exact ? '==' : 'icontains',
                'value' => $value,
            ];
        }

        return $filters;
    }

    public static function matches(stdClass $record, Rccm $rccm): bool
    {
        $raw = self::text($record, 'Numéro RC');
        $candidate = $raw === null ? null : Rccm::tryFrom(explode('(', $raw, 2)[0]);

        return $candidate !== null
            && $candidate->registryCode === $rccm->registryCode
            && $candidate->year === $rccm->year
            && $candidate->category === $rccm->category
            && self::sequenceKey($candidate) === self::sequenceKey($rccm);
    }

    public static function text(stdClass $record, string $field): ?string
    {
        $value = $record->{$field} ?? null;

        if ($value instanceof stdClass) {
            $value = $value->value ?? null;
        }

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    public static function creationDate(stdClass $record, string $field): ?DateTimeImmutable
    {
        $value = self::text($record, $field);

        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }

    public static function formerNumber(stdClass $record): ?string
    {
        $raw = self::text($record, 'Numéro RC');

        if ($raw === null || preg_match('/\(\s*ancien\s*n\s*[°o]?\s*(?:rccm)?\s*:?\s*(.+?)\s*\)/iu', $raw, $parts) !== 1) {
            return null;
        }

        return trim($parts[1]);
    }

    private static function typedYear(Rccm $rccm): string
    {
        if (preg_match('#^RB/[A-Z]{2,3}/(\d{2}|\d{4}) #', $rccm->value, $parts) !== 1) {
            throw new LogicException('Unexpected normalized RCCM value.');
        }

        return $parts[1];
    }

    private static function sequenceKey(Rccm $rccm): string
    {
        return ltrim($rccm->sequenceNumber, '0') ?: '0';
    }
}
