<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin.php';

requireAdmin();

$stats = adminStats();
$series = siteDailySeries('url_clicks', 'clicked_at', 30);
$checks = systemChecks();

$pdo = db();

$topLinks = $pdo->query(
    'SELECT u.id, u.short_code, u.destination_url, u.clicks, u.is_active, us.email AS owner_email
     FROM urls u
     LEFT JOIN users us ON us.id = u.user_id
     ORDER BY u.clicks DESC, u.id DESC
     LIMIT 10'
)->fetchAll();

$recentLinks = $pdo->query(
    'SELECT u.id, u.short_code, u.destination_url, u.created_at, us.email AS owner_email
     FROM urls u
     LEFT JOIN users us ON us.id = u.user_id
     ORDER BY u.id DESC
     LIMIT 8'
)->fetchAll();

$recentUsers = $pdo->query(
    'SELECT id, name, email, role, is_active, created_at
     FROM users ORDER BY id DESC LIMIT 8'
)->fetchAll();

$warnings = count(array_filter($checks, static fn (array $c): bool => $c['status'] === 'warn'));

$adminTitle = 'لوحة المعلومات';
$adminActive = 'dashboard';
$loadChart = true;

require __DIR__ . '/../includes/admin-header.php';
?>

<div class="admin-stats">
    <div class="stat-card"><div class="stat-icon"><?= icon('users') ?></div><span>المستخدمون</span><strong><?= number_format($stats['users_total']) ?></strong><small><?= number_format($stats['users_7d']) ?> جديد آخر 7 أيام · <?= number_format($stats['admins']) ?> مدير</small></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('link') ?></div><span>الروابط</span><strong><?= number_format($stats['links_total']) ?></strong><small><?= number_format($stats['links_active']) ?> فعّال · <?= number_format($stats['links_disabled']) ?> معطّل · <?= number_format($stats['links_guest']) ?> زوار</small></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('cursor-arrow-rays') ?></div><span>إجمالي النقرات</span><strong><?= number_format($stats['clicks_total']) ?></strong><small>اليوم: <?= number_format($stats['clicks_today']) ?></small></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('chart-bar') ?></div><span>نقرات آخر 7 / 30 يوماً</span><strong><?= number_format($stats['clicks_7d']) ?> / <?= number_format($stats['clicks_30d']) ?></strong><small><?= number_format($stats['links_7d']) ?> رابط جديد هذا الأسبوع</small></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('document-text') ?></div><span>الصفحات</span><strong><?= number_format($stats['pages_published']) ?></strong><small>من <?= number_format($stats['pages_total']) ?> (منشورة)</small></div>
    <div class="stat-card"><div class="stat-icon"><?= icon('no-symbol') ?></div><span>حسابات معطّلة</span><strong><?= number_format($stats['users_inactive']) ?></strong><small>لا يمكنها تسجيل الدخول</small></div>
</div>

<section class="dashboard-panel">
    <div class="panel-heading">
        <h2>نقرات الموقع آخر 30 يوماً (UTC)</h2>
    </div>

    <div class="chart-container">
        <canvas id="clickChart" height="280"></canvas>
    </div>

    <script type="application/json" id="chartData"><?= json_encode(
        $series,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
    ) ?></script>
</section>

<div class="admin-two-col">

    <section class="dashboard-panel">
        <div class="panel-heading">
            <h2>أكثر الروابط نقراً</h2>
            <a href="<?= e(url('admin/links.php')) ?>">عرض الكل</a>
        </div>

        <?php if (!$topLinks): ?>
            <div class="empty-state"><p>لا توجد روابط بعد.</p></div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>الرابط</th><th>المالك</th><th>النقرات</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($topLinks as $row): ?>
                        <tr>
                            <td dir="ltr">
                                <a href="<?= e(shortUrlFor($row['short_code'])) ?>" target="_blank" rel="noopener noreferrer">/<?= e($row['short_code']) ?></a>
                                <?php if ((int) $row['is_active'] !== 1): ?><?= adminBadge('معطّل', 'danger') ?><?php endif; ?>
                            </td>
                            <td dir="ltr"><?= e($row['owner_email'] ?? 'زائر') ?></td>
                            <td><?= number_format((int) $row['clicks']) ?></td>
                            <td><a href="<?= e(url('analytics.php?id=' . (int) $row['id'])) ?>">إحصائيات</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="dashboard-panel">
        <div class="panel-heading">
            <h2>فحص جاهزية الإنتاج</h2>
            <?= $warnings > 0 ? adminBadge($warnings . ' تنبيه', 'warning') : adminBadge('كل شيء سليم', 'success') ?>
        </div>

        <ul class="check-list">
            <?php foreach ($checks as $check): ?>
                <li class="check-<?= e($check['status']) ?>">
                    <span class="check-icon" aria-hidden="true"><?= icon($check['status'] === 'ok' ? 'check' : ($check['status'] === 'warn' ? 'exclamation-triangle' : 'information-circle')) ?></span>
                    <span>
                        <strong><?= e($check['label']) ?></strong>
                        <small><?= e($check['detail']) ?></small>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>

</div>

<div class="admin-two-col">

    <section class="dashboard-panel">
        <div class="panel-heading">
            <h2>أحدث الروابط</h2>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>الرمز</th><th>الوجهة</th><th>المالك</th><th>التاريخ</th></tr></thead>
                <tbody>
                <?php foreach ($recentLinks as $row): ?>
                    <tr>
                        <td dir="ltr">/<?= e($row['short_code']) ?></td>
                        <td class="destination-cell" dir="ltr"><span title="<?= e($row['destination_url']) ?>"><?= e($row['destination_url']) ?></span></td>
                        <td dir="ltr"><?= e($row['owner_email'] ?? 'زائر') ?></td>
                        <td><?= formatUtc($row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="dashboard-panel">
        <div class="panel-heading">
            <h2>أحدث المستخدمين</h2>
            <a href="<?= e(url('admin/users.php')) ?>">عرض الكل</a>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>الاسم</th><th>البريد</th><th>الدور</th><th>التسجيل</th></tr></thead>
                <tbody>
                <?php foreach ($recentUsers as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?></td>
                        <td dir="ltr"><?= e($row['email']) ?></td>
                        <td><?= $row['role'] === 'admin' ? adminBadge('مدير', 'primary') : adminBadge('مستخدم') ?></td>
                        <td><?= formatUtc($row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
