<?php
declare(strict_types=1);

/*
| Public CMS page:  https://example.com/page/my-page-slug
| Pages created in Admin > Pages (rich text from the Quill editor).
*/

require_once __DIR__ . '/includes/bootstrap.php';

$slug = $_GET['slug'] ?? '';

if (!is_string($slug) || !pageSlugValid($slug)) {
    http_response_code(404);
    header('X-Robots-Tag: noindex');
    require __DIR__ . '/404.php';
    exit;
}

// about / terms / privacy live at their short URL: avoid duplicate content.
if (in_array($slug, builtinPageSlugs(), true)) {
    header('Location: ' . url($slug), true, 301);
    exit;
}

$page = findPageBySlug($slug);

if ($page === null) {
    http_response_code(404);
    header('X-Robots-Tag: noindex');
    require __DIR__ . '/404.php';
    exit;
}

renderCmsPage($page);
