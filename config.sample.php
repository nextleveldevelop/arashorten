<?php
declare(strict_types=1);

/*
| Sample configuration. The web installer writes config.local.php for you.
| For a manual install: copy this file to config.local.php and edit it.
| Never commit config.local.php to Git.
*/

return [
    'app_name'    => 'AraShorten',
    'app_url'     => 'https://example.com',   // no trailing slash
    'app_env'     => 'production',
    'timezone'    => 'UTC',

    'db_host'     => 'localhost',
    'db_name'     => 'your_database',
    'db_user'     => 'your_db_user',
    'db_pass'     => 'your_db_password',

    // Random secret (used to hash rate-limit keys). Generate with:
    //   php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    'app_key'     => 'CHANGE_ME_64_HEX_CHARS',

    // Redirect http -> https. Set false only if TLS is terminated by a proxy
    // that does not send X-Forwarded-Proto.
    'force_https' => true,

    // OPTIONAL. Quill editor loaded from the CDN: let the browser verify the files.
    // Get both values with:  php cron/install-quill.php --sri-only
    // (Not needed if you self-host Quill with:  php cron/install-quill.php)
    // 'quill_sri_js'  => 'sha384-...',
    // 'quill_sri_css' => 'sha384-...',

    // Optional tuning (defaults shown)
    'limits' => [
        'short_code_length'        => 8,      // random codes: 8 chars => 36^8 combinations
        'max_urls_per_user'        => 1000,
        'user_shorten_per_hour'    => 60,
        'guest_shorten_per_hour'   => 10,
        'login_failures'           => 5,      // per e-mail ...
        'login_window_seconds'     => 900,    // ... within 15 minutes
        'register_per_hour'        => 30,     // global
        'session_idle_seconds'     => 7200,
        'session_absolute_seconds' => 43200,
        'admin_session_seconds'    => 14400,  // admins must log in again after 4 h
        'click_retention_days'     => 0,      // 0 = keep forever
        'contact_retention_days'   => 0,      // delete contact messages after N days (0 = keep)
        'contact_per_hour'         => 5,      // contact messages per visitor session per hour
    ],
];
