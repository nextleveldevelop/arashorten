<?php
declare(strict_types=1);

require_once __DIR__ . '/analytics.php';
require_once __DIR__ . '/url-service.php';

/*
|--------------------------------------------------------------------------
| Helpers shared by the admin pages
|--------------------------------------------------------------------------
*/

/**
 * True for a POST request that passed CSRF validation (exits with 403 otherwise).
 */
function adminIsPost(): bool
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        return false;
    }

    verifyCsrfToken();

    return true;
}

function adminPageNumber(): int
{
    $page = filter_var(
        $_GET['page'] ?? 1,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 100000]]
    );

    return $page === false ? 1 : $page;
}

function adminQueryParam(string $key, int $maxLength = 100): string
{
    $value = $_GET[$key] ?? '';

    return is_string($value) ? trim(mb_substr($value, 0, $maxLength)) : '';
}

/**
 * Escape a user search term for use inside LIKE.
 */
function likeTerm(string $term): string
{
    return '%' . addcslashes($term, '%_\\') . '%';
}

function adminBadge(string $text, string $type = 'muted'): string
{
    $allowed = ['success', 'danger', 'warning', 'info', 'muted', 'primary'];

    if (!in_array($type, $allowed, true)) {
        $type = 'muted';
    }

    return '<span class="badge badge-' . $type . '">' . e($text) . '</span>';
}

function formatUtc(?string $dateTime): string
{
    if ($dateTime === null || $dateTime === '') {
        return '—';
    }

    return e(substr($dateTime, 0, 16)) . ' UTC';
}

/**
 * Pagination links that keep the current filters.
 */
function adminPager(int $total, int $perPage, int $page, array $query = []): string
{
    $pages = (int) max(1, ceil($total / $perPage));

    if ($pages <= 1) {
        return '';
    }

    unset($query['page']);

    $html = '<nav class="admin-pager" aria-label="pagination">';

    $from = max(1, $page - 3);
    $to = min($pages, $page + 3);

    $link = static function (int $n, string $label, bool $current = false, string $iconHtml = '', string $aria = '') use ($query): string {
        $href = '?' . http_build_query($query + ['page' => $n]);

        return '<a href="' . e($href) . '"' .
            ($current ? ' class="is-current" aria-current="page"' : '') .
            ($aria !== '' ? ' aria-label="' . e($aria) . '"' : '') . '>' .
            ($iconHtml !== '' ? $iconHtml : e($label)) . '</a>';
    };

    if ($page > 1) {
        $html .= $link($page - 1, '', false, icon('chevron-right'), 'الصفحة السابقة');
    }

    if ($from > 1) {
        $html .= $link(1, '1');

        if ($from > 2) {
            $html .= '<span>…</span>';
        }
    }

    for ($n = $from; $n <= $to; $n++) {
        $html .= $link($n, (string) $n, $n === $page);
    }

    if ($to < $pages) {
        if ($to < $pages - 1) {
            $html .= '<span>…</span>';
        }

        $html .= $link($pages, (string) $pages);
    }

    if ($page < $pages) {
        $html .= $link($page + 1, '', false, icon('chevron-left'), 'الصفحة التالية');
    }

    return $html . '</nav>';
}

/**
 * Aggregated numbers for the admin dashboard.
 */
function adminStats(): array
{
    $pdo = db();

    $one = static fn (string $sql): int => (int) $pdo->query($sql)->fetchColumn();

    return [
        'users_total' => $one('SELECT COUNT(*) FROM users'),
        'admins' => $one("SELECT COUNT(*) FROM users WHERE role = 'admin'"),
        'users_inactive' => $one('SELECT COUNT(*) FROM users WHERE is_active = 0'),
        'users_7d' => $one('SELECT COUNT(*) FROM users WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY'),
        'links_total' => $one('SELECT COUNT(*) FROM urls'),
        'links_active' => $one('SELECT COUNT(*) FROM urls WHERE is_active = 1'),
        'links_disabled' => $one('SELECT COUNT(*) FROM urls WHERE is_active = 0'),
        'links_guest' => $one('SELECT COUNT(*) FROM urls WHERE user_id IS NULL'),
        'links_7d' => $one('SELECT COUNT(*) FROM urls WHERE created_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY'),
        'clicks_total' => $one('SELECT COALESCE(SUM(clicks), 0) FROM urls'),
        'clicks_today' => $one('SELECT COUNT(*) FROM url_clicks WHERE clicked_at >= UTC_DATE()'),
        'clicks_7d' => $one('SELECT COUNT(*) FROM url_clicks WHERE clicked_at >= UTC_TIMESTAMP() - INTERVAL 7 DAY'),
        'clicks_30d' => $one('SELECT COUNT(*) FROM url_clicks WHERE clicked_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY'),
        'pages_total' => $one('SELECT COUNT(*) FROM pages'),
        'pages_published' => $one('SELECT COUNT(*) FROM pages WHERE is_published = 1'),
    ];
}

