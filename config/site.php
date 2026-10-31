<?php

return [
    'name' => 'Auto Zwaktes',

    // Who is responsible for the site, shown on the privacy page (OPERATOR_NAME, optionally OPERATOR_KVK), and the contact address (CONTACT_EMAIL).
    'operator_name' => env('OPERATOR_NAME'),
    'operator_kvk' => env('OPERATOR_KVK'),
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

    // Verification code from Google Search Console ("HTML tag" method). Leave empty until you have it.
    'google_verification' => env('GOOGLE_SITE_VERIFICATION'),

    // Content Security Policy: enforce, report-only (only try it) or off
    'csp' => env('CSP_MODE', 'enforce'),

    // When set, database backups are encrypted with this passphrase (store it somewhere safe, apart from the server)
    'backup_passphrase' => env('BACKUP_PASSPHRASE'),

    // At most this many parts links in one defect table (the top defects first)
    'parts_links_per_table' => 3,

    // Limits against abuse (all per minute unless it says otherwise). Overridable in .env for a busy launch day.
    'limits' => [
        'search_per_minute' => (int) env('LIMIT_SEARCH_PER_MINUTE', 60),
        'apk_mail_per_hour' => (int) env('LIMIT_APK_MAIL_PER_HOUR', 3),
        // all visitors together: the RDW is a shared, free service
        'rdw_lookups_per_minute' => (int) env('LIMIT_RDW_LOOKUPS_PER_MINUTE', 120),
    ],

    // Groups with fewer vehicles than this are not shown: the percentages would be unreliable.
    'min_sample' => (int) env('SITE_MIN_SAMPLE', 100),

    // A passenger car gets its first APK after this many years. Cars of a newer build year have no defects yet,
    // so they must not be compared with older cars (they would look perfect).
    'first_apk_after_years' => 4,

    // Rankings only look at cars built in the last this many years: of very old cars only the best kept ones are left,
    // which makes them look better than they are for someone who buys a used car.
    'ranking_max_age' => 20,

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
