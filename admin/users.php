<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/admin.php';

requireAdmin();

$me = currentUser();
$meId = (int) $me['id'];

if (adminIsPost()) {
    $action = trimmedPost('action', 30);
    $targetId = (int) filter_var(postString('user_id', 20), FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);

    if ($action === 'create') {
        $name = trimmedPost('name', 400);
        $email = strtolower(trimmedPost('email', 190));
        $password = postString('password', 1000);
        $role = trimmedPost('role', 10) === 'admin' ? 'admin' : 'user';

        if ($name === '' || mb_strlen($name) > 100) {
            setFlash('error', 'اسم غير صالح.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlash('error', 'بريد إلكتروني غير صالح.');
        } elseif (strlen($password) < 12 || strlen($password) > 72) {
            setFlash('error', 'كلمة المرور يجب أن تكون بين 12 و72 بايت.');
        } else {
            try {
                $statement = db()->prepare(
                    'INSERT INTO users (name, email, password, role, created_at)
                     VALUES (:name, :email, :password, :role, UTC_TIMESTAMP())'
                );
                $statement->execute([
                    'name' => $name,
                    'email' => $email,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role,
                ]);

                auditLog('user.create', $email . ' (' . $role . ')');
                setFlash('success', 'تم إنشاء المستخدم.');
            } catch (PDOException $exception) {
                setFlash('error', $exception->getCode() === '23000' ? 'البريد مسجل بالفعل.' : 'تعذر إنشاء المستخدم.');
            }
        }

        redirect('admin/users.php');
    }

    $statement = db()->prepare('SELECT id, email, role, is_active FROM users WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $targetId]);
    $target = $statement->fetch();

    if (!$target) {
        setFlash('error', 'المستخدم غير موجود.');
        redirect('admin/users.php');
    }

    $isSelf = (int) $target['id'] === $meId;

    switch ($action) {
        case 'set_role':
            $newRole = trimmedPost('role', 10) === 'admin' ? 'admin' : 'user';

            if ($isSelf) {
                setFlash('error', 'لا يمكنك تغيير دورك بنفسك.');
            } elseif ($target['role'] === 'admin' && $newRole === 'user' && countActiveAdmins() <= 1) {
                setFlash('error', 'لا يمكن إزالة آخر مدير.');
            } else {
                db()->prepare('UPDATE users SET role = :r, updated_at = UTC_TIMESTAMP() WHERE id = :id')
                    ->execute(['r' => $newRole, 'id' => $targetId]);
                auditLog('user.role', $target['email'] . ' → ' . $newRole);
                setFlash('success', 'تم تحديث الدور.');
            }
            break;

        case 'toggle_active':
            if ($isSelf) {
                setFlash('error', 'لا يمكنك تعطيل حسابك.');
            } elseif ($target['role'] === 'admin' && (int) $target['is_active'] === 1 && countActiveAdmins() <= 1) {
                setFlash('error', 'لا يمكن تعطيل آخر مدير.');
            } else {
                $new = (int) $target['is_active'] === 1 ? 0 : 1;
                db()->prepare('UPDATE users SET is_active = :a, updated_at = UTC_TIMESTAMP() WHERE id = :id')
                    ->execute(['a' => $new, 'id' => $targetId]);
                auditLog($new === 1 ? 'user.activate' : 'user.deactivate', $target['email']);
                setFlash('success', $new === 1 ? 'تم تفعيل الحساب.' : 'تم تعطيل الحساب.');
            }
            break;

        case 'disable_links':
            $statement = db()->prepare('UPDATE urls SET is_active = 0 WHERE user_id = :id');
            $statement->execute(['id' => $targetId]);
            auditLog('user.disable_links', $target['email'] . ' (' . $statement->rowCount() . ')');
            setFlash('success', 'تم تعطيل ' . $statement->rowCount() . ' رابط.');
            break;

        case 'set_password':
            $password = postString('password', 1000);

            if (strlen($password) < 12 || strlen($password) > 72) {
                setFlash('error', 'كلمة المرور يجب أن تكون بين 12 و72 بايت.');
            } else {
                db()->prepare('UPDATE users SET password = :p, updated_at = UTC_TIMESTAMP() WHERE id = :id')
                    ->execute(['p' => password_hash($password, PASSWORD_DEFAULT), 'id' => $targetId]);
                auditLog('user.password_reset', $target['email']);
                setFlash('success', 'تم تعيين كلمة مرور جديدة.');
            }
            break;

        case 'delete':
            if ($isSelf) {
                setFlash('error', 'لا يمكنك حذف حسابك.');
            } elseif ($target['role'] === 'admin' && countActiveAdmins() <= 1) {
                setFlash('error', 'لا يمكن حذف آخر مدير.');
            } else {
                if (postString('delete_links', 5) === '1') {
                    db()->prepare('DELETE FROM urls WHERE user_id = :id')->execute(['id' => $targetId]);
                }

                db()->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $targetId]);
                auditLog('user.delete', $target['email']);
                setFlash('success', 'تم حذف المستخدم.');
            }
            break;

        default:
            setFlash('error', 'إجراء غير معروف.');
    }

    redirect('admin/users.php');
}

// ---- listing
$q = adminQueryParam('q');
$roleFilter = adminQueryParam('role', 10);
$statusFilter = adminQueryParam('status', 10);
$page = adminPageNumber();
$perPage = 25;

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(u.name LIKE :q OR u.email LIKE :q)';
    $params['q'] = likeTerm($q);
}

