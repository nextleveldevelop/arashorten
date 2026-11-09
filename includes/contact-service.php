<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Contact messages (public form -> Admin > Messages)
|--------------------------------------------------------------------------
| Stores name, e-mail, subject and text only. No IP address and no browser
| data is saved (same privacy rule as the rest of the application).
*/

/**
 * Save a message from the public contact form.
 */
function saveContactMessage(string $name, string $email, string $subject, string $message): bool
{
    try {
        $statement = db()->prepare(
            'INSERT INTO contact_messages (name, email, subject, message, is_read, created_at)
             VALUES (:name, :email, :subject, :message, 0, UTC_TIMESTAMP())'
        );

        $statement->execute([
            'name' => mb_substr($name, 0, 100),
            'email' => mb_substr($email, 0, 190),
            'subject' => mb_substr($subject, 0, 150),
            'message' => mb_substr($message, 0, 5000),
        ]);

        return true;
    } catch (Throwable $exception) {
        error_log('Contact message could not be saved.');

        return false;
    }
}

/**
 * Number of unread messages (shown in the admin menu). Never throws.
 */
function unreadContactCount(): int
{
    static $count = null;

    if ($count === null) {
        try {
            $count = (int) db()->query(
                'SELECT COUNT(*) FROM contact_messages WHERE is_read = 0'
            )->fetchColumn();
        } catch (Throwable $exception) {
            $count = 0;
        }
    }

    return $count;
}

/**
 * Delete messages older than the retention period (0 = keep forever).
 * Called from cron/cleanup.php.
 */
function purgeOldContactMessages(int $days): int
{
    if ($days < 1) {
        return 0;
    }

    try {
        $statement = db()->prepare(
            'DELETE FROM contact_messages
             WHERE created_at < UTC_TIMESTAMP() - INTERVAL ' . (int) $days . ' DAY'
        );
        $statement->execute();

        return $statement->rowCount();
    } catch (Throwable $exception) {
        return 0;
    }
}
