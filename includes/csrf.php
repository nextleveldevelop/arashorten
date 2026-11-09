<?php
declare(strict_types=1);

function csrfToken(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf_token" value="' .
        htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') .
        '">';
}

function verifyCsrfToken(): void
{
    $submitted = $_POST['_csrf_token'] ?? '';

    if (
        !is_string($submitted) ||
        !isset($_SESSION['_csrf_token']) ||
        !hash_equals($_SESSION['_csrf_token'], $submitted)
    ) {
        http_response_code(403);
        exit('الطلب غير صالح. يرجى تحديث الصفحة والمحاولة مجدداً.');
    }
}