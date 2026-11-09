<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin.php';

requireAdmin();

// ---- Downloads: /admin/settings.php?download=robots|sitemap
$download = $_GET['download'] ?? '';

if ($download === 'robots') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="robots.txt"');
    echo robotsTxtContent();
    exit;
}

if ($download === 'sitemap') {
    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="sitemap.xml"');
    echo sitemapXml();
    exit;
}

/**
 * Accept either a bare token or a pasted <meta ... content="TOKEN"> tag.
 */
function cleanVerificationToken(string $value): ?string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $value, $m)) {
        $value = $m[1];
    }

    return preg_match('/^[A-Za-z0-9_\-]{10,120}$/', $value) ? $value : null;
}

if (adminIsPost()) {
    $section = trimmedPost('section', 20);

    if ($section === 'general') {
        saveSetting('allow_registration', postString('allow_registration', 2) === '1' ? '1' : '0');
        saveSetting('allow_guest_shortening', postString('allow_guest_shortening', 2) === '1' ? '1' : '0');

        $domains = [];

        foreach (preg_split('/\R/', postString('blocked_domains', 20000)) ?: [] as $line) {
            $line = strtolower(trim($line));
            $line = preg_replace('#^https?://#', '', $line) ?? $line;
            $line = explode('/', $line)[0];

            if ($line !== '' && preg_match('/^[a-z0-9.-]{3,253}$/', $line)) {
                $domains[$line] = true;
            }
        }

        saveSetting('blocked_domains', implode("\n", array_slice(array_keys($domains), 0, 2000)));

        auditLog('settings.general', 'registration=' . setting('allow_registration') . ' guests=' . setting('allow_guest_shortening'));
        setFlash('success', 'تم حفظ الإعدادات العامة.');
    } elseif ($section === 'seo') {
        $errors = [];

        $homeTitle = trimmedPost('seo_home_title', 600);
        $description = trimmedPost('seo_default_description', 1500);
        $separator = postString('seo_title_separator', 10);
        $image = trimmedPost('seo_og_image', 800);
        $twitter = trimmedPost('seo_twitter_handle', 100);
        $google = cleanVerificationToken(postString('seo_google_verification', 500));
        $bing = cleanVerificationToken(postString('seo_bing_verification', 500));
        $orgName = trimmedPost('seo_organization_name', 400);

        if (mb_strlen($homeTitle) > 120) {
            $errors[] = 'عنوان الصفحة الرئيسية طويل جداً (120 كحد أقصى).';
        }

        if (mb_strlen($description) > 320) {
            $errors[] = 'الوصف الافتراضي طويل جداً (320 كحد أقصى).';
        }

        if (!in_array($separator, [' | ', ' - ', ' – ', ' · '], true)) {
            $separator = ' | ';
        }

        if (
            $image !== '' &&
            (
                strlen($image) > 500 ||
                !filter_var($image, FILTER_VALIDATE_URL) ||
                !in_array(strtolower((string) parse_url($image, PHP_URL_SCHEME)), ['http', 'https'], true)
            )
        ) {
            $errors[] = 'رابط صورة المشاركة (og:image) غير صالح.';
        }

        if ($twitter !== '') {
            $twitter = '@' . ltrim($twitter, '@');

            if (!preg_match('/^@[A-Za-z0-9_]{1,15}$/', $twitter)) {
                $errors[] = 'حساب تويتر/X غير صالح.';
            }
        }

        if ($google === null || $bing === null) {
            $errors[] = 'رمز التحقق غير صالح. الصق قيمة content فقط (أو وسم meta كاملاً).';
        }

        if (mb_strlen($orgName) > 100) {
            $errors[] = 'اسم المنظمة طويل جداً.';
        }

        if ($errors) {
            setFlash('error', implode(' ', $errors));
        } else {
            saveSetting('seo_home_title', $homeTitle);
            saveSetting('seo_default_description', $description);
            saveSetting('seo_title_separator', $separator);
            saveSetting('seo_og_image', $image);
            saveSetting('seo_twitter_handle', $twitter);
            saveSetting('seo_google_verification', (string) $google);
            saveSetting('seo_bing_verification', (string) $bing);
            saveSetting('seo_organization_name', $orgName);
            saveSetting('seo_robots_index', postString('seo_robots_index', 2) === '1' ? '1' : '0');
            saveSetting('seo_sitemap_include_static', postString('seo_sitemap_include_static', 2) === '1' ? '1' : '0');

            auditLog('settings.seo', 'indexable=' . setting('seo_robots_index', '1'));
            setFlash('success', 'تم حفظ إعدادات SEO.');
        }
    } elseif ($section === 'robots') {
        saveSetting('seo_robots_custom', cleanRobotsCustomRules(postString('seo_robots_custom', 20000)));
        auditLog('settings.robots', 'custom rules updated');
        setFlash('success', 'تم حفظ قواعد robots.txt.');
    } else {
        setFlash('error', 'قسم غير معروف.');
    }

    redirect('admin/settings.php');
}

