<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

requireGuest();

$pageTitle = 'إنشاء حساب';

$error = '';

if (isPost()) {
    verifyCsrfToken();

    if (honeypotTriggered()) {
        redirect('index.php');
    }

    if (setting('allow_registration', '1') !== '1') {
        $error = 'التسجيل مغلق حالياً.';
    } elseif (!rateLimitAllow('register', 'global', LIMIT_REGISTER_PER_HOUR, 3600)) {
        $error = 'عدد كبير من طلبات التسجيل حالياً. حاول لاحقاً.';
    } else {
        $name = trimmedPost('name', 400);
        $email = strtolower(trimmedPost('email', 190));
        $password = postString('password', 1000);
        $confirmation = postString('password_confirmation', 1000);

        if ($name === '' || mb_strlen($name) > 100) {
            $error = 'يرجى إدخال اسم صحيح.';
        } elseif (
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($email) > 190
        ) {
            $error = 'البريد الإلكتروني غير صالح.';
        } elseif (strlen($password) < 12 || strlen($password) > 72) {
            $error = 'كلمة المرور يجب أن تحتوي على 12 بايت على الأقل وألا تتجاوز 72 بايت.';
        } elseif ($password !== $confirmation) {
            $error = 'كلمتا المرور غير متطابقتين.';
        } else {
            $check = db()->prepare(
                'SELECT id FROM users WHERE email = :email LIMIT 1'
            );

            $check->execute([
                'email' => $email,
            ]);

            if ($check->fetch()) {
                $error = 'هذا البريد الإلكتروني مسجل بالفعل.';
            } else {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);

                    $statement = db()->prepare(
                        'INSERT INTO users
                            (name, email, password, created_at)
                         VALUES
                            (:name, :email, :password, UTC_TIMESTAMP())'
                    );

                    $statement->execute([
                        'name' => $name,
                        'email' => $email,
                        'password' => $hash,
                    ]);

                    loginUser((int) db()->lastInsertId());

                    $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));

                    setFlash('success', 'تم إنشاء حسابك بنجاح.');

                    redirect('dashboard.php');
                } catch (PDOException $exception) {
                    if ($exception->getCode() === '23000') {
                        $error = 'هذا البريد الإلكتروني مسجل بالفعل.';
                    } else {
                        error_log('Registration failed.');
                        $error = 'تعذر إنشاء الحساب حالياً.';
                    }
                }
            }
        }
    }
}

$noindex = true;

require __DIR__ . '/includes/header.php';
?>

<section class="auth-section">
    <div class="auth-card">
        <h1>إنشاء حساب جديد</h1>

        <p>أنشئ حسابك لإدارة روابطك المختصرة.</p>

        <?php if ($error !== ''): ?>
            <?= alertHtml('error', $error) ?>
        <?php endif; ?>

        <form method="post">
            <?= csrfField() ?>
            <?= honeypotField() ?>

            <label for="name">الاسم</label>

            <input
                type="text"
                id="name"
                name="name"
                maxlength="100"
                autocomplete="name"
                required
            >

            <label for="email">البريد الإلكتروني</label>

            <input
                type="email"
                id="email"
                name="email"
                maxlength="190"
                autocomplete="email"
                required
            >

            <label for="password">كلمة المرور</label>

            <input
                type="password"
                id="password"
                name="password"
                minlength="12"
                maxlength="72"
                autocomplete="new-password"
                required
            >

            <label for="password_confirmation">تأكيد كلمة المرور</label>

            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                minlength="12"
                maxlength="72"
                autocomplete="new-password"
                required
            >

            <button type="submit" class="btn btn-primary btn-large">
                إنشاء الحساب
            </button>
        </form>

        <p class="auth-footer">
            لديك حساب بالفعل؟
            <a href="<?= e(url('login.php')) ?>">تسجيل الدخول</a>
        </p>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>