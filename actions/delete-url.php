<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/url-service.php';

if (!isPost()) {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

requireLogin();

verifyCsrfToken();

$user = currentUser();

$urlId = filter_var(
    $_POST['url_id'] ?? null,
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1,
        ],
    ]
);

if ($urlId === false || $urlId === null) {
    setFlash('error', 'معرف الرابط غير صالح.');
    redirect('dashboard.php');
}

$deleted = deleteUserUrl(
    (int) $urlId,
    (int) $user['id']
);

if ($deleted) {
    setFlash('success', 'تم حذف الرابط بنجاح.');
} else {
    setFlash('error', 'الرابط غير موجود أو لا تملك صلاحية حذفه.');
}

redirect('dashboard.php');