<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SEO helpers: meta tags, robots.txt, sitemap.xml
|--------------------------------------------------------------------------
*/

function siteIndexable(): bool
{
    return setting('seo_robots_index', '1') !== '0';
}

function appOrigin(): string
{
    $parts = parse_url(APP_URL);

    return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') .
        (isset($parts['port']) ? ':' . $parts['port'] : '');
}

function appBasePath(): string
{
    return rtrim((string) (parse_url(APP_URL, PHP_URL_PATH) ?? ''), '/');
}

/**
 * Canonical URL for the current request (no query string, no index.php).
 */
function canonicalForRequest(): string
{
    $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

    $path = preg_replace('#/index\.php$#', '/', $path) ?? '/';

    if ($path === '' || $path[0] !== '/') {
        $path = '/';
    }

    // Keep only safe URL characters
    $path = preg_replace('#[^A-Za-z0-9/._~%\-]#', '', $path) ?? '/';

    return appOrigin() . $path;
}

function seoSiteTitle(): string
{
    return setting('seo_home_title', APP_NAME . ' - اختصار الروابط وإدارتها');
}

function seoDefaultDescription(): string
{
    return setting(
        'seo_default_description',
        'اختصر روابطك الطويلة وأدرها بسهولة باستخدام ' . APP_NAME . '.'
    );
}

/**
 * Build the values used in <head>. Pages may set before including header.php:
 *   $pageTitle, $metaDescription, $canonicalUrl, $noindex, $pageType, $isHome
 */
function seoHeadData(array $vars): array
{
    $isHome = !empty($vars['isHome']);
    $separator = setting('seo_title_separator', ' | ');

    if ($isHome) {
        $title = seoSiteTitle();
    } elseif (!empty($vars['pageTitle'])) {
        $title = $vars['pageTitle'] . $separator . APP_NAME;
    } else {
        $title = APP_NAME;
    }

    $description = !empty($vars['metaDescription'])
        ? (string) $vars['metaDescription']
        : seoDefaultDescription();

    $canonical = !empty($vars['canonicalUrl'])
        ? (string) $vars['canonicalUrl']
        : ($isHome ? appOrigin() . appBasePath() . '/' : canonicalForRequest());

    $noindex = !empty($vars['noindex']) || !siteIndexable();

    return [
        'title' => $title,
        'description' => mb_substr(trim(preg_replace('/\s+/u', ' ', $description) ?? $description), 0, 320),
        'canonical' => $canonical,
        'robots' => $noindex ? 'noindex,nofollow' : 'index,follow,max-image-preview:large',
        'noindex' => $noindex,
        'type' => (string) ($vars['pageType'] ?? 'website'),
        'image' => setting('seo_og_image', ''),
        'twitter' => setting('seo_twitter_handle', ''),
        'google' => setting('seo_google_verification', ''),
        'bing' => setting('seo_bing_verification', ''),
    ];
}

/**
 * JSON-LD structured data for the home page (Organization + WebSite).
 */
function seoJsonLdHome(): string
{
    $data = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'Organization',
                '@id' => appOrigin() . appBasePath() . '/#organization',
                'name' => setting('seo_organization_name', APP_NAME),
                'url' => appOrigin() . appBasePath() . '/',
            ],
            [
                '@type' => 'WebSite',
                '@id' => appOrigin() . appBasePath() . '/#website',
                'url' => appOrigin() . appBasePath() . '/',
                'name' => APP_NAME,
                'description' => seoDefaultDescription(),
                'inLanguage' => 'ar',
                'publisher' => ['@id' => appOrigin() . appBasePath() . '/#organization'],
            ],
        ],
    ];

    if (setting('seo_og_image', '') !== '') {
        $data['@graph'][0]['logo'] = setting('seo_og_image');
    }

    return json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR
    );
}

/*
|--------------------------------------------------------------------------
| robots.txt
|--------------------------------------------------------------------------
*/

/**
 * Keep only valid robots.txt lines from admin-entered text.
 */
