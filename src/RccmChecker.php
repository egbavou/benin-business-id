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

final class RccmChecker
{
    public const DEFAULT_COMPANIES_URL = 'https://gdb.guichet.investbenin.bj/data-view/pm/latest/5';

    public const DEFAULT_ESTABLISHMENTS_URL = 'https://gdb.monentreprise.bj/data-view/registre%20pp/latest/310';

    private readonly ApiexDirectory $directory;

    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        private readonly bool $enabled = false,
        private readonly string $companiesUrl = self::DEFAULT_COMPANIES_URL,
        private readonly string $establishmentsUrl = self::DEFAULT_ESTABLISHMENTS_URL,
    ) {
        $this->directory = new ApiexDirectory($httpClient, $requestFactory);
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
            return $this->result(CheckStatus::InvalidFormat, $input, null);
        }

        if (! $this->enabled) {
            return $this->result(CheckStatus::Unavailable, $input, $identifier, unavailableReason: UnavailableReason::Disabled);
        }

        $registers = $identifier->category === 'A'
            ? [RccmRegister::Establishments, RccmRegister::Companies]
            : [RccmRegister::Companies, RccmRegister::Establishments];

        try {
            foreach ($registers as $register) {
                $url = $register === RccmRegister::Companies ? $this->companiesUrl : $this->establishmentsUrl;

                foreach ([true, false] as $exact) {
                    foreach ($this->directory->records($url, $register, $identifier, $exact) as $record) {
                        if (ApiexDirectory::matches($record, $identifier)) {
                            return $this->result(CheckStatus::Found, $input, $identifier, $record, $register);
                        }
                    }
                }
            }
        } catch (DirectoryException $exception) {
            return $this->result(CheckStatus::Unavailable, $input, $identifier, unavailableReason: $exception->reason);
        } catch (Throwable) {
            return $this->result(CheckStatus::Unavailable, $input, $identifier, unavailableReason: UnavailableReason::Schema);
        }

        return $this->result(CheckStatus::NotFound, $input, $identifier);
    }

    private function result(CheckStatus $status, string $input, ?Rccm $rccm, ?stdClass $record = null, ?RccmRegister $register = null, ?UnavailableReason $unavailableReason = null): RccmCheckResult
    {
        $checkedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($status !== CheckStatus::Found || $record === null) {
            return new RccmCheckResult($status, $checkedAt, $input, $rccm, unavailableReason: $unavailableReason);
        }

        $company = $register === RccmRegister::Companies;

        return new RccmCheckResult(
            status: $status,
            checkedAt: $checkedAt,
            input: $input,
            rccm: $rccm,
            name: $company ? ApiexDirectory::text($record, 'Dénomination') : null,
            tradeName: ApiexDirectory::text($record, 'Nom commercial'),
            creationDate: ApiexDirectory::creationDate($record, $company ? 'Date création' : 'Date de création'),
            municipality: ApiexDirectory::text($record, 'Commune'),
            district: ApiexDirectory::text($record, 'Quartier'),
            phone: $company ? ApiexDirectory::text($record, 'Téléphone') : null,
            email: $company ? ApiexDirectory::text($record, 'Email') : null,
            mainActivity: $company ? null : ApiexDirectory::text($record, 'Activité principale'),
            formerNumber: ApiexDirectory::formerNumber($record),
            register: $register,
        );
    }
}
