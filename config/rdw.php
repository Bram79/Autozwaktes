<?php

return [
    'base_url' => 'https://opendata.rdw.nl',

    // Optional free Socrata app token: raises the rate limit. Get one at opendata.rdw.nl.
    'app_token' => env('RDW_APP_TOKEN'),

    // Rows per request / per queue job.
    'chunk_size' => (int) env('RDW_CHUNK_SIZE', 50000),

    // Slow queries (counts, deep offsets) can take 30+ seconds.
    'timeout' => 180,

    'datasets' => [
        'defect_codes' => [
            'id' => 'hx2c-gt7k',
            'columns' => ['gebrek_identificatie', 'gebrek_omschrijving'],
            'optional_columns' => ['gebrek_artikel_nummer', 'ingangsdatum_gebrek_dt', 'einddatum_gebrek_dt'],
            'where' => null,
        ],
        'vehicles' => [
            'id' => 'm9d7-ebf2',
            'columns' => ['kenteken', 'voertuigsoort', 'merk', 'handelsbenaming', 'datum_eerste_toelating_dt'],
            'optional_columns' => [],
            'where' => "voertuigsoort = 'Personenauto'",
        ],
        'fuel' => [
            'id' => '8ys7-d773',
            'columns' => ['kenteken', 'brandstof_volgnummer', 'brandstof_omschrijving'],
            'optional_columns' => [],
            'where' => "brandstof_volgnummer in ('1', '2')",
        ],
        'defects' => [
            'id' => 'a34c-vvps',
            'columns' => ['kenteken', 'gebrek_identificatie', 'meld_datum_door_keuringsinstantie_dt', 'aantal_gebreken_geconstateerd', 'soort_erkenning_omschrijving'],
            'optional_columns' => [],
            'where' => "soort_erkenning_omschrijving = 'APK Lichte voertuigen'",
        ],
    ],
];
