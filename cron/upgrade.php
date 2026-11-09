<?php
declare(strict_types=1);

/*
| Manual database upgrade (command line):
|
|   php cron/upgrade.php
|
| Normally you do not need this: the app upgrades the database
| automatically the first time the new code runs. Use it if your database
| user lacked privileges at that moment, or to see what happened.
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('APP_SKIP_SESSION', true);
define('APP_SKIP_SCHEMA_CHECK', true);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

runMigrations();
allSettings(true);

echo 'Database is at schema v' . setting('schema_version', '?') . PHP_EOL;

$admins = db()->query("SELECT email FROM users WHERE role = 'admin' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

echo 'Admins: ' . ($admins ? implode(', ', $admins) : '(none)') . PHP_EOL;
