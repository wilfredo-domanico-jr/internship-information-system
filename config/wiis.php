<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product and institution branding
    |--------------------------------------------------------------------------
    */

    'name' => env('WIIS_NAME', 'WIIS'),
    'tagline' => env('WIIS_TAGLINE', 'Web Based Internship Information System'),

    'institution' => [
        'name' => env('WIIS_INSTITUTION_NAME', 'Quezon City University'),
        'short' => env('WIIS_INSTITUTION_SHORT', 'QCU'),
        'office' => env('WIIS_INSTITUTION_OFFICE', 'Scholarship, Placement and Alumni Relations Division'),
        // Path under public/ to a logo image, or null to render a text mark.
        'logo' => env('WIIS_INSTITUTION_LOGO'),
    ],

    'support' => [
        'email' => env('WIIS_SUPPORT_EMAIL', 'support@wiis.test'),
        'phone' => env('WIIS_SUPPORT_PHONE', '+63 900 000 0000'),
        'address' => env('WIIS_ADDRESS', 'Quezon City, Philippines'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OJT hour rules
    |--------------------------------------------------------------------------
    | required:        hours needed to complete the internship
    | certificate_min: hours at one company before it may issue a certificate
    | buckets:         [min, max|null, label] used by company dashboards
    */

    'hours' => [
        'required' => (int) env('WIIS_REQUIRED_HOURS', 486),
        'certificate_min' => (int) env('WIIS_CERTIFICATE_MIN_HOURS', 250),
        'buckets' => [
            [0, 250, '0–250'],
            [251, 300, '251–300'],
            [301, 400, '301–400'],
            [401, null, '401+'],
        ],
    ],

    'uploads' => [
        'max_pdf_kb' => 5120,
        'max_avatar_kb' => 1024,
    ],

    // Shows one-click demo sign-in buttons on the login page. Never enable in production.
    'demo_mode' => (bool) env('DEMO_MODE', false),

];
