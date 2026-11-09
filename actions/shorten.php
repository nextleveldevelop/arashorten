<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_once dirname(__DIR__) . '/includes/url-service.php';

if (!isPost()) {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

verifyCsrfToken();

$user = currentUser();

if (honeypotTriggered()) {
    // Pretend success to bots, create nothing.
    redirect($user ? 'dashboard.php' : 'index.php');
}

if (!$user && setting('allow_guest_shortening', '1') !== '1') {
    setFlash('warning', 'اختصار الروابط متاح للأعضاء فقط.');
    redirect('register.php');
}

$destination = $_POST['destination_url'] ?? '';
$customCode = $_POST['custom_code'] ?? null;

if (is_string($customCode)) {
    $customCode = trim($customCode);
}

if (!is_string($destination)) {
    setFlash('error', 'الرابط غير صالح.');
    redirect($user ? 'dashboard.php' : 'index.php');
}

if ($customCode !== null && !is_string($customCode)) {
    setFlash('error', 'الرمز المخصص غير صالح.');
    redirect($user ? 'dashboard.php' : 'index.php');
}

$result = createShortUrl(
    $destination,
    $user ? (int) $user['id'] : null,
    $customCode !== '' ? $customCode : null
);

if ($result['success']) {
    $_SESSION['last_short_url'] = $result['short_url'];

    setFlash('success', $result['message']);
} else {
    setFlash('error', $result['message']);
}

redirect($user ? 'dashboard.php' : 'index.php');