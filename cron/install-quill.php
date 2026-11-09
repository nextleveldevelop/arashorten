<?php
declare(strict_types=1);

/*
| OPTIONAL helper (command line). The editor already works without it: it
| loads the pinned Quill 2.0.3 from the jsDelivr CDN (see includes/editor.php).
| Run this when you prefer one of the following:
|
|   php cron/install-quill.php            self-host Quill in assets/vendor/quill/
|                                         (no third-party request, strictest CSP)
|   php cron/install-quill.php --sri-only keep using the CDN, but print the
|                                         integrity hashes to add to
|                                         config.local.php (browser then refuses
|                                         a CDN file that was tampered with)
|
| If your server has no outbound access, run it on your own computer and
| upload assets/vendor/quill/quill.js + quill.snow.css by hand.
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$version = '2.0.3';
$base = 'https://cdn.jsdelivr.net/npm/quill@' . $version . '/dist/';
$sriOnly = in_array('--sri-only', $argv, true);

// file => [text it must contain, minimum size in bytes, config key]
$files = [
    'quill.js' => ['Quill', 100000, 'quill_sri_js'],
    'quill.snow.css' => ['.ql-editor', 5000, 'quill_sri_css'],
];

$target = dirname(__DIR__) . '/assets/vendor/quill';

if (!$sriOnly && !is_dir($target) && !mkdir($target, 0755, true)) {
    fwrite(STDERR, "Cannot create {$target}\n");
    exit(1);
}

function downloadFile(string $url): ?string
{
    $data = false;

    if ((bool) ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => ['timeout' => 30, 'user_agent' => 'AraShorten-installer'],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $data = @file_get_contents($url, false, $context);
    }

    if ($data === false && function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'AraShorten-installer',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $result = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $data = ($result !== false && $status === 200) ? (string) $result : false;
    }

    return $data === false ? null : $data;
}

$config = [];

foreach ($files as $name => [$mustContain, $minSize, $configKey]) {
    echo "Downloading {$name} ... ";

    $data = downloadFile($base . $name);

    if ($data === null || strlen($data) < $minSize || strpos($data, $mustContain) === false) {
        fwrite(STDERR, "FAILED (no network access, or unexpected content)\n");
        exit(1);
    }

    $sri = 'sha384-' . base64_encode(hash('sha384', $data, true));
    $config[$configKey] = $sri;

    if (!$sriOnly) {
        if (file_put_contents($target . '/' . $name, $data, LOCK_EX) === false) {
            fwrite(STDERR, "Cannot write {$target}/{$name}\n");
            exit(1);
        }

        @chmod($target . '/' . $name, 0644);
    }

    echo 'ok (' . strlen($data) . " bytes)\n";
}

if (!$sriOnly) {
    // Quill 1.x file name from earlier AraShorten versions: not compatible, remove it.
    if (is_file($target . '/quill.min.js')) {
        @unlink($target . '/quill.min.js');
        echo "Removed old quill.min.js (Quill 1.x)\n";
    }

    echo "\nDone. Quill {$version} is now served from your own domain (assets/vendor/quill/).\n";
    echo "Nothing else to configure.\n";
    exit(0);
}

echo "\nAdd these two lines to config.local.php (CDN mode):\n\n";

foreach ($config as $key => $sri) {
    echo "    '{$key}' => '{$sri}',\n";
}

echo "\nThese hashes are for Quill {$version} exactly. If you change the pinned version, run this again.\n";
