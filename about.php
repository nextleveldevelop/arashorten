<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

// If the admin created a published page with this slug, it replaces this text.
maybeRenderCmsOverride('about');

$pageTitle = 'من نحن';

require __DIR__ . '/includes/header.php';
?>

<main class="content-section">
    <div class="container">
        <article class="content-card">
            <span class="eyebrow">تعرف علينا</span>

            <h1>من نحن؟</h1>

            <p>
                <?= e(APP_NAME) ?> منصة لاختصار الروابط الطويلة
                وتحويلها إلى روابط قصيرة يسهل مشاركتها وإدارتها.
            </p>

            <h2>هدفنا</h2>

            <p>
                نسعى إلى توفير تجربة بسيطة تساعد المستخدمين على
                إنشاء روابط مختصرة ومتابعة عدد النقرات عليها.
            </p>

            <h2>خدماتنا</h2>

            <ul>
                <li>إنشاء روابط مختصرة.</li>
                <li>إمكانية تخصيص رمز الرابط.</li>
                <li>إدارة الروابط من لوحة تحكم واحدة.</li>
                <li>إحصائيات أساسية حول عدد النقرات.</li>
            </ul>

            <h2>التواصل</h2>

            <p>
                إذا كانت لديك أسئلة أو اقتراحات، يمكنك التواصل معنا
                من خلال صفحة الاتصال.
            </p>

            <a href="<?= e(url('contact.php')) ?>" class="btn btn-primary">
                اتصل بنا
            </a>
        </article>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>