<?php
declare(strict_types=1);

/* Served as /sitemap.xml (see .htaccess). Generated live from the database. */

define('APP_SKIP_SESSION', true);

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

echo sitemapXml();
