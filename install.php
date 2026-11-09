<?php
declare(strict_types=1);

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

/*
| The installer does NOT use PHP sessions: on many hosts the session folder is
| not writable or the page is cached, which made the form token fail. The token
| is a signed, time-limited value instead (see installToken()).
*/
function installKey(): string
{
    return hash('sha256', __FILE__ . '|' . (string) filemtime(__FILE__) . '|' . (string) ($_SERVER['HTTP_HOST'] ?? ''));
}

function installToken(): string
{
    $time = (string) time();

    return $time . '.' . hash_hmac('sha256', $time, installKey());
}

function installTokenValid(mixed $token): bool
{
    if (!is_string($token) || !preg_match('/^(\d{9,12})\.([a-f0-9]{64})$/', $token, $m)) {
        return false;
    }

    $age = time() - (int) $m[1];

    if ($age < -60 || $age > 7200) {
        return false;
    }

    return hash_equals(hash_hmac('sha256', $m[1], installKey()), $m[2]);
}

/**
 * A browser form post comes from this same host. Reject cross-site posts.
 */
function installSameOrigin(): bool
{
    $source = (string) ($_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '');

    if ($source === '') {
        return true;
    }

    $host = strtolower((string) parse_url($source, PHP_URL_HOST));

    return $host === strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
}

$root = __DIR__;

require_once $root . '/includes/schema.php';
$lockFile = $root . '/storage/installed.lock';
$configFile = $root . '/config.local.php';

