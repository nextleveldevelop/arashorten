<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin.php';

requireAdmin();

if (adminIsPost()) {
    $action = trimmedPost('action', 30);
    $linkId = (int) filter_var(postString('link_id', 20), FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    $link = $linkId > 0 ? findUrlById($linkId) : null;

    if ($link === null) {
        setFlash('error', 'الرابط غير موجود.');
        redirect('admin/links.php');
    }

    if ($action === 'toggle') {
        $new = (int) $link['is_active'] === 1 ? 0 : 1;
        db()->prepare('UPDATE urls SET is_active = :a WHERE id = :id')->execute(['a' => $new, 'id' => $linkId]);
        auditLog($new === 1 ? 'link.enable' : 'link.disable', '/' . $link['short_code']);
        setFlash('success', $new === 1 ? 'تم تفعيل الرابط.' : 'تم تعطيل الرابط.');
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM urls WHERE id = :id')->execute(['id' => $linkId]);
        auditLog('link.delete', '/' . $link['short_code'] . ' → ' . mb_substr($link['destination_url'], 0, 200));
        setFlash('success', 'تم حذف الرابط.');
    } elseif ($action === 'block_domain') {
        $host = strtolower(rtrim((string) parse_url($link['destination_url'], PHP_URL_HOST), '.'));

        if ($host === '' || !preg_match('/^[a-z0-9.-]{3,253}$/', $host)) {
            setFlash('error', 'تعذر تحديد النطاق.');
        } else {
            $blocked = blockedDomains();

            if (!in_array($host, $blocked, true)) {
                $blocked[] = $host;
                saveSetting('blocked_domains', implode("\n", $blocked));
            }

            // Disable every active link that points to that exact host (or a subdomain)
            $ids = [];

            $rows = db()->query('SELECT id, destination_url FROM urls WHERE is_active = 1 LIMIT 20000')->fetchAll();

            foreach ($rows as $row) {
                $h = strtolower(rtrim((string) parse_url($row['destination_url'], PHP_URL_HOST), '.'));

                if ($h === $host || substr($h, -strlen('.' . $host)) === '.' . $host) {
                    $ids[] = (int) $row['id'];
                }
            }

            foreach (array_chunk($ids, 500) as $chunk) {
                db()->exec('UPDATE urls SET is_active = 0 WHERE id IN (' . implode(',', $chunk) . ')');
            }

            auditLog('domain.block', $host . ' (' . count($ids) . ' links disabled)');
            setFlash('success', 'تم حظر النطاق ' . $host . ' وتعطيل ' . count($ids) . ' رابط.');
        }
    } else {
        setFlash('error', 'إجراء غير معروف.');
    }

    $back = [];
    foreach (['q', 'status', 'owner', 'page'] as $k) {
        $v = postString('r_' . $k, 100);
        if ($v !== '') {
            $back[$k] = $v;
        }
    }

    redirect('admin/links.php' . ($back ? '?' . http_build_query($back) : ''));
}

$q = adminQueryParam('q');
$status = adminQueryParam('status', 10);
$owner = adminQueryParam('owner', 10);
$page = adminPageNumber();
$perPage = 25;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(u.short_code LIKE :q OR u.destination_url LIKE :q OR us.email LIKE :q)';
    $params['q'] = likeTerm($q);
}

if ($status === 'active') {
    $where[] = 'u.is_active = 1';
} elseif ($status === 'disabled') {
    $where[] = 'u.is_active = 0';
}

