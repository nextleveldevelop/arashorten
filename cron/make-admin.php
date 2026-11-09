<?php
declare(strict_types=1);

/*
| Promote or demote an account (command line only):
|
|   php cron/make-admin.php user@example.com           make admin
|   php cron/make-admin.php user@example.com --demote  back to normal user
|
| Useful if you lock yourself out. Requires shell access to the server.
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('APP_SKIP_SESSION', true);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$email = strtolower(trim((string) ($argv[1] ?? '')));
$demote = in_array('--demote', $argv, true);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php cron/make-admin.php user@example.com [--demote]\n");
    exit(1);
}

$statement = db()->prepare(
    "UPDATE users SET role = :role, is_active = 1, updated_at = UTC_TIMESTAMP() WHERE email = :email"
);
$statement->execute(['role' => $demote ? 'user' : 'admin', 'email' => $email]);

$found = db()->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
$found->execute(['email' => $email]);

if ((int) $found->fetchColumn() === 0) {
    fwrite(STDERR, "No account with that e-mail.\n");
    exit(1);
}

auditLog($demote ? 'cli.demote' : 'cli.promote', $email, null);

echo $email . ' is now ' . ($demote ? 'a normal user' : 'an admin') . PHP_EOL;
