<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin.php';

requireAdmin();

if (adminIsPost()) {
    $action = trimmedPost('action', 20);
    $messageId = (int) filter_var(postString('message_id', 20), FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

    if ($action === 'read_all') {
        db()->exec('UPDATE contact_messages SET is_read = 1 WHERE is_read = 0');
        setFlash('success', 'تم تعليم كل الرسائل كمقروءة.');
    } elseif ($messageId > 0 && in_array($action, ['read', 'unread', 'delete'], true)) {
        if ($action === 'delete') {
            $statement = db()->prepare('DELETE FROM contact_messages WHERE id = :id');
            $statement->execute(['id' => $messageId]);
            auditLog('message.delete', '#' . $messageId);
            setFlash('success', 'تم حذف الرسالة.');
        } else {
            $statement = db()->prepare('UPDATE contact_messages SET is_read = :read WHERE id = :id');
            $statement->execute(['read' => $action === 'read' ? 1 : 0, 'id' => $messageId]);
            setFlash('success', $action === 'read' ? 'تم تعليم الرسالة كمقروءة.' : 'تم تعليم الرسالة كغير مقروءة.');
        }
    } else {
        setFlash('error', 'إجراء غير معروف.');
    }

    $back = [];

    foreach (['q', 'status', 'page'] as $key) {
        $value = postString('r_' . $key, 100);

        if ($value !== '') {
            $back[$key] = $value;
        }
    }

    redirect('admin/messages.php' . ($back ? '?' . http_build_query($back) : ''));
}

$q = adminQueryParam('q');
$status = adminQueryParam('status', 10);
$page = adminPageNumber();
$perPage = 20;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(name LIKE :q OR email LIKE :q OR subject LIKE :q OR message LIKE :q)';
    $params['q'] = likeTerm($q);
}

if ($status === 'unread') {
    $where[] = 'is_read = 0';
} elseif ($status === 'read') {
    $where[] = 'is_read = 1';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count = db()->prepare('SELECT COUNT(*) FROM contact_messages ' . $whereSql);
$count->execute($params);
$total = (int) $count->fetchColumn();

$list = db()->prepare(
    'SELECT id, name, email, subject, message, is_read, created_at
     FROM contact_messages ' . $whereSql . '
     ORDER BY id DESC
     LIMIT ' . (int) $perPage . ' OFFSET ' . paginationOffset($page, $perPage)
);
$list->execute($params);
$messages = $list->fetchAll();

$adminTitle = 'الرسائل';
$adminActive = 'messages';

require __DIR__ . '/../includes/admin-header.php';
?>

<section class="dashboard-panel">
    <form method="get" class="admin-filters">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="الاسم أو البريد أو الموضوع أو نص الرسالة" maxlength="100">
        <select name="status">
            <option value="">كل الرسائل</option>
            <option value="unread" <?= $status === 'unread' ? 'selected' : '' ?>>غير مقروءة</option>
            <option value="read" <?= $status === 'read' ? 'selected' : '' ?>>مقروءة</option>
        </select>
        <button type="submit" class="btn btn-secondary btn-small"><?= icon('magnifying-glass') ?><span>بحث</span></button>
    </form>
</section>

<section class="dashboard-panel">
    <div class="panel-heading">
        <h2>رسائل «اتصل بنا»</h2>

        <div class="panel-tools">
            <span><?= number_format($total) ?></span>

            <?php if (unreadContactCount() > 0): ?>
                <form method="post">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="read_all">
                    <button type="submit" class="btn btn-secondary btn-small"><?= icon('check') ?><span>تعليم الكل كمقروء</span></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <p class="field-hint">تُحفظ الرسائل (الاسم، البريد، الموضوع، النص) دون أي عنوان IP أو بيانات متصفح.</p>

    <?php if (!$messages): ?>
        <div class="empty-state">
            <?= icon('envelope-open') ?>
            <h3>لا توجد رسائل</h3>
            <p>ستظهر هنا الرسائل المرسلة من صفحة «اتصل بنا».</p>
        </div>
    <?php else: ?>
        <div class="message-list">
            <?php foreach ($messages as $row): ?>
                <?php
                $isRead = (int) $row['is_read'] === 1;
                $mailto = 'mailto:' . str_replace('%40', '@', rawurlencode((string) $row['email']))
                    . '?subject=' . rawurlencode('Re: ' . $row['subject']);
                ?>
                <details class="message-item<?= $isRead ? '' : ' is-unread' ?>">
                    <summary>
                        <?= icon($isRead ? 'envelope-open' : 'envelope') ?>
                        <span class="message-subject" dir="auto"><?= e($row['subject']) ?></span>
                        <span class="message-from" dir="auto"><?= e($row['name']) ?></span>
                        <span class="message-date"><?= formatUtc($row['created_at']) ?></span>
                        <?php if (!$isRead): ?><?= adminBadge('جديدة', 'primary') ?><?php endif; ?>
                    </summary>

                    <div class="message-body">
                        <p class="message-meta">
                            <strong dir="auto"><?= e($row['name']) ?></strong>
                            <a href="<?= e($mailto) ?>" dir="ltr"><?= e($row['email']) ?></a>
                        </p>

                        <div class="message-text" dir="auto"><?= nl2br(e($row['message'])) ?></div>

                        <div class="table-actions">
                            <a class="btn btn-primary btn-small" href="<?= e($mailto) ?>"><?= icon('envelope') ?><span>رد بالبريد</span></a>

                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="<?= $isRead ? 'unread' : 'read' ?>">
                                <input type="hidden" name="message_id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="r_q" value="<?= e($q) ?>">
                                <input type="hidden" name="r_status" value="<?= e($status) ?>">
                                <input type="hidden" name="r_page" value="<?= (int) $page ?>">
                                <button type="submit" class="btn btn-secondary btn-small">
                                    <?= icon($isRead ? 'envelope' : 'check') ?><span><?= $isRead ? 'تعليم كغير مقروءة' : 'تعليم كمقروءة' ?></span>
                                </button>
                            </form>

                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="message_id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="r_q" value="<?= e($q) ?>">
                                <input type="hidden" name="r_status" value="<?= e($status) ?>">
                                <input type="hidden" name="r_page" value="<?= (int) $page ?>">
                                <button type="submit" class="btn btn-danger btn-small" data-confirm="حذف الرسالة نهائياً؟">
                                    <?= icon('trash') ?><span>حذف</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= adminPager($total, $perPage, $page, ['q' => $q, 'status' => $status]) ?>
</section>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