if (in_array($roleFilter, ['admin', 'user'], true)) {
    $where[] = 'u.role = :role';
    $params['role'] = $roleFilter;
}

if ($statusFilter === 'active') {
    $where[] = 'u.is_active = 1';
} elseif ($statusFilter === 'disabled') {
    $where[] = 'u.is_active = 0';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count = db()->prepare('SELECT COUNT(*) FROM users u ' . $whereSql);
$count->execute($params);
$total = (int) $count->fetchColumn();

$list = db()->prepare(
    'SELECT u.id, u.name, u.email, u.role, u.is_active, u.created_at, u.last_login_at,
            (SELECT COUNT(*) FROM urls x WHERE x.user_id = u.id) AS links_count
     FROM users u ' . $whereSql . '
     ORDER BY u.id DESC
     LIMIT ' . (int) $perPage . ' OFFSET ' . paginationOffset($page, $perPage)
);
$list->execute($params);
$users = $list->fetchAll();

$adminTitle = 'المستخدمون';
$adminActive = 'users';

require __DIR__ . '/../includes/admin-header.php';
?>

<section class="dashboard-panel">
    <form method="get" class="admin-filters">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="بحث بالاسم أو البريد" maxlength="100">
        <select name="role">
            <option value="">كل الأدوار</option>
            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>مدير</option>
            <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>مستخدم</option>
        </select>
        <select name="status">
            <option value="">كل الحالات</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>فعّال</option>
            <option value="disabled" <?= $statusFilter === 'disabled' ? 'selected' : '' ?>>معطّل</option>
        </select>
        <button type="submit" class="btn btn-secondary btn-small"><?= icon('magnifying-glass') ?><span>بحث</span></button>
    </form>
</section>

<section class="dashboard-panel">
    <details class="admin-details">
        <summary>+ إضافة مستخدم</summary>

        <form method="post" class="admin-form admin-form-inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="create">

            <label>الاسم <input name="name" required maxlength="100"></label>
            <label>البريد <input type="email" name="email" required maxlength="190" dir="ltr"></label>
            <label>كلمة المرور (12+ حرفاً) <input type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
            <label>الدور
                <select name="role">
                    <option value="user">مستخدم</option>
                    <option value="admin">مدير</option>
                </select>
            </label>

            <button type="submit" class="btn btn-primary btn-small"><?= icon('plus') ?><span>إنشاء</span></button>
        </form>
    </details>
</section>

<section class="dashboard-panel">
    <div class="panel-heading">
        <h2>المستخدمون</h2>
        <span><?= number_format($total) ?></span>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
            <tr>
                <th>المستخدم</th><th>الدور</th><th>الحالة</th><th>الروابط</th>
                <th>التسجيل</th><th>آخر دخول</th><th>إجراءات</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $row): ?>
                <?php $self = (int) $row['id'] === $meId; ?>
                <tr>
                    <td>
                        <strong><?= e($row['name']) ?></strong><?= $self ? ' (أنت)' : '' ?><br>
                        <small dir="ltr"><?= e($row['email']) ?></small>
                    </td>
                    <td><?= $row['role'] === 'admin' ? adminBadge('مدير', 'primary') : adminBadge('مستخدم') ?></td>
                    <td><?= (int) $row['is_active'] === 1 ? adminBadge('فعّال', 'success') : adminBadge('معطّل', 'danger') ?></td>
                    <td><?= number_format((int) $row['links_count']) ?></td>
                    <td><?= formatUtc($row['created_at']) ?></td>
                    <td><?= formatUtc($row['last_login_at']) ?></td>
                    <td>
                        <?php if ($self): ?>
                            —
                        <?php else: ?>
                            <details class="admin-details admin-row-actions">
                                <summary>إدارة</summary>

                                <form method="post" class="admin-form-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="set_role">
                                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                    <select name="role">
                                        <option value="user" <?= $row['role'] === 'user' ? 'selected' : '' ?>>مستخدم</option>
                                        <option value="admin" <?= $row['role'] === 'admin' ? 'selected' : '' ?>>مدير</option>
                                    </select>
                                    <button class="btn btn-secondary btn-small" data-confirm="تغيير دور هذا المستخدم؟">تغيير الدور</button>
                                </form>

                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                    <button class="btn btn-secondary btn-small"><?= (int) $row['is_active'] === 1 ? 'تعطيل الحساب' : 'تفعيل الحساب' ?></button>
                                </form>

                                <form method="post">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="disable_links">
                                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                    <button class="btn btn-secondary btn-small" data-confirm="تعطيل كل روابط هذا المستخدم؟">تعطيل كل روابطه</button>
                                </form>

                                <form method="post" class="admin-form-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="set_password">
                                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                    <input type="password" name="password" placeholder="كلمة مرور جديدة" minlength="12" maxlength="72" autocomplete="new-password" required>
                                    <button class="btn btn-secondary btn-small" data-confirm="تعيين كلمة مرور جديدة؟">تعيين</button>
                                </form>

                                <form method="post" class="admin-form-inline">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                                    <label class="check-inline"><input type="checkbox" name="delete_links" value="1"> حذف روابطه</label>
                                    <button class="btn btn-danger btn-small" data-confirm="حذف المستخدم نهائياً؟"><?= icon('trash') ?><span>حذف</span></button>
                                </form>
                            </details>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= adminPager($total, $perPage, $page, ['q' => $q, 'role' => $roleFilter, 'status' => $statusFilter]) ?>
</section>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
