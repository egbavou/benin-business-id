# Benin Business ID

Offline format validation for Beninese IFU and RCCM identifiers, with optional
RCCM existence checks and Laravel 12/13 validation rules. Requires PHP 8.3+.

## Install

```sh
composer require egbavou/benin-business-id
# For the Laravel checkers or the HTTP example below:
composer require guzzlehttp/guzzle:^7.8
```

The core works without Laravel. HTTP clients and request factories use PSR-18
and PSR-17 respectively; other compatible implementations can replace Guzzle.

## Validate a format

```php
use Egbavou\BeninBusinessId\Ifu;
use Egbavou\BeninBusinessId\Rccm;

Ifu::isValid('3202444139959'); // true
Ifu::tryFrom('invalid');      // null
$ifu = Ifu::from('3.201.200.247.219');
$ifu->value;                 // 3201200247219
$ifu->taxpayerType;          // TaxpayerType::Company
$ifu->registrationYear;      // 2012

$rccm = Rccm::from('RB/COT/07-B-1432');
$rccm->format();             // RB/COT/07 B 1432
$rccm->registryCode;         // COT
$rccm->year;                 // 2007
$rccm->category;             // B
$rccm->sequenceNumber;       // 1432
```

`from()` throws `InvalidIdentifierException` with a `FailureReason` in `reason`.
`format()` and string conversion return the normalized value. Spaces (including
Unicode spaces), dots and hyphens are removed. Prefixes, annotations and full
city names are rejected. RCCM year spelling and sequence leading zeros are
preserved. IFUs require 13 digits, a type digit from 0 to 6 and a registration
year from 2006 through the current year. No unpublished checksum is guessed.
Format validation never makes HTTP requests and does not establish existence.

## Check existence

```php
use Egbavou\BeninBusinessId\IfuChecker;
use Egbavou\BeninBusinessId\RccmChecker;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;

$checker = new RccmChecker(
    new Client(['timeout' => 10]),
    new HttpFactory,
    enabled: true,
);
$result = $checker->check('RB/COT/18 B 22272');
$result->status;    // CheckStatus: Found, NotFound, InvalidFormat or Unavailable
$result->input;     // Original input (or the value of an input value object)
$result->rccm;      // Parsed identifier; null for InvalidFormat
$result->checkedAt; // UTC DateTimeImmutable
$result->unavailableReason; // UnavailableReason enum; null unless Unavailable

$ifuResult = (new IfuChecker)->check('3202444139959'); // Unavailable
$ifuResult->unavailableReason; // UnavailableReason::NoSource
```

RCCM verification is **disabled by default**, including outside Laravel. A found
result exposes nullable `name`, `tradeName`, `creationDate`, `municipality`,
`district`, `phone`, `email`, `mainActivity`, `formerNumber` and `register`
(`RccmRegister::Companies` or `Establishments`). Companies-register results are
returned as published by APIEx, including for category A or E numbers.
Establishments-register results never expose the owner's given name, surname,
phone or email. Business details are null for every status other than `Found`.

APIEx is an **undocumented source**; its endpoints, schema and coverage can
change. `NotFound` means absent from this directory and **is not proof of
non-existence**. Technical failures return `Unavailable`. A stored number with
leading zeros and a parenthetical annotation can only be found when the input
also includes those zeros. Obtain written authorisation from APIEx before
production use.

`unavailableReason` is non-null exactly when the IFU or RCCM status is `Unavailable`:

| Enum case | Value | Meaning |
| --- | --- | --- |
| `NoSource` | `no_source` | IFU only: no authorised IFU source exists yet. |
| `Disabled` | `disabled` | Online verification is switched off. |
| `Transport` | `transport` | Failure before a response is received, including request construction. |
| `HttpStatus` | `http_status` | The server returned a status other than 200. |
| `InvalidJson` | `invalid_json` | The response body cannot be decoded as JSON. |
| `Schema` | `schema` | Unexpected JSON shape, incomplete results with no match, or an error after decoding a 200 response. |

