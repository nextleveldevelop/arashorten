<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin.php';

requireAdmin();

if (adminIsPost()) {
    $action = trimmedPost('action', 30);
    $pageId = (int) filter_var(postString('page_id', 20), FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    $page = $pageId > 0 ? findPageById($pageId) : null;

    if ($page === null) {
        setFlash('error', 'الصفحة غير موجودة.');
    } elseif ($action === 'toggle_publish') {
        $new = (int) $page['is_published'] === 1 ? 0 : 1;
        db()->prepare('UPDATE pages SET is_published = :p, updated_at = UTC_TIMESTAMP() WHERE id = :id')
            ->execute(['p' => $new, 'id' => $pageId]);
        auditLog($new === 1 ? 'page.publish' : 'page.unpublish', $page['slug']);
        setFlash('success', $new === 1 ? 'تم نشر الصفحة.' : 'تم إخفاء الصفحة.');
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM pages WHERE id = :id')->execute(['id' => $pageId]);
        auditLog('page.delete', $page['slug']);
        setFlash('success', 'تم حذف الصفحة.');
    }

    redirect('admin/pages.php');
}

$pages = db()->query(
    'SELECT id, slug, title, is_published, show_in_footer, noindex, sort_order, updated_at
     FROM pages ORDER BY sort_order ASC, id DESC'
)->fetchAll();

$adminTitle = 'الصفحات';
$adminActive = 'pages';

require __DIR__ . '/../includes/admin-header.php';
?>

<section class="dashboard-panel">
    <div class="panel-heading">
        <h2>الصفحات الأساسية</h2>
    </div>

    <p class="field-hint">
        صفحات «من نحن» و«الشروط» و«الخصوصية» لها نص افتراضي. عند إنشاء صفحة منشورة بنفس
        المعرّف (about / terms / privacy) تحل محل النص الافتراضي على نفس الرابط.
    </p>

    <div class="table-responsive">
        <table class="data-table">
            <tbody>
            <?php foreach (builtinPageTitles() as $slug => $title): ?>
                <?php $existing = findPageBySlug($slug, false); ?>
                <tr>
                    <td><strong><?= e($title) ?></strong> <small dir="ltr">/<?= e($slug) ?></small></td>
                    <td>
                        <?php if ($existing && (int) $existing['is_published'] === 1): ?>
                            <?= adminBadge('مخصّصة (من المحرر)', 'success') ?>
                        <?php elseif ($existing): ?>
                            <?= adminBadge('مسودة، يُعرض النص الافتراضي', 'warning') ?>
                        <?php else: ?>
                            <?= adminBadge('النص الافتراضي') ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a class="btn btn-secondary btn-small" href="<?= e(url('admin/page-edit.php?' . ($existing ? 'id=' . (int) $existing['id'] : 'preset=' . $slug))) ?>">
                            <?= $existing ? 'تعديل' : 'تخصيص بالمحرر' ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="dashboard-panel">
    <div class="panel-heading">
        <h2>كل الصفحات</h2>
        <a class="btn btn-primary btn-small" href="<?= e(url('admin/page-edit.php')) ?>"><?= icon('plus') ?><span>صفحة جديدة</span></a>
    </div>

    <?php if (!$pages): ?>
        <div class="empty-state"><h3>لا توجد صفحات بعد</h3><p>أنشئ صفحة مثل «من نحن» أو «شروط الخدمة».</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="data-table">
            <thead><tr><th>العنوان</th><th>الرابط</th><th>الحالة</th><th>الظهور</th><th>آخر تحديث</th><th>إجراءات</th></tr></thead>
            <tbody>
            <?php foreach ($pages as $row): ?>
                <tr>
                    <td><strong><?= e($row['title']) ?></strong></td>
                    <td dir="ltr"><a href="<?= e(pageUrl($row['slug'])) ?>" target="_blank" rel="noopener"><?= e(substr(pageUrl($row['slug']), strlen(APP_URL))) ?></a></td>
                    <td><?= (int) $row['is_published'] === 1 ? adminBadge('منشورة', 'success') : adminBadge('مسودة', 'warning') ?></td>
                    <td>
                        <?= (int) $row['show_in_footer'] === 1 ? adminBadge('في التذييل', 'info') : '' ?>
                        <?= (int) $row['noindex'] === 1 ? adminBadge('noindex', 'muted') : '' ?>
                    </td>
                    <td><?= formatUtc($row['updated_at']) ?></td>
                    <td>
                        <div class="table-actions">
                            <a class="btn btn-secondary btn-small" href="<?= e(url('admin/page-edit.php?id=' . (int) $row['id'])) ?>"><?= icon('pencil-square') ?><span>تعديل</span></a>

                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle_publish">
                                <input type="hidden" name="page_id" value="<?= (int) $row['id'] ?>">
                                <button class="btn btn-secondary btn-small"><?= (int) $row['is_published'] === 1 ? 'إخفاء' : 'نشر' ?></button>
                            </form>

                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="page_id" value="<?= (int) $row['id'] ?>">
                                <button class="btn btn-danger btn-small" data-confirm="حذف الصفحة نهائياً؟"><?= icon('trash') ?><span>حذف</span></button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
