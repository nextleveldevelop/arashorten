<?php
declare(strict_types=1);

/* Served as /robots.txt (see .htaccess). Content comes from Admin > Settings. */

define('APP_SKIP_SESSION', true);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');

echo robotsTxtContent();
