<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Application configuration loader
|--------------------------------------------------------------------------
*/

define('APP_ROOT', dirname(__DIR__));

$configFile = APP_ROOT . '/config.local.php';

if (!is_file($configFile)) {
    http_response_code(503);

    exit(
        'التطبيق غير مثبت. يرجى تشغيل install.php أولاً.'
    );
}

$config = require $configFile;

if (!is_array($config)) {
    http_response_code(500);
    exit('ملف الإعدادات غير صالح.');
}

$required = [
    'app_name',
    'app_url',
    'app_env',
    'db_host',
    'db_name',
    'db_user',
    'db_pass',
];

foreach ($required as $key) {
    if (!array_key_exists($key, $config)) {
        http_response_code(500);
        exit('إعداد مفقود: ' . htmlspecialchars($key));
    }
}

define('APP_NAME', (string) $config['app_name']);
define('APP_URL', rtrim((string) $config['app_url'], '/'));
define('APP_ENV', (string) $config['app_env']);

define('DB_HOST', (string) $config['db_host']);
define('DB_NAME', (string) $config['db_name']);
define('DB_USER', (string) $config['db_user']);
define('DB_PASS', (string) $config['db_pass']);

define('APP_TIMEZONE', (string) ($config['timezone'] ?? 'UTC'));

date_default_timezone_set(APP_TIMEZONE);

/*
| Secret used to hash rate-limit keys (never stored in clear text).
| Installs created before v2.1 have no app_key, so derive a stable one.
*/
define(
    'APP_KEY',
    (string) ($config['app_key'] ?? hash('sha256', DB_NAME . '|' . DB_USER . '|' . DB_PASS))
);

/*
| HTTPS handling: derived from app_url, never from guessing.
| Set 'force_https' => false in config.local.php if your proxy does not
| send X-Forwarded-Proto and you terminate TLS in front of PHP.
*/
define('APP_IS_HTTPS', stripos(APP_URL, 'https://') === 0);
define('APP_FORCE_HTTPS', APP_IS_HTTPS && ($config['force_https'] ?? true) === true);

/*
| Tunable limits (all optional).
*/
$limits = is_array($config['limits'] ?? null) ? $config['limits'] : [];

define('LIMIT_MAX_URLS_PER_USER', max(1, (int) ($limits['max_urls_per_user'] ?? 1000)));
define('LIMIT_USER_SHORTEN_PER_HOUR', max(1, (int) ($limits['user_shorten_per_hour'] ?? 60)));
define('LIMIT_GUEST_SHORTEN_PER_HOUR', max(1, (int) ($limits['guest_shorten_per_hour'] ?? 10)));
define('LIMIT_LOGIN_FAILURES', max(3, (int) ($limits['login_failures'] ?? 5)));
define('LIMIT_LOGIN_WINDOW', max(60, (int) ($limits['login_window_seconds'] ?? 900)));
define('LIMIT_REGISTER_PER_HOUR', max(1, (int) ($limits['register_per_hour'] ?? 30)));
define('SESSION_IDLE_SECONDS', max(300, (int) ($limits['session_idle_seconds'] ?? 7200)));
define('SESSION_ABSOLUTE_SECONDS', max(900, (int) ($limits['session_absolute_seconds'] ?? 43200)));
define('ADMIN_SESSION_SECONDS', max(900, (int) ($limits['admin_session_seconds'] ?? 14400)));
define('SHORT_CODE_LENGTH_CONFIG', min(16, max(6, (int) ($limits['short_code_length'] ?? 8))));
define('CLICK_RETENTION_DAYS', max(0, (int) ($limits['click_retention_days'] ?? 0)));
define('CONTACT_RETENTION_DAYS', max(0, (int) ($limits['contact_retention_days'] ?? 0)));
define('LIMIT_CONTACT_PER_HOUR', max(1, (int) ($limits['contact_per_hour'] ?? 5)));

/*
| Optional Subresource Integrity hashes for the Quill editor files when they
| are loaded from the CDN (see includes/editor.php and cron/install-quill.php,
| which prints both values). Ignored unless they look like a valid SRI value.
*/
$sriPattern = '/^sha(256|384|512)-[A-Za-z0-9+\/]{20,200}={0,2}$/';

define(
    'QUILL_SRI_JS',
    preg_match($sriPattern, (string) ($config['quill_sri_js'] ?? '')) === 1 ? (string) $config['quill_sri_js'] : ''
);
define(
    'QUILL_SRI_CSS',
    preg_match($sriPattern, (string) ($config['quill_sri_css'] ?? '')) === 1 ? (string) $config['quill_sri_css'] : ''
);

/*
| Never show PHP errors to visitors. Errors go to the server log.
*/
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
