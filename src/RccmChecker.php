<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId;

use DateTimeImmutable;
use DateTimeZone;
use Egbavou\BeninBusinessId\Internal\ApiexDirectory;
use Egbavou\BeninBusinessId\Internal\DirectoryException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use stdClass;
use Throwable;

final readonly class RccmChecker
{
    public const DEFAULT_COMPANIES_URL = 'https://gdb.guichet.investbenin.bj/data-view/pm/latest/5';

    public const DEFAULT_ESTABLISHMENTS_URL = 'https://gdb.monentreprise.bj/data-view/registre%20pp/latest/310';

    private ApiexDirectory $directory;

    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        private bool $enabled = false,
        string $companiesUrl = self::DEFAULT_COMPANIES_URL,
        string $establishmentsUrl = self::DEFAULT_ESTABLISHMENTS_URL,
    ) {
        $this->directory = new ApiexDirectory($httpClient, $requestFactory, $companiesUrl, $establishmentsUrl);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function check(string|Rccm $rccm): RccmCheckResult
    {
        $input = $rccm instanceof Rccm ? $rccm->value : $rccm;
        $identifier = $rccm instanceof Rccm ? $rccm : Rccm::tryFrom($rccm);

        if ($identifier === null) {
            return $this->statusResult(CheckStatus::InvalidFormat, $input, null);
        }

        if (! $this->enabled) {
            return $this->statusResult(CheckStatus::Unavailable, $input, $identifier, unavailableReason: UnavailableReason::Disabled);
        }

        $registers = $identifier->category === 'A'
            ? [RccmRegister::Establishments, RccmRegister::Companies]
            : [RccmRegister::Companies, RccmRegister::Establishments];

        try {
            foreach ($registers as $register) {
                $record = $this->directory->find($register, $identifier);

                if ($record !== null) {
                    return $this->foundResult($input, $identifier, $record, $register);
                }
            }
        } catch (DirectoryException $exception) {
            return $this->statusResult(CheckStatus::Unavailable, $input, $identifier,
                unavailableReason: $exception->reason,
                failure: $exception->getPrevious() ?? $exception,
            );
        } catch (Throwable $exception) {
            return $this->statusResult(CheckStatus::Unavailable, $input, $identifier,
                unavailableReason: UnavailableReason::Schema,
                failure: $exception,
            );
        }

        return $this->statusResult(CheckStatus::NotFound, $input, $identifier);
    }

    private function statusResult(CheckStatus $status, string $input, ?Rccm $rccm, ?UnavailableReason $unavailableReason = null, ?Throwable $failure = null): RccmCheckResult
    {
        return new RccmCheckResult(
            status: $status,
            checkedAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            input: $input,
            rccm: $rccm,
            unavailableReason: $unavailableReason,
            failure: $failure,
        );
    }

    private function foundResult(string $input, Rccm $rccm, stdClass $record, RccmRegister $register): RccmCheckResult
    {
        return new RccmCheckResult(
            CheckStatus::Found,
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
            $input,
            $rccm,
            ...$this->directory->details($record, $register),
        );
    }
}
