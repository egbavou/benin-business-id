<?php

return [
    'ifu' => [
        'invalid_characters' => 'Le champ :attribute ne doit contenir que des chiffres.',
        'invalid_length' => 'Le champ :attribute doit contenir 13 chiffres.',
        'invalid_checksum' => 'Le champ :attribute n\'est pas un IFU valide.',
        'unknown_taxpayer_type' => 'Le champ :attribute doit commencer par un chiffre de 0 à 6.',
        'invalid_year' => 'Le champ :attribute contient une année d\'immatriculation invalide.',
        'not_found' => 'L\'IFU indiqué dans :attribute est introuvable.',
        'unavailable' => 'L\'IFU indiqué dans :attribute ne peut pas être vérifié pour le moment.',
    ],
    'rccm' => [
        'invalid_characters' => 'Le champ :attribute contient des caractères non autorisés.',
        'invalid_length' => 'Le champ :attribute n\'a pas la longueur d\'un numéro RCCM.',
        'invalid_checksum' => 'Le champ :attribute n\'est pas un numéro RCCM valide.',
        'invalid_structure' => 'Le champ :attribute doit avoir la forme RB/COT/18 B 22272.',
        'invalid_country' => 'Le champ :attribute doit commencer par RB.',
        'unknown_registry' => 'Le champ :attribute indique un greffe inconnu.',
        'unknown_category' => 'Le champ :attribute doit porter une lettre de catégorie de A à E.',
        'invalid_year' => 'Le champ :attribute contient une année invalide.',
        'not_found' => 'Le numéro RCCM indiqué dans :attribute est absent de l\'annuaire de l\'APIEx.',
        'unavailable' => 'Le numéro RCCM indiqué dans :attribute ne peut pas être vérifié pour le moment.',
        'verification_disabled' => 'La vérification en ligne des numéros RCCM est désactivée.',
    ],
];
