<?php

return [
    'ifu' => [
        'invalid_characters' => 'The :attribute field must contain only digits.',
        'invalid_length' => 'The :attribute field must contain 13 digits.',
        'invalid_checksum' => 'The :attribute field is not a valid IFU.',
        'unknown_taxpayer_type' => 'The :attribute field must start with a digit from 0 to 6.',
        'invalid_year' => 'The :attribute field contains an invalid registration year.',
        'not_found' => 'The IFU specified in :attribute could not be found.',
        'unavailable' => 'The IFU specified in :attribute cannot be verified at the moment.',
    ],
    'rccm' => [
        'invalid_characters' => 'The :attribute field contains unauthorised characters.',
        'invalid_length' => 'The :attribute field does not have the length of an RCCM number.',
        'invalid_checksum' => 'The :attribute field is not a valid RCCM number.',
        'invalid_structure' => 'The :attribute field must have the form RB/COT/18 B 22272.',
        'invalid_country' => 'The :attribute field must start with RB.',
        'unknown_registry' => 'The :attribute field indicates an unknown registry.',
        'unknown_category' => 'The :attribute field must have a category letter from A to E.',
        'invalid_year' => 'The :attribute field contains an invalid year.',
        'not_found' => 'The RCCM number specified in :attribute is absent from the APIEx directory.',
        'unavailable' => 'The RCCM number specified in :attribute cannot be verified at the moment.',
        'verification_disabled' => 'Online verification of RCCM numbers is disabled.',
    ],
];