// ---- SEO health checklist
$seoChecks = [];

$addCheck = static function (string $label, bool $ok, string $detail) use (&$seoChecks): void {
    $seoChecks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
};

$homeTitleLen = mb_strlen(seoSiteTitle());
$descLen = mb_strlen(seoDefaultDescription());

$addCheck('السماح لمحركات البحث بالفهرسة', siteIndexable(), siteIndexable() ? 'مفعّل' : 'الموقع كله noindex حالياً!');
$addCheck('الموقع يعمل عبر HTTPS', APP_IS_HTTPS, APP_IS_HTTPS ? 'نعم' : 'غيّر app_url إلى https://');
$addCheck('عنوان الصفحة الرئيسية (30–65 حرفاً)', $homeTitleLen >= 30 && $homeTitleLen <= 65, $homeTitleLen . ' حرف');
$addCheck('الوصف الافتراضي (70–160 حرفاً)', $descLen >= 70 && $descLen <= 160, $descLen . ' حرف');
$addCheck('صورة المشاركة (og:image)', setting('seo_og_image') !== '', setting('seo_og_image') !== '' ? 'مضبوطة' : 'غير مضبوطة (يُفضّل 1200×630)');
$addCheck('التحقق من Google Search Console', setting('seo_google_verification') !== '', setting('seo_google_verification') !== '' ? 'مضاف' : 'غير مضاف');

$missingMeta = (int) db()->query(
    "SELECT COUNT(*) FROM pages WHERE is_published = 1 AND noindex = 0
       AND (meta_description IS NULL OR meta_description = '')"
)->fetchColumn();

$addCheck('كل الصفحات المنشورة لها وصف SEO', $missingMeta === 0, $missingMeta === 0 ? 'نعم' : $missingMeta . ' صفحة بلا وصف');

$entries = sitemapEntries();

$adminTitle = 'الإعدادات و SEO';
$adminActive = 'settings';

require __DIR__ . '/../includes/admin-header.php';
?>

<nav class="admin-subnav">
    <a href="#general">عام</a>
    <a href="#seo">SEO</a>
    <a href="#robots">robots.txt</a>
    <a href="#sitemap">sitemap.xml</a>
    <a href="#health">فحص SEO</a>
</nav>

<section class="dashboard-panel" id="general">
    <div class="panel-heading"><h2>إعدادات عامة</h2></div>

    <form method="post" class="admin-form">
        <?= csrfField() ?>
        <input type="hidden" name="section" value="general">

        <label class="check-inline"><input type="checkbox" name="allow_registration" value="1" <?= setting('allow_registration', '1') === '1' ? 'checked' : '' ?>> السماح بإنشاء حسابات جديدة</label>
        <label class="check-inline"><input type="checkbox" name="allow_guest_shortening" value="1" <?= setting('allow_guest_shortening', '1') === '1' ? 'checked' : '' ?>> السماح للزوار بالاختصار دون حساب</label>

        <label for="blocked_domains">نطاقات محظورة (نطاق في كل سطر)</label>
        <textarea id="blocked_domains" name="blocked_domains" rows="5" dir="ltr" placeholder="bad-site.com"><?= e(setting('blocked_domains', '')) ?></textarea>
        <p class="field-hint">لا يمكن اختصار روابط هذه النطاقات ونطاقاتها الفرعية (مفيد ضد التصيّد والإساءة).</p>

        <button type="submit" class="btn btn-primary">حفظ</button>
    </form>
