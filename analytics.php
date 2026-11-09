<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/url-service.php';
require_once __DIR__ . '/includes/analytics.php';

requireLogin();

$user = currentUser();

$urlId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$isAdminView = isAdmin();

if ($urlId === false || $urlId === null) {
    $link = null;
} elseif ($isAdminView) {
    $link = findUrlById((int) $urlId); // admins can inspect any link
} else {
    $link = findUserUrl((int) $urlId, (int) $user['id']);
}

if ($link === null) {
    setFlash('error', 'الرابط غير موجود.');
    redirect($isAdminView ? 'admin/links.php' : 'dashboard.php');
}

$days = 30;

// Fill days without clicks with zero so the chart has a continuous axis.
$daily = [];

foreach (getDailyClicksByUrl((int) $link['id'], $days) as $row) {
    $daily[(string) $row['click_date']] = (int) $row['total'];
}

$labels = [];
$values = [];

for ($i = $days - 1; $i >= 0; $i--) {
    $date = gmdate('Y-m-d', time() - ($i * 86400));
    $labels[] = $date;
    $values[] = $daily[$date] ?? 0;
}

$shortUrl = shortUrlFor($link['short_code']);

$pageTitle = 'إحصائيات الرابط';
$noindex = true;

require __DIR__ . '/includes/header.php';
?>

<main class="dashboard-section">
    <div class="container">

        <div class="dashboard-heading">
            <div>
                <span class="eyebrow">الإحصائيات</span>
                <h1>إحصائيات الرابط</h1>
            </div>

            <a href="<?= e(url($isAdminView ? 'admin/links.php' : 'dashboard.php')) ?>" class="btn btn-secondary">
                رجوع
            </a>
        </div>

        <section class="dashboard-panel">
            <div class="analytics-info">
                <div>
                    <span>الرابط المختصر</span>
                    <strong dir="ltr"><?= e($shortUrl) ?></strong>
                </div>
                <div>
                    <span>الرابط الأصلي</span>
                    <strong dir="ltr"><?= e($link['destination_url']) ?></strong>
                </div>
                <div>
                    <span>إجمالي النقرات</span>
                    <strong><?= number_format((int) $link['clicks']) ?></strong>
                </div>
            </div>
        </section>

        <section class="dashboard-panel">
            <div class="panel-heading">
                <h2>النقرات خلال آخر <?= (int) $days ?> يوماً (UTC)</h2>
            </div>

            <div class="chart-container">
                <canvas id="clickChart" height="280"></canvas>
            </div>

            <script type="application/json" id="chartData"><?= json_encode(
                ['labels' => $labels, 'values' => $values],
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
            ) ?></script>

            <p class="privacy-note">
                لا يتم تسجيل عناوين IP أو معلومات المتصفح. تُخزَّن فقط
                الأعداد والتوقيت.
            </p>
        </section>

    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