The enum is `Egbavou\BeninBusinessId\UnavailableReason`. It is null for `Found`,
`NotFound` and `InvalidFormat`. Valid IFUs always return `NoSource`; RCCM results
never use this reason.

An existence check makes up to **4 sequential requests**, so the worst case is
about **4 × the configured timeout**. A `NotFound` or category-A check typically
took **10–13 seconds** in live measurements. The HTTP client controls timeouts.

IFU existence returns `Unavailable` until an authorised DGI source exists.
Invalid IFU input returns `InvalidFormat`. CAPTCHA-protected services are never
queried.

## Laravel

The service provider is discovered automatically. Use object rules:

```php
use Egbavou\BeninBusinessId\Laravel\Rules\IfuRule;
use Egbavou\BeninBusinessId\Laravel\Rules\IfuExistsRule;
use Egbavou\BeninBusinessId\Laravel\Rules\RccmRule;
use Egbavou\BeninBusinessId\Laravel\Rules\RccmExistsRule;

$rules = [
    'ifu' => ['required', new IfuRule, new IfuExistsRule],
    'rccm' => ['required', new RccmRule, (new RccmExistsRule)->failWhenUnavailable()],
];
```

Existence rules reject invalid formats and `NotFound`. `Unavailable` passes by
default; `failWhenUnavailable()` makes it fail. Strict RCCM checks have a dedicated
message when verification is disabled. With the current IFU checker, a valid IFU
passes the default existence rule and fails its strict version. Laravel skips
ordinary rules on empty strings; use `required` when the value is mandatory.
Optional fields need `nullable`, for example `['nullable', new RccmRule]`, because
Laravel's `ConvertEmptyStringsToNull` middleware converts empty strings to null.
English and French translations use `benin-business-id::validation`.

```sh
php artisan vendor:publish --tag=benin-business-id-config
php artisan vendor:publish --tag=benin-business-id-lang
```

Configuration lives in `config/benin-business-id.php`:

| Key under `rccm.apiex` | Environment variable | Default |
| --- | --- | --- |
| `enabled` | `BENIN_RCCM_APIEX_ENABLED` | `false` |
| `timeout` | `BENIN_RCCM_APIEX_TIMEOUT` | `10` seconds |
| `companies_url` | `BENIN_RCCM_APIEX_COMPANIES_URL` | `https://gdb.guichet.investbenin.bj/data-view/pm/latest/5` |
| `establishments_url` | `BENIN_RCCM_APIEX_ESTABLISHMENTS_URL` | `https://gdb.monentreprise.bj/data-view/registre%20pp/latest/310` |

`Http::fake()` does not intercept these requests: the provider constructs a
Guzzle client directly. To fake existence checks in Laravel tests, bind your own
`RccmChecker` using a fake PSR-18 client:

```php
$this->app->instance(RccmChecker::class, new RccmChecker(
    $fakePsr18Client,
    new \GuzzleHttp\Psr7\HttpFactory,
    enabled: true,
));
```

Timeouts that are zero, negative, non-numeric or null fall back to 10 seconds.

## Development

```sh
composer install
composer validate --strict
vendor/bin/pest
vendor/bin/pest --testsuite=core
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

The default tests use a fake PSR-18 client and exclude the `live` group. Core-only
tests run with `vendor/bin/pest --testsuite=core`. To explicitly allow the
two public-company live checks and one absent-number live check:

```sh
BENIN_BUSINESS_ID_LIVE=1 vendor/bin/pest --configuration=phpunit.live.xml --group=live
```

Without that environment variable the live tests are skipped, even when selected.
CI covers PHP 8.3, 8.4 and 8.5, with no Laravel, Laravel 12 and Laravel 13,
plus PHP 8.3 / Laravel 12 with the lowest supported dependencies.

MIT license. Copyright Egbavou.
