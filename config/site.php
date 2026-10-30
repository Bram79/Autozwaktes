<?php

return [
    'name' => 'Autozwaktes.nl',

    // Groups with fewer vehicles than this are not shown: the percentages would be unreliable.
    'min_sample' => (int) env('SITE_MIN_SAMPLE', 100),

    // How many defects the top list shows.
    'top_defects' => 10,

    // Comma separated e-mail addresses that may open /admin outside the local environment.
    'admin_emails' => array_filter(array_map(fn ($e) => strtolower(trim($e)), explode(',', env('ADMIN_EMAILS', '')))),

    /*
     * Affiliate blocks. Set 'url' to your own affiliate link and 'enabled' to true
     * to show a block. Blocks without a url are never shown.
     * Use {merk}, {model} and {jaar} in the url if the partner supports it.
     */
    'affiliates' => [
        'vehicle_report' => [
            'enabled' => true,
            'title' => 'Kentekenrapport opvragen',
            'text' => 'Check de historie van de auto voordat je koopt: kilometerstanden, schades en eerdere eigenaren.',
            'button' => 'Bekijk kentekenrapport',
            'url' => env('AFFILIATE_VEHICLE_REPORT_URL'),
        ],
        'insurance' => [
            'enabled' => true,
            'title' => 'Autoverzekering vergelijken',
            'text' => 'Vergelijk verzekeringen voor je {merk} {model} en kijk wat je kunt besparen.',
            'button' => 'Vergelijk verzekeringen',
            'url' => env('AFFILIATE_INSURANCE_URL'),
        ],
        'parts' => [
            'enabled' => true,
            'title' => 'Onderdelen voor je {merk} {model}',
            'text' => 'Zoek passende onderdelen voor onderhoud en reparatie.',
            'button' => 'Zoek onderdelen',
            'url' => env('AFFILIATE_PARTS_URL'),
        ],
    ],
];
