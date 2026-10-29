<?php

return [

    // When true, new reviews wait for admin approval before they appear publicly.
    'reviews_require_approval' => env('REVIEWS_REQUIRE_APPROVAL', false),

    // Days an admin sign-in lasts in the admin panel before it asks again (members stay signed in). 0 = no limit.
    'admin_session_days' => (int) env('ADMIN_SESSION_DAYS', 7),

    /*
     | Site languages. The first one is the base language: content typed in the
     | main admin fields is in this language, the others are stored as translations.
     | To add a language: add it here and create lang/{code}.json + lang/{code}/trust.php.
     */
    'locales' => [
        'ar' => ['name' => 'العربية', 'dir' => 'rtl'],
        'en' => ['name' => 'English', 'dir' => 'ltr'],
    ],

    // Labels for these keys live in lang/{locale}/trust.php
    'socials' => [
        'telegram_url' => 'telegram',
        'website_url' => 'globe',
        'twitter_url' => 'x',
        'instagram_url' => 'instagram',
        'youtube_url' => 'youtube',
        'tiktok_url' => 'tiktok',
        'facebook_url' => 'facebook',
    ],
];
