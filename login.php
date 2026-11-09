<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

requireGuest();

$pageTitle = 'تسجيل الدخول';

$error = '';

if (isPost()) {
    verifyCsrfToken();

    $email = strtolower(trimmedPost('email', 190));
    $password = postString('password', 1000);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'البريد الإلكتروني غير صالح.';
    } elseif ($password === '') {
        $error = 'يرجى إدخال كلمة المرور.';
    } elseif (rateLimitCount('login', $email, LIMIT_LOGIN_WINDOW) >= LIMIT_LOGIN_FAILURES) {
        $error = 'محاولات تسجيل دخول فاشلة كثيرة. حاول مجدداً بعد بضع دقائق.';
    } else {
        $statement = db()->prepare(
            'SELECT id, password, is_active
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $statement->execute([
            'email' => $email,
        ]);

        $user = $statement->fetch();

        // Always run a password hash check so response time does not reveal
        // whether the e-mail exists.
        $hash = $user['password'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
        $valid = password_verify($password, $hash) && is_array($user);

        if ($valid && (int) $user['is_active'] !== 1) {
            $error = 'تم تعطيل هذا الحساب. يرجى التواصل مع الإدارة.';
        } elseif ($valid) {
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);

                $update = db()->prepare(
                    'UPDATE users SET password = :password WHERE id = :id'
                );

                $update->execute([
                    'password' => $newHash,
                    'id' => $user['id'],
                ]);
            }

            rateLimitReset('login', $email);

            loginUser((int) $user['id']);

            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

            setFlash('success', 'مرحباً بك مجدداً.');

            if (isAdmin()) {
                auditLog('admin.login');
                redirect('admin/');
            }

            redirect('dashboard.php');
        }

        if (!$valid) {
            rateLimitHit('login', $email, LIMIT_LOGIN_WINDOW);

            $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة.';
        }
    }
}

$noindex = true;

require __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card">
        <h1>تسجيل الدخول</h1>

        <p>أدخل بيانات حسابك للمتابعة.</p>

        <?php if ($error !== ''): ?>
            <?= alertHtml('error', $error) ?>
        <?php endif; ?>

        <form method="post">
            <?= csrfField() ?>

            <label for="email">البريد الإلكتروني</label>

            <input
                type="email"
                id="email"
                name="email"
                autocomplete="email"
                maxlength="190"
                required
            >

            <label for="password">كلمة المرور</label>

            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >

            <button type="submit" class="btn btn-primary btn-large">
                تسجيل الدخول
            </button>
        </form>

        <p class="auth-footer">
            ليس لديك حساب؟
            <a href="<?= e(url('register.php')) ?>">إنشاء حساب</a>
        </p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>