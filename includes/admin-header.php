<?php
declare(strict_types=1);

/*
| Admin layout (top). Expected variables:
|   $adminTitle (string), $adminActive (string key),
|   optional: $loadChart (bool), $loadEditor (bool)
*/

// The editor page may need to load Quill from the CDN: widen the CSP for
// this one page only (must happen before any output).
if (!empty($loadEditor)) {
    applyEditorCsp();
}

$quill = !empty($loadEditor) ? quillAssets() : null;

$flash = getFlash();
$adminUser = currentUser();
$unreadMessages = unreadContactCount();

$adminNav = [
    'dashboard' => ['index.php', 'لوحة المعلومات', 'squares-2x2'],
    'users' => ['users.php', 'المستخدمون', 'users'],
    'links' => ['links.php', 'الروابط', 'link'],
    'pages' => ['pages.php', 'الصفحات', 'document-text'],
    'messages' => ['messages.php', 'الرسائل', 'envelope'],
    'settings' => ['settings.php', 'الإعدادات و SEO', 'cog-6-tooth'],
    'audit' => ['audit.php', 'سجل النشاط', 'clipboard-document-list'],
];

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($adminTitle) ?> | الإدارة - <?= e(APP_NAME) ?></title>

    <link rel="icon" href="<?= e(faviconHref()) ?>">
    <link rel="stylesheet" href="<?= e(assetUrl('assets/css/style.css')) ?>">

    <?php if ($quill !== null): ?>
        <link rel="stylesheet" href="<?= e($quill['css']) ?>"<?= quillTagAttributes($quill, 'css') ?>>
    <?php endif; ?>

    <?php if (!empty($loadChart)): ?>
        <script src="<?= e(assetUrl('assets/js/chart.js')) ?>" defer></script>
    <?php endif; ?>

    <?php if ($quill !== null): ?>
        <script src="<?= e($quill['js']) ?>"<?= quillTagAttributes($quill, 'js') ?> defer></script>
    <?php endif; ?>

    <script src="<?= e(assetUrl('assets/js/script.js')) ?>" defer></script>
    <script src="<?= e(assetUrl('assets/js/admin.js')) ?>" defer></script>

    <?php if ($quill !== null): ?>
        <script src="<?= e(assetUrl('assets/js/admin-editor.js')) ?>" defer></script>
    <?php endif; ?>
</head>
<body class="admin-body">

<div class="admin-shell">

    <aside class="admin-sidebar" id="adminSidebar">
        <a href="<?= e(url('admin/')) ?>" class="admin-brand">
            <span class="brand-icon"><?= icon('link') ?></span>
            <span><?= e(APP_NAME) ?> <small>إدارة</small></span>
        </a>

        <nav class="admin-nav" aria-label="قائمة الإدارة">
            <?php foreach ($adminNav as $key => [$file, $label, $navIcon]): ?>
                <a
                    href="<?= e(url('admin/' . $file)) ?>"
                    class="<?= $adminActive === $key ? 'is-active' : '' ?>"
                    <?= $adminActive === $key ? 'aria-current="page"' : '' ?>
                >
                    <?= icon($navIcon) ?>
                    <span><?= e($label) ?></span>
                    <?php if ($key === 'messages' && $unreadMessages > 0): ?>
                        <span class="nav-count"><?= $unreadMessages > 99 ? '99+' : (int) $unreadMessages ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar-footer">
            <a href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener"><?= icon('arrow-top-right-on-square') ?><span>عرض الموقع</span></a>
            <a href="<?= e(url('dashboard.php')) ?>"><?= icon('user-circle') ?><span>لوحة المستخدم</span></a>

            <form action="<?= e(url('logout.php')) ?>" method="post">
                <?= csrfField() ?>
                <button type="submit" class="nav-logout"><?= icon('arrow-right-on-rectangle') ?><span>تسجيل الخروج</span></button>
            </form>

            <small><?= e($adminUser['email']) ?></small>
        </div>
    </aside>

    <div class="admin-main">

        <header class="admin-topbar">
            <button
                type="button"
                class="admin-menu-toggle"
                id="adminMenuToggle"
                aria-label="القائمة"
                aria-expanded="false"
                aria-controls="adminSidebar"
            >
                <?= icon('bars-3', 'icon-open') ?>
                <?= icon('x-mark', 'icon-close') ?>
            </button>
            <h1><?= e($adminTitle) ?></h1>
        </header>

        <div class="admin-content">

            <?php if ($flash): ?>
                <?= alertHtml($flash['type'], $flash['message']) ?>
            <?php endif; ?>
