<?php

declare(strict_types=1);

namespace Egbavou\BeninBusinessId;

enum UnavailableReason: string
{
    case NoSource = 'no_source';
    case Disabled = 'disabled';
    case Transport = 'transport';
    case HttpStatus = 'http_status';
    case InvalidJson = 'invalid_json';
    case Schema = 'schema';
}
