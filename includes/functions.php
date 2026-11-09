<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| HTML escaping
|--------------------------------------------------------------------------
*/

function e(mixed $value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| URL helper
|--------------------------------------------------------------------------
*/

function url(string $path = ''): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

/*
|--------------------------------------------------------------------------
| Redirect helper
|--------------------------------------------------------------------------
*/

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

/*
|--------------------------------------------------------------------------
| Flash messages
|--------------------------------------------------------------------------
*/

function setFlash(string $type, string $message): void
{
    $allowedTypes = ['success', 'error', 'warning', 'info'];

    if (!in_array($type, $allowedTypes, true)) {
        $type = 'info';
    }

    $_SESSION['_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function getFlash(): ?array
{
    if (empty($_SESSION['_flash'])) {
        return null;
    }

    $flash = $_SESSION['_flash'];

    unset($_SESSION['_flash']);

    return $flash;
}

/*
|--------------------------------------------------------------------------
| Random short code
|--------------------------------------------------------------------------
*/

function generateShortCode(int $length = 8): string
{
    // Lowercase letters and digits only: easy to read, type and share.
    $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';

    $max = strlen($characters) - 1;

    $code = '';

    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, $max)];
    }

    return $code;
}

/*
|--------------------------------------------------------------------------
| Application setting
|--------------------------------------------------------------------------
*/

function allSettings(bool $refresh = false): array
{
    static $cache = null;

    if ($cache === null || $refresh) {
        $cache = [];

        try {
            $statement = db()->query(
                'SELECT setting_key, setting_value FROM settings'
            );

            foreach ($statement->fetchAll() as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $exception) {
            $cache = [];
        }
    }

    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = allSettings();

    return isset($all[$key]) && $all[$key] !== ''
        ? (string) $all[$key]
        : $default;
}

function saveSetting(string $key, string $value): void
{
    $statement = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value)
         VALUES (:k, :v)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );

    $statement->execute(['k' => $key, 'v' => $value]);

    allSettings(true);
}

/*
|--------------------------------------------------------------------------
| Pagination helper
|--------------------------------------------------------------------------
*/

function paginationOffset(int $page, int $perPage): int
{
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));

    return ($page - 1) * $perPage;
}

/*
|--------------------------------------------------------------------------
| Request method
|--------------------------------------------------------------------------
*/

function isPost(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/*
|--------------------------------------------------------------------------
| Safe request input
|--------------------------------------------------------------------------
| trim() on an array (e.g. ?name[]=x) throws a TypeError in PHP 8 and would
| produce a 500 error. These helpers always return a plain string.
*/

function postString(string $key, int $maxBytes = 10000): string
{
    $value = $_POST[$key] ?? '';

    if (!is_string($value)) {
        return '';
    }

    return substr($value, 0, $maxBytes);
}

function trimmedPost(string $key, int $maxBytes = 10000): string
{
    return trim(postString($key, $maxBytes));
}

/*
|--------------------------------------------------------------------------
| Bot trap (honeypot)
|--------------------------------------------------------------------------
| Real users never see or fill this field (hidden with CSS).
*/

function honeypotField(): string
{
    return '<div class="hp-field" aria-hidden="true">' .
        '<label for="website_url">Leave this field empty</label>' .
        '<input type="text" id="website_url" name="website_url" ' .
        'value="" tabindex="-1" autocomplete="off"></div>';
}

function honeypotTriggered(): bool
{
    return trimmedPost('website_url', 200) !== '';
}

/*
|--------------------------------------------------------------------------
| Meta robots / asset versioning
|--------------------------------------------------------------------------
*/

function assetUrl(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $version = is_file($file) ? (string) filemtime($file) : '1';

    return url($path) . '?v=' . $version;
}
