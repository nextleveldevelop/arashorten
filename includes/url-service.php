<?php
declare(strict_types=1);

require_once __DIR__ . '/url-validator.php';

const SHORT_CODE_MAX_ATTEMPTS = 10;

/**
 * Words that can never be used as a short code because they are real pages,
 * folders or common technical paths.
 */
function reservedShortCodes(): array
{
    return [
        'about', 'contact', 'privacy', 'terms', 'login', 'logout', 'register',
        'signup', 'signin', 'dashboard', 'profile', 'analytics', 'install',
        'index', 'redirect', 'actions', 'assets', 'includes', 'storage',
        'cron', 'database', 'admin', 'administrator', 'api', 'static', 'robots',
        'sitemap', 'favicon', 'health', 'status', 'help', 'support', 'www',
        'app', 'null', 'undefined', 'config', 'settings', 'account', 'user',
        'users', 'password', 'reset', 'verify', 'webhook', 'wp-admin',
        'wp-login', 'phpmyadmin', 'cpanel', 'mail', 'test', 'page', 'pages',
    ];
}

function isReservedShortCode(string $code): bool
{
    return in_array(strtolower($code), reservedShortCodes(), true);
}

/**
 * Public short URL for a code, e.g. https://example.com/jfhksl7d
 */
function shortUrlFor(string $code): string
{
    return APP_URL . '/' . rawurlencode($code);
}

/**
 * Create a new short URL.
 */
function createShortUrl(
    string $destination,
    ?int $userId = null,
    ?string $customCode = null
): array {
    $validation = validateDestinationUrl($destination);

    if (!$validation['valid']) {
        return [
            'success' => false,
            'message' => $validation['message'],
        ];
    }

    $destination = $validation['url'];

    // Per-account quota
    if ($userId !== null) {
        $count = db()->prepare('SELECT COUNT(*) FROM urls WHERE user_id = :user_id');
        $count->execute(['user_id' => $userId]);

        if ((int) $count->fetchColumn() >= LIMIT_MAX_URLS_PER_USER) {
            return [
                'success' => false,
                'message' => 'وصلت إلى الحد الأقصى لعدد الروابط المسموح به لحسابك.',
            ];
        }
    }

    // Rate limit (per account, or per session for guests)
    $allowed = $userId !== null
        ? rateLimitAllow('shorten-user', (string) $userId, LIMIT_USER_SHORTEN_PER_HOUR, 3600)
        : rateLimitAllow('shorten-guest', guestRateSubject(), LIMIT_GUEST_SHORTEN_PER_HOUR, 3600);

    if (!$allowed) {
        return [
            'success' => false,
            'message' => 'تجاوزت عدد الروابط المسموح به في الساعة. حاول لاحقاً.',
        ];
    }

    if ($customCode !== null && trim($customCode) !== '') {
        $customCode = trim($customCode);

        if (!preg_match('/^[a-zA-Z0-9_-]{3,32}$/', $customCode)) {
            return [
                'success' => false,
                'message' => 'الرمز المخصص يجب أن يحتوي على 3 إلى 32 حرفاً أو رقماً أو - أو _.',
            ];
        }

        if (isReservedShortCode($customCode)) {
            return [
                'success' => false,
                'message' => 'هذا الرمز محجوز للنظام. اختر رمزاً آخر.',
            ];
        }

        if (shortCodeExists($customCode)) {
            return [
                'success' => false,
                'message' => 'هذا الرمز مستخدم بالفعل.',
            ];
        }

        $code = $customCode;
    } else {
        $code = null;

        for ($attempt = 0; $attempt < SHORT_CODE_MAX_ATTEMPTS; $attempt++) {
            $candidate = generateShortCode(SHORT_CODE_LENGTH_CONFIG);

            if (!isReservedShortCode($candidate) && !shortCodeExists($candidate)) {
                $code = $candidate;
                break;
            }
        }

        if ($code === null) {
            return [
                'success' => false,
                'message' => 'تعذر إنشاء رابط مختصر. يرجى المحاولة مجدداً.',
            ];
        }
    }

    try {
        $statement = db()->prepare(
            'INSERT INTO urls
                (user_id, short_code, destination_url, created_at)
             VALUES
                (:user_id, :short_code, :destination_url, UTC_TIMESTAMP())'
        );

        $statement->execute([
            'user_id' => $userId,
            'short_code' => $code,
            'destination_url' => $destination,
        ]);

        return [
            'success' => true,
            'id' => (int) db()->lastInsertId(),
            'code' => $code,
            'short_url' => shortUrlFor($code),
            'message' => 'تم إنشاء الرابط المختصر بنجاح.',
        ];
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            return [
                'success' => false,
                'message' => 'هذا الرمز مستخدم بالفعل. حاول مرة أخرى.',
            ];
        }

        error_log('Short URL creation failed.');

        return [
            'success' => false,
            'message' => 'حدث خطأ أثناء إنشاء الرابط.',
        ];
    }
}

/**
 * Check whether a short code already exists.
 */
function shortCodeExists(string $code): bool
{
    $statement = db()->prepare(
        'SELECT id FROM urls WHERE short_code = :code LIMIT 1'
    );

    $statement->execute([
        'code' => $code,
    ]);

    return (bool) $statement->fetch();
}

/**
 * Retrieve an active short URL.
 */
function findShortUrl(string $code): ?array
{
    $statement = db()->prepare(
        'SELECT id, user_id, short_code, destination_url, created_at
         FROM urls
         WHERE short_code = :code
           AND is_active = 1
         LIMIT 1'
    );

    $statement->execute([
        'code' => $code,
    ]);

    $result = $statement->fetch();

    return $result ?: null;
}

/**
 * Retrieve a URL owned by a user (for analytics page).
 */
function findUserUrl(int $urlId, int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT id, short_code, destination_url, clicks, is_active, created_at
         FROM urls
         WHERE id = :id
           AND user_id = :user_id
         LIMIT 1'
    );

    $statement->execute([
        'id' => $urlId,
        'user_id' => $userId,
    ]);

    $result = $statement->fetch();

    return $result ?: null;
}

/**
 * Any URL by id (admin only; callers must check isAdmin()).
 */
function findUrlById(int $urlId): ?array
{
    $statement = db()->prepare(
        'SELECT id, user_id, short_code, destination_url, clicks, is_active, created_at
         FROM urls WHERE id = :id LIMIT 1'
    );
    $statement->execute(['id' => $urlId]);

    return $statement->fetch() ?: null;
}

/**
 * Delete a URL only when it belongs to the given user.
 */
function deleteUserUrl(int $urlId, int $userId): bool
{
    $statement = db()->prepare(
        'DELETE FROM urls
         WHERE id = :id
           AND user_id = :user_id'
    );

    $statement->execute([
        'id' => $urlId,
        'user_id' => $userId,
    ]);

    return $statement->rowCount() === 1;
}
