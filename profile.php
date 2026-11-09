<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

requireLogin();

$user = currentUser();

$pageTitle = 'الملف الشخصي';

$error = '';
$flash = getFlash();

if (isPost()) {
    verifyCsrfToken();

    $name = trimmedPost('name', 400);
    $current = postString('current_password', 1000);
    $password = postString('password', 1000);
    $confirmation = postString('password_confirmation', 1000);

    $storedHash = null;

    if ($password !== '') {
        $row = db()->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
        $row->execute(['id' => $user['id']]);
        $storedHash = (string) $row->fetchColumn();
    }

    if ($name === '' || mb_strlen($name) > 100) {
        $error = 'الاسم غير صالح.';
    } elseif ($password !== '' && strlen($password) < 12) {
        $error = 'كلمة المرور الجديدة يجب أن تحتوي على 12 بايت على الأقل.';
    } elseif (strlen($password) > 72) {
        $error = 'كلمة المرور طويلة جداً.';
    } elseif ($password !== $confirmation) {
        $error = 'كلمتا المرور غير متطابقتين.';
    } elseif (
        $password !== '' &&
        !rateLimitAllow('password-change', (string) $user['id'], 10, 900)
    ) {
        $error = 'محاولات كثيرة. حاول لاحقاً.';
    } elseif (
        $password !== '' &&
        ($current === '' || !password_verify($current, (string) $storedHash))
    ) {
        $error = 'كلمة المرور الحالية غير صحيحة.';
    } else {
        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $statement = db()->prepare(
                'UPDATE users
                 SET name = :name,
                     password = :password,
                     updated_at = UTC_TIMESTAMP()
                 WHERE id = :id'
            );

            $statement->execute([
                'name' => $name,
                'password' => $hash,
                'id' => $user['id'],
            ]);

            session_regenerate_id(true);
        } else {
            $statement = db()->prepare(
                'UPDATE users
                 SET name = :name,
                     updated_at = UTC_TIMESTAMP()
                 WHERE id = :id'
            );

            $statement->execute([
                'name' => $name,
                'id' => $user['id'],
            ]);
        }

        setFlash('success', 'تم تحديث بيانات الحساب.');

        redirect('profile.php');
    }
}

$noindex = true;

$user = currentUser();

require __DIR__ . '/includes/header.php';
?>

<main class="auth-section">
    <div class="auth-card">
        <h1>الملف الشخصي</h1>

        <p>إدارة معلومات حسابك.</p>

        <?php if ($flash): ?>
            <?= alertHtml($flash['type'], $flash['message']) ?>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <?= alertHtml('error', $error) ?>
        <?php endif; ?>

        <form method="post">
            <?= csrfField() ?>

            <label for="name">الاسم</label>

            <input
                type="text"
                id="name"
                name="name"
                value="<?= e($user['name']) ?>"
                maxlength="100"
                required
            >

            <label>البريد الإلكتروني</label>

            <input
                type="email"
                value="<?= e($user['email']) ?>"
                disabled
            >

            <p class="field-hint">
                لا يمكن تغيير البريد الإلكتروني من هذه الصفحة.
            </p>

            <hr>

            <h3>تغيير كلمة المرور</h3>

            <label for="current_password">كلمة المرور الحالية</label>

            <input
                type="password"
                id="current_password"
                name="current_password"
                autocomplete="current-password"
            >

            <label for="password">كلمة المرور الجديدة</label>

            <input
                type="password"
                id="password"
                name="password"
                minlength="12"
                maxlength="72"
                autocomplete="new-password"
            >

            <label for="password_confirmation">تأكيد كلمة المرور الجديدة</label>

            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                minlength="12"
                maxlength="72"
                autocomplete="new-password"
            >

            <button type="submit" class="btn btn-primary btn-large">
                حفظ التغييرات
            </button>
        </form>

    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>