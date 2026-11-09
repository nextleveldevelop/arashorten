<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Privacy-friendly rate limiting
|--------------------------------------------------------------------------
| Counters are keyed by an HMAC of an account identifier (e-mail, user id)
| or of the session id. IP addresses are NEVER used or stored, which keeps
| the privacy promise of this application.
|
| Limits of this approach (be aware of them):
|   * Guest limits keyed by session can be bypassed by dropping cookies.
|   * Credential stuffing across many different e-mails is not stopped here.
| For those, add edge protection (Cloudflare / WAF / fail2ban) as described
| in README.md.
*/

function rateLimitKey(string $scope, string $subject): string
{
    return hash_hmac('sha256', $scope . '|' . strtolower($subject), APP_KEY);
}

function ensureRateLimitTable(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS rate_limits (
            bucket_key CHAR(64) NOT NULL PRIMARY KEY,
            hits INT UNSIGNED NOT NULL DEFAULT 0,
            window_start DATETIME NOT NULL,
            INDEX idx_rate_window (window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/**
 * Register one hit and return the number of hits in the current window.
 * Returns 0 if the limiter is unavailable (fails open, error is logged).
 */
function rateLimitHit(string $scope, string $subject, int $windowSeconds): int
{
    $key = rateLimitKey($scope, $subject);
    $window = max(1, $windowSeconds);

    $sql = 'INSERT INTO rate_limits (bucket_key, hits, window_start)
            VALUES (:key, 1, UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE
                hits = IF(window_start < UTC_TIMESTAMP() - INTERVAL ' . $window . ' SECOND, 1, hits + 1),
                window_start = IF(window_start < UTC_TIMESTAMP() - INTERVAL ' . $window . ' SECOND, UTC_TIMESTAMP(), window_start)';

    for ($try = 0; $try < 2; $try++) {
        try {
            $statement = db()->prepare($sql);
            $statement->execute(['key' => $key]);

            return rateLimitCount($scope, $subject, $window);
        } catch (PDOException $exception) {
            // 42S02 = table missing (upgrade from v2.0): create it once and retry.
            if ($try === 0 && $exception->getCode() === '42S02') {
                try {
                    ensureRateLimitTable();
                    continue;
                } catch (Throwable $inner) {
                    // fall through to fail-open
                }
            }

            error_log('Rate limiter unavailable: ' . $exception->getCode());

            return 0;
        }
    }

    return 0;
}

/**
 * Number of hits currently counted in the window (without adding one).
 */
function rateLimitCount(string $scope, string $subject, int $windowSeconds): int
{
    $window = max(1, $windowSeconds);

    try {
        $statement = db()->prepare(
            'SELECT hits FROM rate_limits
             WHERE bucket_key = :key
               AND window_start >= UTC_TIMESTAMP() - INTERVAL ' . $window . ' SECOND
             LIMIT 1'
        );

        $statement->execute(['key' => rateLimitKey($scope, $subject)]);

        $hits = $statement->fetchColumn();

        return $hits === false ? 0 : (int) $hits;
    } catch (Throwable $exception) {
        return 0;
    }
}

/**
 * Count one attempt and tell whether it is still allowed.
 */
function rateLimitAllow(string $scope, string $subject, int $max, int $windowSeconds): bool
{
    return rateLimitHit($scope, $subject, $windowSeconds) <= $max;
}

function rateLimitReset(string $scope, string $subject): void
{
    try {
        $statement = db()->prepare('DELETE FROM rate_limits WHERE bucket_key = :key');
        $statement->execute(['key' => rateLimitKey($scope, $subject)]);
    } catch (Throwable $exception) {
        // ignore
    }
}

/**
 * Remove expired counters (called from cron/cleanup.php).
 */
function rateLimitPurge(int $olderThanSeconds = 86400): int
{
    try {
        $statement = db()->prepare(
            'DELETE FROM rate_limits
             WHERE window_start < UTC_TIMESTAMP() - INTERVAL ' . max(60, $olderThanSeconds) . ' SECOND'
        );
        $statement->execute();

        return $statement->rowCount();
    } catch (Throwable $exception) {
        return 0;
    }
}

/**
 * Subject used for guests: a hash input derived from the session id.
 */
function guestRateSubject(): string
{
    return session_status() === PHP_SESSION_ACTIVE ? session_id() : 'no-session';
}
