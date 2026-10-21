<?php

use Egbavou\BeninBusinessId\RccmChecker;

return [
    'rccm' => [
        'apiex' => [
            'enabled' => env('BENIN_RCCM_APIEX_ENABLED', false),
            'timeout' => env('BENIN_RCCM_APIEX_TIMEOUT', 10),
            'companies_url' => env('BENIN_RCCM_APIEX_COMPANIES_URL', RccmChecker::DEFAULT_COMPANIES_URL),
            'establishments_url' => env('BENIN_RCCM_APIEX_ESTABLISHMENTS_URL', RccmChecker::DEFAULT_ESTABLISHMENTS_URL),
        ],
    ],
];
