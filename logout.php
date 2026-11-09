<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

if (!isPost()) {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

verifyCsrfToken();

logoutUser();

startSecureSession();

setFlash('success', 'تم تسجيل الخروج بنجاح.');

redirect('login.php');