/**
 * Production readiness checks shown on the admin dashboard.
 *
 * @return array<int, array{label:string, status:string, detail:string}>
 */
function systemChecks(): array
{
    $checks = [];

    $add = static function (string $label, string $status, string $detail) use (&$checks): void {
        $checks[] = ['label' => $label, 'status' => $status, 'detail' => $detail];
    };

    $add(
        'إصدار PHP',
        version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'warn',
        PHP_VERSION . (version_compare(PHP_VERSION, '8.1.0', '>=') ? '' : ' (يُنصح بـ 8.1+)')
    );

    $missing = array_filter(
        ['pdo_mysql', 'mbstring', 'dom', 'openssl'],
        static fn (string $ext): bool => !extension_loaded($ext)
    );

    $add(
        'امتدادات PHP المطلوبة',
        $missing ? 'warn' : 'ok',
        $missing ? 'مفقودة: ' . implode(', ', $missing) : 'pdo_mysql, mbstring, dom, openssl'
    );

    $add(
        'HTTPS',
        (APP_IS_HTTPS && requestIsHttps()) ? 'ok' : 'warn',
        APP_IS_HTTPS ? 'app_url يبدأ بـ https' : 'غيّر app_url إلى https:// في config.local.php'
    );

    $add(
        'ملف install.php محذوف',
        is_file(APP_ROOT . '/install.php') ? 'warn' : 'ok',
        is_file(APP_ROOT . '/install.php') ? 'احذف install.php من الخادم' : 'تم حذفه'
    );

    $config = APP_ROOT . '/config.local.php';
    $worldReadable = is_file($config) && ((fileperms($config) & 0004) !== 0);

    $add(
        'صلاحيات config.local.php',
        $worldReadable ? 'warn' : 'ok',
        $worldReadable ? 'الملف قابل للقراءة من الجميع، اجعله 640 أو 600' : 'مقيّدة'
    );

    $sessions = APP_ROOT . '/storage/sessions';

    $add(
        'مجلد الجلسات الخاص',
        (is_dir($sessions) && is_writable($sessions)) ? 'ok' : 'warn',
        (is_dir($sessions) && is_writable($sessions)) ? 'storage/sessions' : 'غير قابل للكتابة: تُستخدم جلسات النظام الافتراضية'
    );

    $add(
        'بيئة التشغيل',
        APP_ENV === 'production' ? 'ok' : 'warn',
        APP_ENV
    );

    $add(
        'عرض أخطاء PHP للزوار',
        'ok',
        'معطّل (display_errors مُغلق من التطبيق)'
    );

    $add(
        'محرر Quill ' . QUILL_VERSION,
        'ok',
        quillLocalInstalled()
            ? 'مستضاف على موقعك (أعلى درجات الأمان)'
            : (QUILL_SRI_JS !== '' && QUILL_SRI_CSS !== ''
                ? 'يُحمَّل من CDN مع فحص سلامة الملفات (SRI)'
                : 'يُحمَّل من CDN (jsDelivr) بإصدار ثابت. للأمان الأعلى نفّذ: php cron/install-quill.php')
    );

    try {
        $version = (string) db()->query('SELECT VERSION()')->fetchColumn();
    } catch (Throwable $exception) {
        $version = '?';
    }

    $add('قاعدة البيانات', 'info', $version . ' — schema v' . setting('schema_version', '?'));

    return $checks;
}

/**
 * Admin accounts that can still sign in.
 */
function countActiveAdmins(): int
{
    return (int) db()->query(
        "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1"
    )->fetchColumn();
}
