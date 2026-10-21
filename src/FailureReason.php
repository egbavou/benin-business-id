<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId;

enum FailureReason: string
{
    case InvalidCharacters = 'invalid_characters';
    case InvalidLength = 'invalid_length';
    case InvalidChecksum = 'invalid_checksum';
    case UnknownTaxpayerType = 'unknown_taxpayer_type';
    case InvalidYear = 'invalid_year';
    case InvalidCountry = 'invalid_country';
    case UnknownRegistry = 'unknown_registry';
    case UnknownCategory = 'unknown_category';
    case InvalidStructure = 'invalid_structure';
}
