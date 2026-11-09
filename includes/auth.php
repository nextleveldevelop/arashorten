<?php
declare(strict_types=1);

function currentUser(bool $refresh = false): ?array
{
    static $loaded = false;
    static $user = null;

    if ($refresh) {
        $loaded = false;
    }

    if ($loaded) {
        return $user;
    }

    $loaded = true;

    $userId = $_SESSION['user_id'] ?? null;

    if (!is_int($userId) && !ctype_digit((string) $userId)) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, name, email, role, is_active, created_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );

    $statement->execute([
        'id' => (int) $userId,
    ]);

    $user = $statement->fetch() ?: null;

    // Deleted or deactivated accounts lose access immediately.
    if ($user === null || (int) $user['is_active'] !== 1) {
        $user = null;
        unset($_SESSION['user_id'], $_SESSION['authenticated_at']);
    }

    return $user;
}

function isLoggedIn(): bool
{
    return currentUser() !== null;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('warning', 'يرجى تسجيل الدخول أولاً.');
        redirect('login.php');
    }
}

function isAdmin(): bool
{
    $user = currentUser();

    return $user !== null && $user['role'] === 'admin';
}

/**
 * Admin-only pages. The role is read from the database on every request,
 * so demoting an admin takes effect immediately. Admin sessions are also
 * limited to 4 hours, after which the password is asked again.
 */
function requireAdmin(): void
{
    requireLogin();

    if (!isAdmin()) {
        http_response_code(403);
        header('Cache-Control: no-store');
        exit('403 - غير مسموح.');
    }

    $startedAt = (int) ($_SESSION['authenticated_at'] ?? 0);

    if ($startedAt === 0 || (time() - $startedAt) > ADMIN_SESSION_SECONDS) {
        logoutUser();
        startSecureSession();
        setFlash('warning', 'انتهت جلسة الإدارة. سجّل الدخول مجدداً.');
        redirect('login.php');
    }
}

function requireGuest(): void
{
    if (isLoggedIn()) {
        redirect('dashboard.php');
    }
}

function loginUser(int $userId): void
{
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['authenticated_at'] = time();

    try {
        $statement = db()->prepare(
            'UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id'
        );
        $statement->execute(['id' => $userId]);
    } catch (Throwable $exception) {
        // not critical
    }

    currentUser(true);
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]
        );
    }

    session_destroy();
}