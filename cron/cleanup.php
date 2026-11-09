<?php
declare(strict_types=1);

/*
| Housekeeping job. Run from cron, once a day:
|
|   0 3 * * *  /usr/bin/php /home/USER/public_html/cron/cleanup.php
|
| Removes expired rate-limit counters and (optionally) old click rows and
| old contact messages (limits.click_retention_days / contact_retention_days).
| Command line only: the web server also blocks the /cron folder.
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('APP_SKIP_SESSION', true);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/analytics.php';

$rate = rateLimitPurge(86400);
$clicks = purgeOldClicks(CLICK_RETENTION_DAYS);
$messages = purgeOldContactMessages(CONTACT_RETENTION_DAYS);

echo date('c') . " rate_limits removed: {$rate}, url_clicks removed: {$clicks}, contact_messages removed: {$messages}\n";