</section>

<section class="dashboard-panel" id="seo">
    <div class="panel-heading"><h2>قواعد SEO</h2></div>

    <form method="post" class="admin-form">
        <?= csrfField() ?>
        <input type="hidden" name="section" value="seo">

        <label class="check-inline"><input type="checkbox" name="seo_robots_index" value="1" <?= siteIndexable() ? 'checked' : '' ?>> السماح لمحركات البحث بفهرسة الموقع</label>
        <p class="field-hint">أوقفه أثناء التجربة فقط: يضيف noindex لكل الصفحات و Disallow: / في robots.txt ويُفرغ sitemap.</p>

        <label for="seo_home_title">عنوان الصفحة الرئيسية (title)</label>
        <input id="seo_home_title" name="seo_home_title" value="<?= e(setting('seo_home_title', '')) ?>" maxlength="120" placeholder="<?= e(seoSiteTitle()) ?>">
        <p class="field-hint"><span data-counter-for="seo_home_title" data-recommended="60"></span> — الأفضل 30–60 حرفاً.</p>

        <label for="seo_default_description">الوصف الافتراضي (meta description)</label>
        <textarea id="seo_default_description" name="seo_default_description" rows="3" maxlength="320"><?= e(setting('seo_default_description', '')) ?></textarea>
        <p class="field-hint"><span data-counter-for="seo_default_description" data-recommended="160"></span> — الأفضل 70–160 حرفاً. يُستخدم للصفحة الرئيسية وللصفحات بلا وصف خاص.</p>

        <label for="seo_title_separator">الفاصل بين عنوان الصفحة واسم الموقع</label>
        <select id="seo_title_separator" name="seo_title_separator">
            <?php foreach ([' | ', ' - ', ' – ', ' · '] as $sep): ?>
                <option value="<?= e($sep) ?>" <?= setting('seo_title_separator', ' | ') === $sep ? 'selected' : '' ?>>مثال: صفحة<?= e($sep) ?><?= e(APP_NAME) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="seo_organization_name">اسم المنظمة (للبيانات المنظمة Schema.org)</label>
        <input id="seo_organization_name" name="seo_organization_name" value="<?= e(setting('seo_organization_name', '')) ?>" maxlength="100" placeholder="<?= e(APP_NAME) ?>">

        <label for="seo_og_image">صورة المشاركة og:image (رابط كامل)</label>
        <input id="seo_og_image" name="seo_og_image" value="<?= e(setting('seo_og_image', '')) ?>" maxlength="500" dir="ltr" placeholder="https://example.com/share.png">

        <label for="seo_twitter_handle">حساب X / تويتر</label>
        <input id="seo_twitter_handle" name="seo_twitter_handle" value="<?= e(setting('seo_twitter_handle', '')) ?>" maxlength="16" dir="ltr" placeholder="@mybrand">

        <label for="seo_google_verification">رمز التحقق من Google Search Console</label>
        <input id="seo_google_verification" name="seo_google_verification" value="<?= e(setting('seo_google_verification', '')) ?>" maxlength="500" dir="ltr" placeholder="قيمة content فقط">

        <label for="seo_bing_verification">رمز التحقق من Bing Webmaster</label>
        <input id="seo_bing_verification" name="seo_bing_verification" value="<?= e(setting('seo_bing_verification', '')) ?>" maxlength="500" dir="ltr">

        <label class="check-inline"><input type="checkbox" name="seo_sitemap_include_static" value="1" <?= setting('seo_sitemap_include_static', '1') === '1' ? 'checked' : '' ?>> تضمين الصفحات الثابتة (من نحن، اتصل بنا، الخصوصية، الشروط) في sitemap</label>

        <button type="submit" class="btn btn-primary">حفظ إعدادات SEO</button>
    </form>

    <p class="field-hint">
        مُطبّق تلقائياً على كل الصفحات: canonical، Open Graph، Twitter Card، JSON-LD (Organization و WebSite)،
        robots meta، noindex للوحات المستخدم، وروابط مختصرة بـ noindex (إعادة توجيه 302).
    </p>
