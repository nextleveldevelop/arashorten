<?php
declare(strict_types=1);

require_once __DIR__ . '/html-sanitizer.php';

/**
 * Slugs that also have a friendly top-level URL (/about, /terms, /privacy).
 */
function builtinPageSlugs(): array
{
    return ['about', 'terms', 'privacy'];
}

function builtinPageTitles(): array
{
    return [
        'about' => 'من نحن',
        'terms' => 'شروط الاستخدام',
        'privacy' => 'سياسة الخصوصية',
    ];
}

function pageSlugValid(string $slug): bool
{
    return strlen($slug) <= 100
        && preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) === 1;
}

function slugify(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return substr($slug, 0, 100);
}

/**
 * Public URL of a CMS page.
 */
function pageUrl(string $slug): string
{
    return in_array($slug, builtinPageSlugs(), true)
        ? url($slug)
        : url('page/' . $slug);
}

function findPageBySlug(string $slug, bool $onlyPublished = true): ?array
{
    if (!pageSlugValid($slug)) {
        return null;
    }

    try {
        $statement = db()->prepare(
            'SELECT * FROM pages
             WHERE slug = :slug' . ($onlyPublished ? ' AND is_published = 1' : '') . '
             LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);

        return $statement->fetch() ?: null;
    } catch (Throwable $exception) {
        return null;
    }
}

function findPageById(int $id): ?array
{
    $statement = db()->prepare('SELECT * FROM pages WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);

    return $statement->fetch() ?: null;
}

/**
 * Pages shown in the footer (excluding the built-in about/terms/privacy,
 * which already have fixed footer links).
 */
function footerPages(): array
{
    static $pages = null;

    if ($pages === null) {
        try {
            $pages = db()->query(
                'SELECT slug, title FROM pages
                 WHERE is_published = 1 AND show_in_footer = 1
                 ORDER BY sort_order ASC, title ASC
                 LIMIT 12'
            )->fetchAll();
        } catch (Throwable $exception) {
            $pages = [];
        }

        $pages = array_values(array_filter(
            $pages,
            static fn (array $page): bool => !in_array($page['slug'], builtinPageSlugs(), true)
        ));
    }

    return $pages;
}

/**
 * Render a CMS page inside the normal site layout and stop.
 */
function renderCmsPage(array $page): never
{
    // Variables consumed by includes/header.php
    $pageTitle = $page['meta_title'] !== null && $page['meta_title'] !== ''
        ? $page['meta_title']
        : $page['title'];

    $metaDescription = (string) ($page['meta_description'] ?? '');
    $canonicalUrl = pageUrl($page['slug']);
    $noindex = (int) $page['noindex'] === 1;
    $pageType = 'article';

    $cmsPage = $page;

    require dirname(__DIR__) . '/includes/header.php';
    ?>
    <main class="content-section">
        <div class="container">
            <article class="content-card cms-body">
                <h1><?= e($page['title']) ?></h1>
                <?= sanitizeRichHtml((string) $page['content']) /* sanitized on save AND again on output */ ?>
            </article>
        </div>
    </main>
    <?php
    require dirname(__DIR__) . '/includes/footer.php';
    exit;
}

/**
 * Called by about.php / terms.php / privacy.php: if the admin created a
 * published CMS page with that slug, it replaces the built-in text.
 */
function maybeRenderCmsOverride(string $slug): void
{
    $page = findPageBySlug($slug);

    if ($page !== null) {
        renderCmsPage($page);
    }
}
