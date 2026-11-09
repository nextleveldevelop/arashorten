<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$isHome = true;
require_once __DIR__ . '/includes/url-service.php';

$pageTitle = 'اختصر روابطك بسهولة';

$flash = getFlash();
$lastShortUrl = $_SESSION['last_short_url'] ?? null;

unset($_SESSION['last_short_url']);

require __DIR__ . '/includes/header.php';
?>

<main class="hero">
    <div class="container">
        <div class="hero-content">
            <span class="eyebrow">اختصار الروابط بطريقة سهلة</span>

            <h1>
                روابط طويلة؟
                <span>اجعلها قصيرة واحترافية.</span>
            </h1>

            <p>
                أنشئ روابط مختصرة وشاركها بسهولة مع الآخرين.
                تابع عدد النقرات وأدر روابطك من لوحة التحكم.
            </p>

            <?php if ($flash): ?>
                <?= alertHtml($flash['type'], $flash['message']) ?>
            <?php endif; ?>

            <?php if ($lastShortUrl): ?>
                <div class="result-card">
                    <label>رابطك المختصر</label>

                    <div class="result-row">
                        <input
                            type="text"
                            id="shortUrl"
                            value="<?= e($lastShortUrl) ?>"
                            readonly
                        >

                        <button
                            type="button"
                            class="btn btn-primary btn-copy"
                            data-copy-target="shortUrl"
                        >
                            <?= icon('clipboard-document') ?><?= icon('check', 'icon-done') ?><span class="btn-label">نسخ</span>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (setting('allow_guest_shortening', '1') === '1' || isLoggedIn()): ?>
                <form
                    action="<?= e(url('actions/shorten.php')) ?>"
                    method="post"
                    class="shorten-form"
                >
                    <?= csrfField() ?>
                    <?= honeypotField() ?>

                    <label for="destination_url">الرابط الأصلي</label>

                    <input
                        type="url"
                        id="destination_url"
                        name="destination_url"
                        placeholder="https://example.com/very-long-url"
                        maxlength="2048"
                        required
                    >

                    <label for="custom_code">رمز مخصص (اختياري)</label>

                    <input
                        type="text"
                        id="custom_code"
                        name="custom_code"
                        placeholder="my-link"
                        minlength="3"
                        maxlength="32"
                        pattern="[a-zA-Z0-9_-]{3,32}"
                    >

                    <button type="submit" class="btn btn-primary btn-large">
                        <?= icon('link') ?><span>اختصر الرابط</span>
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-info" role="status">
                    <?= icon('information-circle') ?>
                    <span>
                        اختصار الروابط متاح للأعضاء فقط.
                        <a href="<?= e(url('register.php')) ?>">إنشاء حساب</a>
                    </span>
                </div>
            <?php endif; ?>

            <div class="hero-actions">
                <?php if (isLoggedIn()): ?>
                    <a href="<?= e(url('dashboard.php')) ?>" class="btn btn-secondary">
                        لوحة التحكم
                    </a>
                <?php else: ?>
                    <a href="<?= e(url('register.php')) ?>" class="btn btn-secondary">
                        إنشاء حساب مجاني
                    </a>

                    <a href="<?= e(url('login.php')) ?>" class="text-link">
                        تسجيل الدخول
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<section class="features-section">
    <div class="container">
        <div class="section-heading">
            <h2>كل ما تحتاجه لإدارة روابطك</h2>
            <p>أدوات بسيطة لإدارة ومتابعة روابطك المختصرة.</p>
        </div>

        <div class="feature-grid">
            <article class="feature-card">
                <div class="feature-icon"><?= icon('link') ?></div>
                <h3>اختصار الروابط</h3>
                <p>حوّل الروابط الطويلة إلى روابط قصيرة وسهلة المشاركة.</p>
            </article>

            <article class="feature-card">
                <div class="feature-icon"><?= icon('chart-bar') ?></div>
                <h3>إحصائيات النقرات</h3>
                <p>اعرف عدد مرات النقر على روابطك.</p>
            </article>

            <article class="feature-card">
                <div class="feature-icon"><?= icon('rectangle-stack') ?></div>
                <h3>إدارة الروابط</h3>
                <p>نظّم روابطك من لوحة تحكم واحدة.</p>
            </article>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>