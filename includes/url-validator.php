<?php
declare(strict_types=1);

/**
 * Validate a destination URL.
 *
 * Only HTTP and HTTPS URLs are accepted.
 * No network request or DNS lookup is performed.
 */
function validateDestinationUrl(string $url): array
{
    $url = trim($url);

    if ($url === '') {
        return [
            'valid' => false,
            'message' => 'يرجى إدخال الرابط.',
        ];
    }

    if (strlen($url) > 2048) {
        return [
            'valid' => false,
            'message' => 'الرابط طويل جداً.',
        ];
    }

    if (preg_match('/[\x00-\x20\x7F]/', $url)) {
        return [
            'valid' => false,
            'message' => 'الرابط يحتوي على أحرف غير مسموح بها.',
        ];
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return [
            'valid' => false,
            'message' => 'صيغة الرابط غير صحيحة.',
        ];
    }

    $parts = parse_url($url);

    if (!is_array($parts)) {
        return [
            'valid' => false,
            'message' => 'تعذر تحليل الرابط.',
        ];
    }

    $scheme = strtolower($parts['scheme'] ?? '');

    if (!in_array($scheme, ['http', 'https'], true)) {
        return [
            'valid' => false,
            'message' => 'يسمح فقط بروابط HTTP و HTTPS.',
        ];
    }

    if (empty($parts['host'])) {
        return [
            'valid' => false,
            'message' => 'يجب أن يحتوي الرابط على اسم نطاق.',
        ];
    }

    if (isset($parts['user']) || isset($parts['pass'])) {
        return [
            'valid' => false,
            'message' => 'الروابط التي تحتوي على بيانات تسجيل دخول غير مسموح بها.',
        ];
    }

    $host = strtolower(rtrim($parts['host'], '.'));

    if (
        filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ||
        filter_var(trim($host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
    ) {
        return [
            'valid' => false,
            'message' => 'الروابط المباشرة إلى عناوين IP غير مسموح بها.',
        ];
    }

    // Internal / non-public host names
    if (
        $host === 'localhost' ||
        strpos($host, '.') === false ||
        preg_match('/\.(local|localhost|internal|lan|intranet|home|corp)$/', $host)
    ) {
        return [
            'valid' => false,
            'message' => 'الروابط إلى عناوين داخلية أو محلية غير مسموح بها.',
        ];
    }

    // Domains blocked by the administrator (Admin > Settings)
    foreach (blockedDomains() as $blocked) {
        if ($host === $blocked || substr($host, -strlen('.' . $blocked)) === '.' . $blocked) {
            return [
                'valid' => false,
                'message' => 'هذا النطاق محظور ولا يمكن اختصار روابطه.',
            ];
        }
    }

    // Prevent links that point back to this service (redirect loops / chaining).
    $appHost = strtolower((string) parse_url(APP_URL, PHP_URL_HOST));

    if ($appHost !== '' && ($host === $appHost || $host === 'www.' . $appHost)) {
        return [
            'valid' => false,
            'message' => 'لا يمكن اختصار روابط تابعة لهذا الموقع نفسه.',
        ];
    }

    return [
        'valid' => true,
        'url' => $url,
        'message' => '',
    ];
}

/**
 * Lower-case list of blocked domains from the admin settings.
 */
function blockedDomains(): array
{
    $list = [];

    foreach (preg_split('/\R/', setting('blocked_domains', '')) ?: [] as $line) {
        $line = strtolower(trim($line));

        if ($line !== '' && preg_match('/^[a-z0-9.-]{3,253}$/', $line)) {
            $list[] = $line;
        }
    }

    return $list;
}
