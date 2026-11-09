<?php
declare(strict_types=1);

/**
 * Record a click without collecting visitor identifiers.
 */
function recordUrlClick(int $urlId): void
{
    $pdo = db();

    try {
        $pdo->beginTransaction();

        $statement = $pdo->prepare(
            'UPDATE urls
             SET clicks = clicks + 1
             WHERE id = :id'
        );

        $statement->execute([
            'id' => $urlId,
        ]);

        $statement = $pdo->prepare(
            'INSERT INTO url_clicks (url_id, clicked_at)
             VALUES (:url_id, UTC_TIMESTAMP())'
        );

        $statement->execute([
            'url_id' => $urlId,
        ]);

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Click analytics recording failed.');
    }
}

/**
 * Get total clicks for a URL.
 */
function getUrlClickCount(int $urlId, int $userId): int
{
    $statement = db()->prepare(
        'SELECT clicks
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

    return $result ? (int) $result['clicks'] : 0;
}

/**
 * Get daily click totals for a URL.
 */
function getDailyClicks(
    int $urlId,
    int $userId,
    int $days = 30
): array {
    $days = max(1, min(90, $days));

    $statement = db()->prepare(
        'SELECT DATE(c.clicked_at) AS click_date,
                COUNT(*) AS total
         FROM url_clicks c
         INNER JOIN urls u ON u.id = c.url_id
         WHERE u.id = :url_id
           AND u.user_id = :user_id
           AND c.clicked_at >= UTC_TIMESTAMP() - INTERVAL ' . $days . ' DAY
         GROUP BY DATE(c.clicked_at)
         ORDER BY click_date ASC'
    );

    $statement->execute([
        'url_id' => $urlId,
        'user_id' => $userId,
    ]);

    return $statement->fetchAll();
}

/**
 * Delete click rows older than the retention period (0 = keep forever).
 */
function purgeOldClicks(int $retentionDays): int
{
    if ($retentionDays < 1) {
        return 0;
    }

    $statement = db()->prepare(
        'DELETE FROM url_clicks
         WHERE clicked_at < UTC_TIMESTAMP() - INTERVAL ' . $retentionDays . ' DAY'
    );
    $statement->execute();

    return $statement->rowCount();
}

/**
 * Daily click totals for one URL. The caller is responsible for having
 * verified access (owner or admin) when it loaded the URL.
 */
function getDailyClicksByUrl(int $urlId, int $days = 30): array
{
    $days = max(1, min(365, $days));

    $statement = db()->prepare(
        'SELECT DATE(clicked_at) AS click_date, COUNT(*) AS total
         FROM url_clicks
         WHERE url_id = :url_id
           AND clicked_at >= UTC_TIMESTAMP() - INTERVAL ' . $days . ' DAY
         GROUP BY DATE(clicked_at)
         ORDER BY click_date ASC'
    );
    $statement->execute(['url_id' => $urlId]);

    return $statement->fetchAll();
}

/**
 * Site-wide daily series for the admin dashboard.
 *
 * @return array{labels: string[], values: int[]}
 */
function siteDailySeries(string $table, string $column, int $days = 30): array
{
    // $table/$column come only from the fixed allow-list below.
    $allowed = [
        'url_clicks' => 'clicked_at',
        'urls' => 'created_at',
        'users' => 'created_at',
    ];

    if (!isset($allowed[$table]) || $allowed[$table] !== $column) {
        return ['labels' => [], 'values' => []];
    }

    $days = max(1, min(90, $days));

    $rows = db()->query(
        'SELECT DATE(' . $column . ') AS d, COUNT(*) AS total
         FROM ' . $table . '
         WHERE ' . $column . ' >= UTC_TIMESTAMP() - INTERVAL ' . $days . ' DAY
         GROUP BY DATE(' . $column . ')'
    )->fetchAll();

    $map = [];

    foreach ($rows as $row) {
        $map[(string) $row['d']] = (int) $row['total'];
    }

    $labels = [];
    $values = [];

    for ($i = $days - 1; $i >= 0; $i--) {
        $date = gmdate('Y-m-d', time() - ($i * 86400));
        $labels[] = $date;
        $values[] = $map[$date] ?? 0;
    }

    return ['labels' => $labels, 'values' => $values];
}
