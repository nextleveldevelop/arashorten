<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'اتصل بنا';

$error = '';

if (isPost()) {
    verifyCsrfToken();

    if (honeypotTriggered()) {
        // Bots: pretend it worked, store nothing.
        setFlash('success', 'تم إرسال رسالتك. شكراً لتواصلك معنا.');
        redirect('contact.php');
    }

    $name = trimmedPost('name', 400);
    $email = strtolower(trimmedPost('email', 400));
    $subject = trimmedPost('subject', 600);
    $message = trimmedPost('message', 20000);

    if ($name === '' || mb_strlen($name) > 100) {
        $error = 'يرجى إدخال اسم صحيح.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $error = 'يرجى إدخال بريد إلكتروني صحيح.';
    } elseif ($subject === '' || mb_strlen($subject) > 150) {
        $error = 'يرجى إدخال موضوع الرسالة.';
    } elseif ($message === '' || mb_strlen($message) > 5000) {
        $error = 'يرجى إدخال رسالة لا تتجاوز 5000 حرف.';
    } elseif (
        !rateLimitAllow('contact', guestRateSubject(), LIMIT_CONTACT_PER_HOUR, 3600) ||
        !rateLimitAllow('contact-global', 'all', 200, 3600)
    ) {
        $error = 'أرسلت رسائل كثيرة خلال وقت قصير. حاول مجدداً لاحقاً.';
    } elseif (!saveContactMessage($name, $email, $subject, $message)) {
        $error = 'تعذر إرسال الرسالة حالياً. يرجى المحاولة لاحقاً.';
    } else {
        setFlash('success', 'تم إرسال رسالتك. سنرد عليك عبر البريد الإلكتروني عند الحاجة.');
        redirect('contact.php');
    }
}

require __DIR__ . '/includes/header.php';
?>

<main class="content-section">
    <div class="container">
        <div class="auth-card">
            <h1>اتصل بنا</h1>

            <p>أرسل لنا رسالتك وسنقرؤها في أقرب وقت.</p>

            <?php if ($error !== ''): ?>
                <?= alertHtml('error', $error) ?>
            <?php endif; ?>

            <form method="post">
                <?= csrfField() ?>
                <?= honeypotField() ?>

                <label for="name">الاسم</label>
                <input type="text" id="name" name="name" maxlength="100" value="<?= e(postString('name', 100)) ?>" autocomplete="name" required>

                <label for="email">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" maxlength="190" value="<?= e(postString('email', 190)) ?>" autocomplete="email" required>

                <label for="subject">الموضوع</label>
                <input type="text" id="subject" name="subject" maxlength="150" value="<?= e(postString('subject', 150)) ?>" required>

                <label for="message">الرسالة</label>
                <textarea id="message" name="message" rows="6" maxlength="5000" required><?= e(postString('message', 5000)) ?></textarea>

                <button type="submit" class="btn btn-primary btn-large">
                    <?= icon('envelope') ?><span>إرسال</span>
                </button>
            </form>
        </div>
    </div>
</main>

<?php require __DIR__ . '/includes/footer.php'; ?>
