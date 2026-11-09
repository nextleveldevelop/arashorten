<?php
declare(strict_types=1);

/*
| Define APP_SKIP_SESSION before requiring this file for endpoints that
| must not create sessions or cookies (e.g. the short-link redirector).
*/

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

enforceHttps();

sendSecurityHeaders();

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/rate-limit.php';
require_once __DIR__ . '/migrate.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/pages-service.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/contact-service.php';
require_once __DIR__ . '/editor.php';

// The short-link redirector skips this to stay as fast as possible.
if (!defined('APP_SKIP_SCHEMA_CHECK')) {
    ensureSchema();
}

// Site-wide "do not index" switch (e.g. for staging) from the admin settings.
if (!defined('APP_SKIP_SCHEMA_CHECK') && setting('seo_robots_index', '1') === '0' && !headers_sent()) {
    header('X-Robots-Tag: noindex, nofollow');
}

if (!defined('APP_SKIP_SESSION')) {
    startSecureSession();

    // Never let browsers or proxies cache pages of a signed-in user.
    if (isset($_SESSION['user_id']) && !headers_sent()) {
        header('Cache-Control: no-store, private');
    }
}
