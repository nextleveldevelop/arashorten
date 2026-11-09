<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| HTTPS enforcement
|--------------------------------------------------------------------------
*/

function requestIsHttps(): bool
{
    if (
        isset($_SERVER['HTTPS']) &&
        $_SERVER['HTTPS'] !== '' &&
        strtolower((string) $_SERVER['HTTPS']) !== 'off'
    ) {
        return true;
    }

    // Behind a reverse proxy / CDN (Cloudflare, load balancer, ...)
    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

    return $forwarded === 'https';
}

function enforceHttps(): void
{
    if (
        !APP_FORCE_HTTPS ||
        PHP_SAPI === 'cli' ||
        requestIsHttps()
    ) {
        return;
    }

    // Always redirect to the configured canonical URL, never to a
    // host taken from the request (prevents Host-header abuse).
    $path = (string) ($_SERVER['REQUEST_URI'] ?? '/');

    if ($path === '' || $path[0] !== '/' || preg_match('/[\r\n]/', $path)) {
        $path = '/';
    }

    $parts = parse_url(APP_URL);
    $origin = 'https://' . ($parts['host'] ?? '') .
        (isset($parts['port']) ? ':' . $parts['port'] : '');

    header('Location: ' . $origin . $path, true, 301);
    exit;
}

/*
|--------------------------------------------------------------------------
| Secure session configuration
|--------------------------------------------------------------------------
*/

function sessionStoragePath(): ?string
{
    // A private session folder avoids sharing /tmp with other hosting accounts.
    $path = APP_ROOT . '/storage/sessions';

    if (!is_dir($path)) {
        @mkdir($path, 0700, true);
    }

    return (is_dir($path) && is_writable($path)) ? $path : null;
}

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', (string) SESSION_ABSOLUTE_SECONDS);

    $path = sessionStoragePath();

    if ($path !== null) {
        session_save_path($path);

        // Distro PHP packages often disable GC for the default path
        // (they clean it with cron); make sure our private folder is cleaned.
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
    }

    session_name('ARASHORTEN_SESSION');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => APP_IS_HTTPS,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    enforceSessionLifetime();
}

/**
 * Expire sessions after inactivity and after an absolute lifetime.
 */
function enforceSessionLifetime(): void
{
    $now = time();

    $lastActivity = (int) ($_SESSION['_last_activity'] ?? $now);
    $startedAt = (int) ($_SESSION['authenticated_at'] ?? $now);

    if (
        isset($_SESSION['user_id']) &&
        (
            ($now - $lastActivity) > SESSION_IDLE_SECONDS ||
            ($now - $startedAt) > SESSION_ABSOLUTE_SECONDS
        )
    ) {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    $_SESSION['_last_activity'] = $now;
}

/*
|--------------------------------------------------------------------------
| Security headers
|--------------------------------------------------------------------------
*/

/**
 * Build the Content-Security-Policy header value.
 *
 * Every page uses the strict default (only the site's own files). The only
 * exception is the page editor, which may additionally load the two exact
 * Quill files from the CDN when no local copy is installed (see editor.php).
 * Exact file URLs are allowed, never a whole CDN host.
 *
 * @param string[] $extraScript extra script-src sources
 * @param string[] $extraStyle  extra style-src sources
 */
function contentSecurityPolicy(array $extraScript = [], array $extraStyle = []): string
{
    $script = implode(' ', array_merge(["'self'"], $extraScript));
    $style = implode(' ', array_merge(["'self'"], $extraStyle));

    $directives = [
        "default-src 'self'",
        'script-src ' . $script,
        'style-src ' . $style,
        "img-src 'self' data:",
        "font-src 'self'",
        "connect-src 'self'",
        "object-src 'none'",
        "frame-src 'none'",
        "worker-src 'none'",
        "manifest-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ];

    if (APP_IS_HTTPS) {
        $directives[] = 'upgrade-insecure-requests';
    }

    return implode('; ', $directives);
}

function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');

    header('Content-Security-Policy: ' . contentSecurityPolicy());

    if (APP_IS_HTTPS) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
