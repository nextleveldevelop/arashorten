<?php
declare(strict_types=1);

$user = currentUser();
$flash = getFlash();

$seo = seoHeadData(get_defined_vars());
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= e($seo['title']) ?></title>

    <meta name="description" content="<?= e($seo['description']) ?>">
    <meta name="robots" content="<?= e($seo['robots']) ?>">
    <link rel="canonical" href="<?= e($seo['canonical']) ?>">

    <?php if ($seo['google'] !== ''): ?>
        <meta name="google-site-verification" content="<?= e($seo['google']) ?>">
    <?php endif; ?>
    <?php if ($seo['bing'] !== ''): ?>
        <meta name="msvalidate.01" content="<?= e($seo['bing']) ?>">
    <?php endif; ?>

    <?php if (!$seo['noindex']): ?>
        <meta property="og:type" content="<?= e($seo['type']) ?>">
        <meta property="og:site_name" content="<?= e(APP_NAME) ?>">
        <meta property="og:title" content="<?= e($seo['title']) ?>">
        <meta property="og:description" content="<?= e($seo['description']) ?>">
        <meta property="og:url" content="<?= e($seo['canonical']) ?>">
        <meta property="og:locale" content="ar_AR">
        <?php if ($seo['image'] !== ''): ?>
            <meta property="og:image" content="<?= e($seo['image']) ?>">
        <?php endif; ?>
        <meta name="twitter:card" content="<?= $seo['image'] !== '' ? 'summary_large_image' : 'summary' ?>">
        <meta name="twitter:title" content="<?= e($seo['title']) ?>">
        <meta name="twitter:description" content="<?= e($seo['description']) ?>">
        <?php if ($seo['twitter'] !== ''): ?>
            <meta name="twitter:site" content="<?= e($seo['twitter']) ?>">
        <?php endif; ?>
    <?php endif; ?>

    <link rel="icon" href="<?= e(faviconHref()) ?>">

    <?php if (!empty($isHome) && !$seo['noindex']): ?>
        <script type="application/ld+json"><?= seoJsonLdHome() ?></script>
    <?php endif; ?>

    <link rel="stylesheet" href="<?= e(assetUrl('assets/css/style.css')) ?>">

    <?php if (isset($pageTitle) && $pageTitle === 'إحصائيات الرابط'): ?>
        <script src="<?= e(assetUrl('assets/js/chart.js')) ?>" defer></script>
    <?php endif; ?>

    <script src="<?= e(assetUrl('assets/js/script.js')) ?>" defer></script>
</head>
<body>

<header class="site-header">
    <div class="container header-inner">

        <a href="<?= e(url('index.php')) ?>" class="brand">
            <span class="brand-icon"><?= icon('link') ?></span>
            <span><?= e(APP_NAME) ?></span>
        </a>

        <button
            class="menu-toggle"
            type="button"
            aria-label="فتح القائمة"
            aria-expanded="false"
            aria-controls="mainNavigation"
        >
            <?= icon('bars-3', 'icon-open') ?>
            <?= icon('x-mark', 'icon-close') ?>
        </button>

        <nav class="main-navigation" id="mainNavigation">

            <a href="<?= e(url('index.php')) ?>">الرئيسية</a>

            <?php if ($user): ?>

                <a href="<?= e(url('dashboard.php')) ?>">لوحة التحكم</a>

                <?php if ($user['role'] === 'admin'): ?>
                    <a href="<?= e(url('admin/')) ?>">الإدارة</a>
                <?php endif; ?>

                <a href="<?= e(url('profile.php')) ?>">حسابي</a>

                <form
                    action="<?= e(url('logout.php')) ?>"
                    method="post"
                    class="logout-form"
                >
                    <?= csrfField() ?>

                    <button type="submit" class="nav-logout">
                        <?= icon('arrow-right-on-rectangle') ?><span>تسجيل الخروج</span>
                    </button>
                </form>

            <?php else: ?>

                <a href="<?= e(url('login.php')) ?>">تسجيل الدخول</a>

                <a href="<?= e(url('register.php')) ?>" class="nav-register">
                    إنشاء حساب
                </a>

            <?php endif; ?>

        </nav>

    </div>
</header>

<div class="site-content">

<?php if ($flash): ?>
    <div class="container global-flash">
        <?= alertHtml($flash['type'], $flash['message']) ?>
    </div>
<?php endif; ?>