if (file_exists($lockFile) || file_exists($configFile)) {
    exit('
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <meta charset="UTF-8">
        <title>التطبيق مثبت</title>
        <body style="font-family:Arial;text-align:center;padding:60px">
            <h2>التطبيق مثبت بالفعل</h2>
            <p>تم تعطيل صفحة التثبيت لأسباب أمنية.</p>
            <a href="index.php">العودة إلى الموقع</a>
        </body>
        </html>
    ');
}

$error = '';
$success = false;
$installerRemoved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!installSameOrigin() || !installTokenValid($_POST['_install_token'] ?? '')) {
        $error = 'الطلب غير صالح. يرجى تحديث الصفحة.';
    } else {
        $input = static function (string $key, string $default = ''): string {
            $value = $_POST[$key] ?? $default;

            return is_string($value) ? trim($value) : $default;
        };

        $appName = $input('app_name', 'AraShorten');
        $appUrl = rtrim($input('app_url'), '/');

        $dbHost = $input('db_host', 'localhost');
        $dbName = $input('db_name');
        $dbUser = $input('db_user');
        $dbPass = is_string($_POST['db_pass'] ?? null) ? $_POST['db_pass'] : '';

        $adminName = $input('admin_name');
        $adminEmail = strtolower($input('admin_email'));
        $adminPassword = is_string($_POST['admin_password'] ?? null) ? $_POST['admin_password'] : '';
        $adminConfirm = is_string($_POST['admin_password_confirmation'] ?? null) ? $_POST['admin_password_confirmation'] : '';

        if (
            $appName === '' ||
            strlen($appName) > 100 ||
            !filter_var($appUrl, FILTER_VALIDATE_URL) ||
            !in_array(
                strtolower((string) parse_url($appUrl, PHP_URL_SCHEME)),
                ['http', 'https'],
                true
            ) ||
            $dbHost === '' ||
            $dbName === '' ||
            $dbUser === '' ||
            !preg_match('/^[A-Za-z0-9._:\\-]{1,255}$/', $dbHost)
        ) {
            $error = 'يرجى إدخال جميع البيانات المطلوبة بشكل صحيح.';
        } elseif (
            !preg_match('/^[a-zA-Z0-9_]+$/', $dbName)
        ) {
            $error = 'اسم قاعدة البيانات غير صالح.';
        } elseif ($adminName === '' || mb_strlen($adminName) > 100) {
            $error = 'يرجى إدخال اسم المدير.';
        } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL) || strlen($adminEmail) > 190) {
            $error = 'بريد المدير غير صالح.';
        } elseif (strlen($adminPassword) < 12 || strlen($adminPassword) > 72) {
            $error = 'كلمة مرور المدير يجب أن تكون بين 12 و72 بايت.';
        } elseif ($adminPassword !== $adminConfirm) {
            $error = 'كلمتا مرور المدير غير متطابقتين.';
        } else {
            try {
                $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";

                $pdo = new PDO(
                    $dsn,
                    $dbUser,
                    $dbPass,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );

                foreach (schemaTables() as $query) {
                    $pdo->exec($query);
                }

                $settings = schemaDefaultSettings() + ['schema_version' => (string) SCHEMA_VERSION];

                $statement = $pdo->prepare(
                    'INSERT IGNORE INTO settings
                        (setting_key, setting_value)
                     VALUES (:key, :value)'
                );

                foreach ($settings as $key => $value) {
                    $statement->execute([
                        'key' => $key,
                        'value' => $value,
                    ]);
                }

                $adminStatement = $pdo->prepare(
                    "INSERT INTO users (name, email, password, role, created_at)
                     VALUES (:name, :email, :password, 'admin', UTC_TIMESTAMP())
                     ON DUPLICATE KEY UPDATE
                        name = VALUES(name), password = VALUES(password), role = 'admin'"
                );

                $adminStatement->execute([
                    'name' => $adminName,
                    'email' => $adminEmail,
                    'password' => password_hash($adminPassword, PASSWORD_DEFAULT),
                ]);

                $configuration = [
                    'app_name' => $appName,
                    'app_url' => $appUrl,
                    'app_env' => 'production',
                    'timezone' => 'UTC',
                    'app_key' => bin2hex(random_bytes(32)),
                    'force_https' => strtolower((string) parse_url($appUrl, PHP_URL_SCHEME)) === 'https',
                    'db_host' => $dbHost,
                    'db_name' => $dbName,
                    'db_user' => $dbUser,
                    'db_pass' => $dbPass,
                ];

                $content = "<?php\n";
                $content .= "declare(strict_types=1);\n\n";
                $content .= "return " . var_export($configuration, true) . ";\n";

                if (file_put_contents($configFile, $content, LOCK_EX) === false) {
                    throw new RuntimeException(
                        'تعذر إنشاء ملف الإعدادات. تحقق من صلاحيات المجلد.'
                    );
                }

                @chmod($configFile, 0640);

                if (!is_dir($root . '/storage')) {
                    if (!mkdir($root . '/storage', 0750, true)) {
                        throw new RuntimeException('تعذر إنشاء مجلد التخزين.');
                    }
                }

                if (!is_dir($root . '/storage/sessions')) {
                    @mkdir($root . '/storage/sessions', 0700, true);
                }

                if (
                    file_put_contents(
                        $lockFile,
                        'installed',
                        LOCK_EX
                    ) === false
                ) {
                    throw new RuntimeException('تعذر إنشاء ملف حماية التثبيت.');
                }

                $success = true;

                // Remove the installer so nobody can re-run it.
                $installerRemoved = @unlink(__FILE__);
            } catch (Throwable $exception) {
                error_log('Installation failed.');

                if (file_exists($configFile) && !$success) {
                    @unlink($configFile);
                }

                $error = 'فشل التثبيت. تحقق من بيانات قاعدة البيانات وصلاحيات الملفات.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تثبيت AraShorten</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 30px 15px;
            background: #f4f6fa;
            font-family: Arial, sans-serif;
            color: #172033;
        }
        .container {
            max-width: 540px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 14px;
            box-shadow: 0 8px 35px rgba(0,0,0,.06);
        }
        h1 { margin-top: 0; }
        label {
            display: block;
            margin: 17px 0 7px;
            font-weight: bold;
        }
        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #dce1ea;
            border-radius: 7px;
            font-size: 15px;
        }
        button {
            width: 100%;
            margin-top: 25px;
            padding: 14px;
            border: 0;
            border-radius: 7px;
            background: #315efb;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }
        .error {
            padding: 12px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 7px;
        }
        .success {
            padding: 15px;
            background: #dcfce7;
            color: #166534;
            border-radius: 7px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>تثبيت AraShorten</h1>
    <p>أدخل معلومات الموقع وقاعدة البيانات لإكمال التثبيت.</p>

    <?php if ($success): ?>
        <div class="success">
            <h3>تم تثبيت التطبيق بنجاح!</h3>
            <p>تم إنشاء جداول قاعدة البيانات وملف الإعدادات.</p>
            <?php if ($installerRemoved): ?>
                <p>تم حذف ملف install.php تلقائياً.</p>
            <?php else: ?>
                <p><strong>مهم:</strong> تعذر الحذف التلقائي، احذف ملف install.php من الخادم الآن.</p>
            <?php endif; ?>
            <a href="login.php">تسجيل الدخول إلى لوحة الإدارة</a>
        </div>
    <?php else: ?>

        <?php if ($error !== ''): ?>
            <div class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post">
            <input
                type="hidden"
                name="_install_token"
                value="<?= htmlspecialchars(installToken(), ENT_QUOTES, 'UTF-8') ?>"
            >

            <label>اسم الموقع</label>
            <input name="app_name" value="AraShorten" required maxlength="100">

            <label>رابط الموقع</label>
            <input
                type="url"
                name="app_url"
                placeholder="https://example.com"
                required
            >

            <label>Database Host</label>
            <input name="db_host" value="localhost" required>

            <label>Database Name</label>
            <input name="db_name" required>

            <label>Database Username</label>
            <input name="db_user" required>

            <label>Database Password</label>
            <input type="password" name="db_pass">

            <h2>حساب المدير</h2>

            <label>الاسم</label>
            <input name="admin_name" required maxlength="100" autocomplete="name">

            <label>البريد الإلكتروني</label>
            <input type="email" name="admin_email" required maxlength="190" autocomplete="username">

            <label>كلمة المرور (12 حرفاً على الأقل)</label>
            <input type="password" name="admin_password" required minlength="12" maxlength="72" autocomplete="new-password">

            <label>تأكيد كلمة المرور</label>
            <input type="password" name="admin_password_confirmation" required minlength="12" maxlength="72" autocomplete="new-password">

            <button type="submit">تثبيت التطبيق</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>