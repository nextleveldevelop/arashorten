<?php
declare(strict_types=1);

/**
 * Record an administrative action. No IP address or user agent is stored.
 */
function auditLog(string $action, string $details = '', ?int $actorId = null): void
{
    if ($actorId === null) {
        $current = currentUser();
        $actorId = $current ? (int) $current['id'] : null;
    }

    try {
        $statement = db()->prepare(
            'INSERT INTO audit_logs (user_id, action, details, created_at)
             VALUES (:user_id, :action, :details, UTC_TIMESTAMP())'
        );

        $statement->execute([
            'user_id' => $actorId,
            'action' => substr($action, 0, 64),
            'details' => mb_substr($details, 0, 500),
        ]);
    } catch (Throwable $exception) {
        error_log('Audit log write failed.');
    }
}
