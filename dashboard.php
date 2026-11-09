<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/url-service.php';

requireLogin();

$user = currentUser();

$pageTitle = 'لوحة التحكم';
$noindex = true;

$flash = getFlash();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = paginationOffset($page, $perPage);

$countStatement = db()->prepare(
    'SELECT COUNT(*) FROM urls WHERE user_id = :user_id'
);

$countStatement->execute([
    'user_id' => $user['id'],
]);

$totalUrls = (int) $countStatement->fetchColumn();

$totalPages = max(1, (int) ceil($totalUrls / $perPage));

$page = min($page, $totalPages);
$offset = paginationOffset($page, $perPage);

$statement = db()->prepare(
    'SELECT id, short_code, destination_url, clicks, created_at
     FROM urls
     WHERE user_id = :user_id
     ORDER BY id DESC
     LIMIT :limit OFFSET :offset'
);

$statement->bindValue(':user_id', (int) $user['id'], PDO::PARAM_INT);
$statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
$statement->bindValue(':offset', $offset, PDO::PARAM_INT);

$statement->execute();

$urls = $statement->fetchAll();

$statsStatement = db()->prepare(
    'SELECT
        COUNT(*) AS total_urls,
        COALESCE(SUM(clicks), 0) AS total_clicks
     FROM urls
     WHERE user_id = :user_id'
);

$statsStatement->execute([
    'user_id' => $user['id'],
]);

$stats = $statsStatement->fetch();

require __DIR__ . '/includes/header.php';
?>

<main class="dashboard-section">
    <div class="container">

        <div class="dashboard-heading">
            <div>
                <span class="eyebrow">مساحة العمل</span>
                <h1>مرحباً، <?= e($user['name']) ?></h1>
                <p>إدارة ومتابعة جميع روابطك المختصرة.</p>
            </div>

            <a href="<?= e(url('profile.php')) ?>" class="btn btn-secondary">
                <?= icon('user-circle') ?><span>الملف الشخصي</span>
            </a>
        </div>

        <?php if ($flash): ?>
            <?= alertHtml($flash['type'], $flash['message']) ?>
        <?php endif; ?>

        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon"><?= icon('link') ?></div>
                <span>إجمالي الروابط</span>
                <strong><?= number_format((int) $stats['total_urls']) ?></strong>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><?= icon('cursor-arrow-rays') ?></div>
                <span>إجمالي النقرات</span>
                <strong><?= number_format((int) $stats['total_clicks']) ?></strong>
            </div>

        </div>

        <section class="dashboard-panel">
            <div class="panel-heading">
                <h2>اختصار رابط جديد</h2>
            </div>

            <form
                method="post"
                action="<?= e(url('actions/shorten.php')) ?>"
                class="shorten-form"
            >
                <?= csrfField() ?>
                <?= honeypotField() ?>

                <label for="destination_url">الرابط الأصلي</label>

                <input
                    type="url"
                    id="destination_url"
                    name="destination_url"
                    placeholder="https://example.com"
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

                <button type="submit" class="btn btn-primary">
                    <?= icon('plus') ?><span>إنشاء الرابط</span>
                </button>
            </form>
        </section>

        <section class="dashboard-panel">
            <div class="panel-heading">
                <h2>روابطي</h2>
                <span><?= number_format($totalUrls) ?> رابط</span>
            </div>

            <?php if (!$urls): ?>

                <div class="empty-state">
                    <?= icon('link') ?>
                    <h3>لا توجد روابط بعد</h3>
                    <p>ابدأ بإنشاء أول رابط مختصر لك.</p>
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>الرابط المختصر</th>
                                <th>الرابط الأصلي</th>
                                <th>النقرات</th>
                                <th>تاريخ الإنشاء</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($urls as $item): ?>
                                <?php
                                $shortUrl = shortUrlFor($item['short_code']);
                                ?>

                                <tr>
                                    <td>
                                        <a
                                            href="<?= e($shortUrl) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            <span dir="ltr"><?= e(preg_replace('#^https?://#', '', $shortUrl)) ?></span>
                                        </a>
                                    </td>

                                    <td class="destination-cell">
                                        <span title="<?= e($item['destination_url']) ?>">
                                            <?= e($item['destination_url']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= number_format((int) $item['clicks']) ?>
                                    </td>

                                    <td>
                                        <?= e($item['created_at']) ?>
                                    </td>

                                    <td>
                                        <div class="table-actions">

                                            <button
                                                type="button"
                                                class="btn btn-small btn-secondary btn-copy"
                                                data-copy-text="<?= e($shortUrl) ?>"
                                            >
                                                <?= icon('clipboard-document') ?><?= icon('check', 'icon-done') ?><span class="btn-label">نسخ</span>
                                            </button>

                                            <a
                                                class="btn btn-small btn-secondary"
                                                href="<?= e(url('analytics.php?id=' . (int) $item['id'])) ?>"
                                            >
                                                <?= icon('chart-bar') ?><span>الإحصائيات</span>
                                            </a>

                                            <form
                                                method="post"
                                                action="<?= e(url('actions/delete-url.php')) ?>"
                                                data-confirm="هل تريد حذف هذا الرابط؟"
                                            >
                                                <?= csrfField() ?>

                                                <input
                                                    type="hidden"
                                                    name="url_id"
                                                    value="<?= (int) $item['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-small btn-danger"
                                                >
                                                    <?= icon('trash') ?><span>حذف</span>
                                                </button>
                                            </form>

                                        </div>
                                    </td>
                                </tr>

                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="pagination" aria-label="التنقل بين الصفحات">

                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>"><?= icon('chevron-right') ?><span>السابق</span></a>
                        <?php endif; ?>

                        <span>
                            الصفحة <?= $page ?> من <?= $totalPages ?>
                        </span>

                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>"><span>التالي</span><?= icon('chevron-left') ?></a>
                        <?php endif; ?>

                    </nav>
                <?php endif; ?>

            <?php endif; ?>
        </section>

    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>