function cleanRobotsCustomRules(string $text): string
{
    $lines = [];

    foreach (preg_split('/\R/', $text) ?: [] as $line) {
        $line = trim($line);

        if ($line === '') {
            continue;
        }

        if (
            $line[0] === '#' ||
            preg_match('/^(User-agent|Allow|Disallow|Crawl-delay|Sitemap|Host):\s*[\x21-\x7E ]{0,200}$/i', $line)
        ) {
            $lines[] = mb_substr($line, 0, 250);
        }
    }

    return implode("\n", array_slice($lines, 0, 100));
}

function robotsTxtContent(): string
{
    if (!siteIndexable()) {
        return "# Site is set to NOT be indexed (Admin > Settings > SEO)\n" .
            "User-agent: *\nDisallow: /\n";
    }

    $base = appBasePath();

    $lines = [
        '# robots.txt generated by ' . APP_NAME,
        'User-agent: *',
        'Allow: /',
    ];

    foreach ([
        '/admin/', '/dashboard', '/analytics', '/profile', '/login',
        '/register', '/logout', '/actions/', '/includes/', '/storage/',
        '/cron/', '/database/', '/install.php',
    ] as $path) {
        $lines[] = 'Disallow: ' . $base . $path;
    }

    $custom = cleanRobotsCustomRules(setting('seo_robots_custom', ''));

    if ($custom !== '') {
        $lines[] = '';
        $lines[] = '# Custom rules';
        $lines[] = $custom;
    }

    $lines[] = '';
    $lines[] = 'Sitemap: ' . appOrigin() . $base . '/sitemap.xml';

    return implode("\n", $lines) . "\n";
}

/*
|--------------------------------------------------------------------------
| sitemap.xml
|--------------------------------------------------------------------------
*/

/**
 * @return array<int, array{loc:string,lastmod:?string,changefreq:string,priority:string}>
 */
function sitemapEntries(): array
{
    if (!siteIndexable()) {
        return [];
    }

    $entries = [[
        'loc' => appOrigin() . appBasePath() . '/',
        'lastmod' => null,
        'changefreq' => 'weekly',
        'priority' => '1.0',
    ]];

    $cmsSlugs = [];

    try {
        $pages = db()->query(
            'SELECT slug, updated_at FROM pages
             WHERE is_published = 1 AND noindex = 0
             ORDER BY sort_order ASC, id ASC
             LIMIT 5000'
        )->fetchAll();
    } catch (Throwable $exception) {
        $pages = [];
    }

    foreach ($pages as $page) {
        $cmsSlugs[] = $page['slug'];

        $entries[] = [
            'loc' => pageUrl($page['slug']),
            'lastmod' => gmdate('c', (int) strtotime($page['updated_at'] . ' UTC')),
            'changefreq' => 'monthly',
            'priority' => '0.7',
        ];
    }

    // Built-in static pages (only if no CMS page replaces them, no duplicates)
    if (setting('seo_sitemap_include_static', '1') === '1') {
        foreach (['about', 'contact', 'privacy', 'terms'] as $slug) {
            if (in_array($slug, $cmsSlugs, true)) {
                continue;
            }

            // A CMS page that exists but is hidden/noindex also hides the static one
            if (in_array($slug, builtinPageSlugs(), true) && findPageBySlug($slug) !== null) {
                continue;
            }

            $entries[] = [
                'loc' => url($slug),
                'lastmod' => null,
                'changefreq' => 'yearly',
                'priority' => '0.4',
            ];
        }
    }

    return $entries;
}

function sitemapXml(): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach (sitemapEntries() as $entry) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";

        if ($entry['lastmod'] !== null) {
            $xml .= '    <lastmod>' . $entry['lastmod'] . "</lastmod>\n";
        }

        $xml .= '    <changefreq>' . $entry['changefreq'] . "</changefreq>\n";
        $xml .= '    <priority>' . $entry['priority'] . "</priority>\n";
        $xml .= "  </url>\n";
    }

    return $xml . "</urlset>\n";
}
