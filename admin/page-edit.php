<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin.php';

requireAdmin();

$id = (int) filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
$existing = $id > 0 ? findPageById($id) : null;

if ($id > 0 && $existing === null) {
    setFlash('error', 'الصفحة غير موجودة.');
    redirect('admin/pages.php');
}

$page = $existing ?? [
    'id' => 0,
    'slug' => '',
    'title' => '',
    'content' => '',
    'meta_title' => '',
    'meta_description' => '',
    'is_published' => 1,
    'show_in_footer' => 0,
    'noindex' => 0,
    'sort_order' => 0,
];

// New page from a built-in preset: /admin/page-edit.php?preset=about
$preset = $_GET['preset'] ?? '';

if ($existing === null && is_string($preset) && isset(builtinPageTitles()[$preset])) {
    $page['slug'] = $preset;
    $page['title'] = builtinPageTitles()[$preset];
}

$errors = [];

if (adminIsPost()) {
    $title = trimmedPost('title', 800);
    $slug = strtolower(trimmedPost('slug', 300));
    $metaTitle = trimmedPost('meta_title', 800);
    $metaDescription = trimmedPost('meta_description', 1500);
    $contentRaw = postString('content', 400000);
    $sortOrder = (int) filter_var(postString('sort_order', 10), FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

    $published = postString('is_published', 2) === '1' ? 1 : 0;
    $footer = postString('show_in_footer', 2) === '1' ? 1 : 0;
    $noindex = postString('noindex', 2) === '1' ? 1 : 0;

    if ($slug === '') {
        $slug = slugify($title);
    }

    if ($slug === '') {
        $slug = 'page-' . bin2hex(random_bytes(3));
    }

    $content = sanitizeRichHtml($contentRaw);

    if ($title === '' || mb_strlen($title) > 200) {
        $errors[] = 'العنوان مطلوب (200 حرف كحد أقصى).';
    }

    if (!pageSlugValid($slug)) {
        $errors[] = 'المعرّف (slug) يجب أن يحتوي على أحرف إنجليزية صغيرة وأرقام وشرطات فقط.';
    }

    if (mb_strlen($metaTitle) > 200) {
        $errors[] = 'عنوان SEO طويل جداً.';
    }

    if (mb_strlen($metaDescription) > 320) {
        $errors[] = 'وصف SEO طويل جداً (320 حرفاً كحد أقصى).';
    }

    if (richTextLength($content) < 1) {
        $errors[] = 'محتوى الصفحة فارغ.';
    }

    if ($sortOrder < -1000 || $sortOrder > 1000) {
        $sortOrder = 0;
    }

    if (!$errors) {
        $dup = db()->prepare('SELECT id FROM pages WHERE slug = :slug AND id <> :id LIMIT 1');
        $dup->execute(['slug' => $slug, 'id' => $existing ? (int) $existing['id'] : 0]);

        if ($dup->fetch()) {
            $errors[] = 'هذا المعرّف مستخدم في صفحة أخرى.';
        }
    }

    $page = [
        'id' => $existing ? (int) $existing['id'] : 0,
        'slug' => $slug,
        'title' => $title,
        'content' => $content !== '' ? $content : $contentRaw,
        'meta_title' => $metaTitle,
        'meta_description' => $metaDescription,
        'is_published' => $published,
        'show_in_footer' => $footer,
        'noindex' => $noindex,
        'sort_order' => $sortOrder,
    ];

    if (!$errors) {
        $values = [
            'slug' => $slug,
            'title' => $title,
            'content' => $content,
            'meta_title' => $metaTitle !== '' ? $metaTitle : null,
            'meta_description' => $metaDescription !== '' ? $metaDescription : null,
            'published' => $published,
            'footer' => $footer,
            'noindex' => $noindex,
            'sort' => $sortOrder,
        ];

        if ($existing) {
            $statement = db()->prepare(
                'UPDATE pages SET slug = :slug, title = :title, content = :content,
                        meta_title = :meta_title, meta_description = :meta_description,
                        is_published = :published, show_in_footer = :footer,
                        noindex = :noindex, sort_order = :sort, updated_at = UTC_TIMESTAMP()
                 WHERE id = :id'
            );
            $statement->execute($values + ['id' => (int) $existing['id']]);
            $savedId = (int) $existing['id'];
            auditLog('page.update', $slug);
        } else {
            $statement = db()->prepare(
                'INSERT INTO pages (slug, title, content, meta_title, meta_description,
                        is_published, show_in_footer, noindex, sort_order, created_at, updated_at)
                 VALUES (:slug, :title, :content, :meta_title, :meta_description,
                        :published, :footer, :noindex, :sort, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $statement->execute($values);
            $savedId = (int) db()->lastInsertId();
            auditLog('page.create', $slug);
        }

        setFlash('success', 'تم حفظ الصفحة.');
        redirect('admin/page-edit.php?id=' . $savedId);
    }
}

$adminTitle = $existing ? 'تعديل صفحة' : 'صفحة جديدة';
$adminActive = 'pages';
$loadEditor = true;

require __DIR__ . '/../includes/admin-header.php';
?>

<div class="alert alert-warning" id="editorFallbackNotice" hidden>
    <?= icon('exclamation-triangle') ?>
    <span>
        تعذّر تحميل المحرر المرئي (قد يكون محظوراً من الشبكة أو من أداة حجب الإعلانات).
        يمكنك المتابعة بمحرر HTML البسيط أدناه، ويتم تنظيف المحتوى عند الحفظ في كل الأحوال.
    </span>
</div>

<?php foreach ($errors as $error): ?>
    <?= alertHtml('error', $error) ?>
<?php endforeach; ?>

<form method="post" class="admin-form" id="pageForm">
    <?= csrfField() ?>

    <section class="dashboard-panel">
        <div class="panel-heading">
            <h2>المحتوى</h2>
            <?php if ($existing): ?>
                <a href="<?= e(pageUrl($page['slug'])) ?>" target="_blank" rel="noopener" class="link-with-icon"><span>عرض</span><?= icon('arrow-top-right-on-square') ?></a>
            <?php endif; ?>
        </div>

        <label for="title">عنوان الصفحة</label>
        <input id="title" name="title" value="<?= e($page['title']) ?>" maxlength="200" required>

        <label for="slug">المعرّف في الرابط (slug)</label>
        <input id="slug" name="slug" value="<?= e($page['slug']) ?>" maxlength="100" dir="ltr"
               pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="about-us">
        <p class="field-hint" dir="ltr"><?= e(APP_URL) ?>/page/<strong>slug</strong> — about / terms / privacy تعمل على /about /terms /privacy</p>

        <label for="contentField">النص</label>
        <div class="editor-wrap">
            <textarea id="contentField" name="content" rows="16" class="admin-textarea" dir="auto"><?= e($page['content']) ?></textarea>
            <div
                id="editorHost"
                class="editor-host"
                hidden
                data-icons="<?= e(json_encode(editorIcons(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)) ?>"
            ></div>
        </div>
    </section>

    <section class="dashboard-panel">
        <div class="panel-heading"><h2>تحسين محركات البحث (SEO)</h2></div>

        <label for="meta_title">عنوان SEO (اختياري)</label>
        <input id="meta_title" name="meta_title" value="<?= e($page['meta_title']) ?>" maxlength="200">
        <p class="field-hint"><span data-counter-for="meta_title" data-recommended="60"></span> — يُفضّل ألا يتجاوز 60 حرفاً. يُستخدم العنوان أعلاه إذا تُرك فارغاً.</p>

        <label for="meta_description">وصف SEO</label>
        <textarea id="meta_description" name="meta_description" rows="3" maxlength="320"><?= e($page['meta_description']) ?></textarea>
        <p class="field-hint"><span data-counter-for="meta_description" data-recommended="160"></span> — يُفضّل 120–160 حرفاً.</p>
    </section>

    <section class="dashboard-panel">
        <div class="panel-heading"><h2>النشر</h2></div>

        <label class="check-inline"><input type="checkbox" name="is_published" value="1" <?= (int) $page['is_published'] === 1 ? 'checked' : '' ?>> منشورة</label>
        <label class="check-inline"><input type="checkbox" name="show_in_footer" value="1" <?= (int) $page['show_in_footer'] === 1 ? 'checked' : '' ?>> عرض رابطها في التذييل</label>
        <label class="check-inline"><input type="checkbox" name="noindex" value="1" <?= (int) $page['noindex'] === 1 ? 'checked' : '' ?>> منع الأرشفة (noindex) واستبعادها من sitemap</label>

        <label for="sort_order">الترتيب</label>
        <input id="sort_order" type="number" name="sort_order" value="<?= (int) $page['sort_order'] ?>" min="-1000" max="1000">
    </section>

    <button type="submit" class="btn btn-primary"><?= icon('check') ?><span>حفظ الصفحة</span></button>
    <a href="<?= e(url('admin/pages.php')) ?>" class="btn btn-secondary">إلغاء</a>
</form>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
