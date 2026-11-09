<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin.php';

requireAdmin();

$q = adminQueryParam('q');
$page = adminPageNumber();
$perPage = 50;

$where = '';
$params = [];

if ($q !== '') {
    $where = 'WHERE (a.action LIKE :q OR a.details LIKE :q OR u.email LIKE :q)';
    $params['q'] = likeTerm($q);
}

$count = db()->prepare('SELECT COUNT(*) FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id ' . $where);
$count->execute($params);
$total = (int) $count->fetchColumn();

$list = db()->prepare(
    'SELECT a.id, a.action, a.details, a.created_at, u.email
     FROM audit_logs a
     LEFT JOIN users u ON u.id = a.user_id ' . $where . '
     ORDER BY a.id DESC
     LIMIT ' . (int) $perPage . ' OFFSET ' . paginationOffset($page, $perPage)
);
$list->execute($params);
$rows = $list->fetchAll();

$adminTitle = 'سجل النشاط';
$adminActive = 'audit';

require __DIR__ . '/../includes/admin-header.php';
?>

<section class="dashboard-panel">
    <form method="get" class="admin-filters">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="بحث في الإجراء أو التفاصيل أو المدير" maxlength="100">
        <button type="submit" class="btn btn-secondary btn-small"><?= icon('magnifying-glass') ?><span>بحث</span></button>
    </form>
</section>

<section class="dashboard-panel">
    <div class="panel-heading">
        <h2>إجراءات الإدارة</h2>
        <span><?= number_format($total) ?></span>
    </div>

    <p class="field-hint">لا تُسجَّل عناوين IP أو بيانات المتصفح، فقط من فعل ماذا ومتى.</p>

    <?php if (!$rows): ?>
        <div class="empty-state"><p>لا توجد سجلات بعد.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>الوقت</th><th>المدير</th><th>الإجراء</th><th>التفاصيل</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= formatUtc($row['created_at']) ?></td>
                    <td dir="ltr"><?= e($row['email'] ?? 'محذوف') ?></td>
                    <td dir="ltr"><code><?= e($row['action']) ?></code></td>
                    <td dir="auto"><?= e($row['details']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?= adminPager($total, $perPage, $page, ['q' => $q]) ?>
</section>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