if ($owner === 'guest') {
    $where[] = 'u.user_id IS NULL';
} elseif ($owner === 'user') {
    $where[] = 'u.user_id IS NOT NULL';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count = db()->prepare('SELECT COUNT(*) FROM urls u LEFT JOIN users us ON us.id = u.user_id ' . $whereSql);
$count->execute($params);
$total = (int) $count->fetchColumn();

$list = db()->prepare(
    'SELECT u.id, u.short_code, u.destination_url, u.clicks, u.is_active, u.created_at, us.email AS owner_email
     FROM urls u
     LEFT JOIN users us ON us.id = u.user_id ' . $whereSql . '
     ORDER BY u.id DESC
     LIMIT ' . (int) $perPage . ' OFFSET ' . paginationOffset($page, $perPage)
);
$list->execute($params);
$links = $list->fetchAll();

$adminTitle = 'الروابط';
$adminActive = 'links';

require __DIR__ . '/../includes/admin-header.php';
?>

<section class="dashboard-panel">
    <form method="get" class="admin-filters">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="الرمز أو الوجهة أو بريد المالك" maxlength="100">
        <select name="status">
            <option value="">كل الحالات</option>
            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>فعّال</option>
            <option value="disabled" <?= $status === 'disabled' ? 'selected' : '' ?>>معطّل</option>
        </select>
        <select name="owner">
            <option value="">كل المالكين</option>
            <option value="user" <?= $owner === 'user' ? 'selected' : '' ?>>أعضاء</option>
            <option value="guest" <?= $owner === 'guest' ? 'selected' : '' ?>>زوار</option>
        </select>
        <button type="submit" class="btn btn-secondary btn-small"><?= icon('magnifying-glass') ?><span>بحث</span></button>
    </form>
</section>

<section class="dashboard-panel">
    <div class="panel-heading">
        <h2>كل الروابط</h2>
        <span><?= number_format($total) ?></span>
    </div>

    <?php if (!$links): ?>
        <div class="empty-state"><p>لا توجد نتائج.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
            <tr><th>الرابط المختصر</th><th>الوجهة</th><th>المالك</th><th>النقرات</th><th>الحالة</th><th>التاريخ</th><th>إجراءات</th></tr>
            </thead>
            <tbody>
            <?php foreach ($links as $row): ?>
                <?php
                $hidden = '';
                foreach (['q' => $q, 'status' => $status, 'owner' => $owner, 'page' => $page > 1 ? (string) $page : ''] as $k => $v) {
                    if ($v !== '') {
                        $hidden .= '<input type="hidden" name="r_' . e($k) . '" value="' . e($v) . '">';
                    }
                }
                ?>
                <tr>
                    <td dir="ltr">
                        <a href="<?= e(shortUrlFor($row['short_code'])) ?>" target="_blank" rel="noopener noreferrer">/<?= e($row['short_code']) ?></a>
                    </td>
                    <td class="destination-cell" dir="ltr"><span title="<?= e($row['destination_url']) ?>"><?= e($row['destination_url']) ?></span></td>
                    <td dir="ltr"><?= e($row['owner_email'] ?? 'زائر') ?></td>
                    <td><?= number_format((int) $row['clicks']) ?></td>
                    <td><?= (int) $row['is_active'] === 1 ? adminBadge('فعّال', 'success') : adminBadge('معطّل', 'danger') ?></td>
                    <td><?= formatUtc($row['created_at']) ?></td>
                    <td>
                        <details class="admin-details admin-row-actions">
                            <summary>إدارة</summary>

                            <a class="btn btn-secondary btn-small" href="<?= e(url('analytics.php?id=' . (int) $row['id'])) ?>">الإحصائيات</a>

                            <form method="post">
                                <?= csrfField() ?><?= $hidden ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="link_id" value="<?= (int) $row['id'] ?>">
                                <button class="btn btn-secondary btn-small"><?= (int) $row['is_active'] === 1 ? 'تعطيل' : 'تفعيل' ?></button>
                            </form>

                            <form method="post">
                                <?= csrfField() ?><?= $hidden ?>
                                <input type="hidden" name="action" value="block_domain">
                                <input type="hidden" name="link_id" value="<?= (int) $row['id'] ?>">
                                <button class="btn btn-secondary btn-small" data-confirm="حظر نطاق الوجهة وتعطيل كل الروابط المرتبطة به؟">حظر النطاق</button>
                            </form>

                            <form method="post">
                                <?= csrfField() ?><?= $hidden ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="link_id" value="<?= (int) $row['id'] ?>">
                                <button class="btn btn-danger btn-small" data-confirm="حذف الرابط نهائياً مع إحصائياته؟"><?= icon('trash') ?><span>حذف</span></button>
                            </form>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?= adminPager($total, $perPage, $page, ['q' => $q, 'status' => $status, 'owner' => $owner]) ?>
</section>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
