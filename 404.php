<?php
declare(strict_types=1);

/*
| Standalone 404 page. Included by redirect.php; it also works if opened
| directly. It must not start a session (bots hit random URLs constantly).
*/

if (!defined('APP_ROOT')) {
    define('APP_SKIP_SESSION', true);
    require_once __DIR__ . '/includes/bootstrap.php';
    http_response_code(404);
}

$home = defined('APP_URL') ? APP_URL . '/' : '/';
$css = function_exists('assetUrl') ? assetUrl('assets/css/style.css') : 'assets/css/style.css';
$name = defined('APP_NAME') ? APP_NAME : 'AraShorten';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>الرابط غير موجود | <?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<main class="auth-section">
    <div class="auth-card text-center">
        <h1>404</h1>
        <p>الرابط غير موجود أو تم حذفه أو تعطيله.</p>
        <a class="btn btn-primary" href="<?= htmlspecialchars($home, ENT_QUOTES, 'UTF-8') ?>">العودة إلى الرئيسية</a>
    </div>
</main>
</body>
</html>
