<?php
declare(strict_types=1);
?>

</div>

<footer class="site-footer">
    <div class="container">

        <div class="footer-grid">

            <div class="footer-brand">
                <h3><?= e(APP_NAME) ?></h3>

                <p>
                    منصة بسيطة لاختصار الروابط وإدارتها.
                </p>
            </div>

            <div class="footer-links">
                <h4>روابط مهمة</h4>

                <a href="<?= e(url('about.php')) ?>">من نحن</a>

                <a href="<?= e(url('contact.php')) ?>">اتصل بنا</a>

                <a href="<?= e(url('privacy.php')) ?>">سياسة الخصوصية</a>

                <a href="<?= e(url('terms.php')) ?>">شروط الاستخدام</a>

                <?php foreach (footerPages() as $footerPage): ?>
                    <a href="<?= e(pageUrl($footerPage['slug'])) ?>"><?= e($footerPage['title']) ?></a>
                <?php endforeach; ?>
            </div>

            <div class="footer-links">
                <h4>حسابك</h4>

                <?php if (isLoggedIn()): ?>

                    <a href="<?= e(url('dashboard.php')) ?>">لوحة التحكم</a>

                    <a href="<?= e(url('profile.php')) ?>">الملف الشخصي</a>

                <?php else: ?>

                    <a href="<?= e(url('login.php')) ?>">تسجيل الدخول</a>

                    <a href="<?= e(url('register.php')) ?>">إنشاء حساب</a>

                <?php endif; ?>
            </div>

        </div>

        <div class="footer-bottom">
            <p>
                © <?= date('Y') ?> <?= e(APP_NAME) ?>.
                جميع الحقوق محفوظة.
            </p>

            <p>Powered by AraShorten</p>
        </div>

    </div>
</footer>

</body>
</html>