<?php
declare(strict_types=1);

/*
| Short-link resolver.
|
|   https://example.com/jfhksl7d     (random code)
|   https://example.com/myurlname    (custom code)
|
| .htaccess rewrites those paths to this file. The legacy form
| redirect.php?code=xxxx keeps working so old links do not break.
|
| No session and no cookie is created here: a click must stay anonymous
| and must not create a session file on the server for every visitor.
*/

define('APP_SKIP_SESSION', true);
define('APP_SKIP_SCHEMA_CHECK', true);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/url-service.php';
require_once __DIR__ . '/includes/analytics.php';

function renderNotFound(): never
{
    http_response_code(404);
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');

    require __DIR__ . '/404.php';
    exit;
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if (!in_array($method, ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}

$code = $_GET['code'] ?? '';

if (
    !is_string($code) ||
    !preg_match('/^[a-zA-Z0-9_-]{3,32}$/', $code)
) {
    renderNotFound();
}

$link = findShortUrl($code);

if (!$link) {
    renderNotFound();
}

// Do not count HEAD requests (link previews, uptime monitors).
if ($method === 'GET') {
    recordUrlClick((int) $link['id']);
}

header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('Location: ' . $link['destination_url'], true, 302);
exit;