</section>

<section class="dashboard-panel" id="robots">
    <div class="panel-heading">
        <h2>robots.txt</h2>
        <span>
            <a href="<?= e(url('robots.txt')) ?>" target="_blank" rel="noopener"><span>فتح</span><?= icon('arrow-top-right-on-square') ?></a> ·
            <a href="<?= e(url('admin/settings.php?download=robots')) ?>">تنزيل</a>
        </span>
    </div>

    <p class="field-hint">يُولَّد تلقائياً (يحجب لوحات الإدارة والمستخدم والمجلدات الداخلية ويضيف رابط sitemap). أضف قواعدك الإضافية هنا:</p>

    <form method="post" class="admin-form">
        <?= csrfField() ?>
        <input type="hidden" name="section" value="robots">

        <label for="seo_robots_custom">قواعد إضافية</label>
        <textarea id="seo_robots_custom" name="seo_robots_custom" rows="5" dir="ltr" placeholder="User-agent: GPTBot&#10;Disallow: /"><?= e(setting('seo_robots_custom', '')) ?></textarea>
        <p class="field-hint">الأسطر المسموحة فقط: User-agent / Allow / Disallow / Crawl-delay / Sitemap / Host وتعليقات #. غيرها يُحذف.</p>

        <button type="submit" class="btn btn-primary">حفظ</button>
    </form>

    <label>معاينة الملف الناتج</label>
    <textarea readonly rows="12" dir="ltr" class="admin-textarea"><?= e(robotsTxtContent()) ?></textarea>
</section>

<section class="dashboard-panel" id="sitemap">
    <div class="panel-heading">
        <h2>sitemap.xml</h2>
        <span>
            <a href="<?= e(url('sitemap.xml')) ?>" target="_blank" rel="noopener"><span>فتح</span><?= icon('arrow-top-right-on-square') ?></a> ·
            <a href="<?= e(url('admin/settings.php?download=sitemap')) ?>">تنزيل</a>
        </span>
    </div>

    <p class="field-hint">
        يُولَّد مباشرة من قاعدة البيانات عند كل طلب (لا حاجة لإعادة التوليد): الرئيسية + الصفحات المنشورة
        غير noindex + الصفحات الثابتة. الروابط المختصرة لا تُضاف عمداً. بعد النشر أرسل
        <code dir="ltr"><?= e(url('sitemap.xml')) ?></code> في Google Search Console › Sitemaps.
    </p>

    <p><strong><?= count($entries) ?></strong> عنوان في الخريطة الحالية.</p>

    <?php if ($entries): ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>URL</th><th>آخر تعديل</th><th>الأولوية</th></tr></thead>
            <tbody>
            <?php foreach ($entries as $entry): ?>
                <tr>
                    <td dir="ltr"><?= e($entry['loc']) ?></td>
                    <td><?= e($entry['lastmod'] ?? '—') ?></td>
                    <td><?= e($entry['priority']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<section class="dashboard-panel" id="health">
    <div class="panel-heading"><h2>فحص جاهزية SEO</h2></div>

    <ul class="check-list">
        <?php foreach ($seoChecks as $check): ?>
            <li class="check-<?= $check['ok'] ? 'ok' : 'warn' ?>">
                <span class="check-icon" aria-hidden="true"><?= icon($check['ok'] ? 'check' : 'exclamation-triangle') ?></span>
                <span><strong><?= e($check['label']) ?></strong><small><?= e($check['detail']) ?></small></span>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
