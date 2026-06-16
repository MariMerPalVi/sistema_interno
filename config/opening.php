<?php

return [
    'latest_election_year' => env('LATEST_ELECTION_YEAR', '2025'),
    'ocr_enabled' => env('OCR_ENABLED', false),
    'ocr_on_upload' => env('OCR_ON_UPLOAD', false),
    'ocr_on_demand' => env('OCR_ON_DEMAND', true),
    'rescan_stored_documents' => env('RESCAN_STORED_DOCUMENTS', false),
    'default_agency' => env('DEFAULT_AGENCY', 'matriz-las-naves'),
    'agencies' => [
        'matriz-las-naves' => [
            'name' => 'Matriz - Las Naves',
            'folder' => 'Matriz - Las Naves',
        ],
        'echeandia' => [
            'name' => 'Echeandía',
            'folder' => 'Echeandía',
        ],
        'caluma' => [
            'name' => 'Caluma',
            'folder' => 'Caluma',
        ],
        'san-jose-del-tambo' => [
            'name' => 'San José del Tambo',
            'folder' => 'San José del Tambo',
        ],
        'montalvo' => [
            'name' => 'Montalvo',
            'folder' => 'Montalvo',
        ],
        'quinsaloma' => [
            'name' => 'Quinsaloma',
            'folder' => 'Quinsaloma',
        ],
    ],
];
