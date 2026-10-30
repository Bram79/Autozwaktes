<?php

return [
    'name' => 'Auto Zwaktes',

    // Contact address shown on the privacy page (set CONTACT_EMAIL in .env).
    'contact_email' => env('CONTACT_EMAIL'),

    /*
     * Optional: let Claude write a draft of the buying advice from our own statistics (php artisan advice:draft).
     * Off by default. A draft never goes live before a person approves it in the admin.
     */
    'ai' => [
        'enabled' => (bool) env('AI_ADVICE_ENABLED', false),
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('AI_ADVICE_MODEL', 'claude-sonnet-5-5'),
    ],

    /*
     * Advertisements: ready, but off. Switch on with ADS_ENABLED=true and ADS_SCRIPT_URL (the script of your ad network).
     * The script is only loaded after the visitor accepts it in the cookie banner.
     */
    'ads' => [
        'enabled' => (bool) env('ADS_ENABLED', false),
        'script_url' => env('ADS_SCRIPT_URL'),
    ],

    // The cookie banner is shown when ads are on, or when you force it (for example for analytics you add later).
    'consent_banner' => (bool) env('CONSENT_BANNER', false),

    // Groups with fewer vehicles than this are not shown: the percentages would be unreliable.
    'min_sample' => (int) env('SITE_MIN_SAMPLE', 100),

    // A generation needs at least this many cars to appear in a ranking.
    'min_ranking_sample' => (int) env('SITE_MIN_RANKING_SAMPLE', 500),

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
        // The partner block in the APK reminder e-mail. Off until you have a partner that fits.
        'apk_mail' => [
            'enabled' => false,
            'title' => 'Binnenkort APK? Laat je auto eerst nakijken',
            'text' => 'Vergelijk garages bij jou in de buurt en ontdek wat een keuring kost.',
            'button' => 'Bekijk garages',
            'url' => env('AFFILIATE_APK_MAIL_URL'),
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
