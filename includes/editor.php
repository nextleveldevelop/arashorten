<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Quill 2 rich-text editor (Admin > Pages)
|--------------------------------------------------------------------------
| The editor works out of the box, with nothing to install:
|
|   1. If assets/vendor/quill/quill.js + quill.snow.css exist (installed with
|      `php cron/install-quill.php`) they are served from your own domain
|      and the Content-Security-Policy stays fully strict.
|   2. Otherwise Quill 2.0.3 is loaded from the jsDelivr CDN. The version is
|      pinned, and the CSP of this one admin page is widened to exactly those
|      two file URLs (not to the whole CDN).
|
| Optionally add 'quill_sri_js' / 'quill_sri_css' to config.local.php so the
| browser refuses a CDN file whose content differs from what you approved
| (`php cron/install-quill.php` prints the values).
|
| If the editor cannot load at all (CDN blocked by an ad-blocker/firewall),
| assets/js/admin-editor.js keeps the plain textarea usable and shows a
| notice. The server sanitizes the HTML on save in every case.
*/

const QUILL_VERSION = '2.0.3';
const QUILL_CDN_JS = 'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js';
const QUILL_CDN_CSS = 'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css';

/**
 * True when a self-hosted copy of Quill is installed.
 */
function quillLocalInstalled(): bool
{
    $js = APP_ROOT . '/assets/vendor/quill/quill.js';
    $css = APP_ROOT . '/assets/vendor/quill/quill.snow.css';

    // A real Quill build is hundreds of KB; this also rejects empty/partial files.
    return is_file($js) && filesize($js) > 100000 && is_file($css) && filesize($css) > 5000;
}

/**
 * Kept for backward compatibility with the production checks.
 */
function quillInstalled(): bool
{
    return quillLocalInstalled();
}

/**
 * Where the editor files come from.
 *
 * @return array{mode:string, js:string, css:string, js_integrity:string, css_integrity:string}
 */
function quillAssets(): array
{
    if (quillLocalInstalled()) {
        return [
            'mode' => 'local',
            'js' => assetUrl('assets/vendor/quill/quill.js'),
            'css' => assetUrl('assets/vendor/quill/quill.snow.css'),
            'js_integrity' => '',
            'css_integrity' => '',
        ];
    }

    return [
        'mode' => 'cdn',
        'js' => QUILL_CDN_JS,
        'css' => QUILL_CDN_CSS,
        'js_integrity' => QUILL_SRI_JS,
        'css_integrity' => QUILL_SRI_CSS,
    ];
}

/**
 * Widen the Content-Security-Policy for the editor page only, and only when
 * the CDN copy is used. Must be called before any output is sent.
 */
function applyEditorCsp(): void
{
    $assets = quillAssets();

    if ($assets['mode'] !== 'cdn' || headers_sent()) {
        return;
    }

    // header() replaces the policy that bootstrap.php already sent.
    header('Content-Security-Policy: ' . contentSecurityPolicy([$assets['js']], [$assets['css']]));
}

/**
 * Attribute string for a <script>/<link> that loads an editor file.
 * CDN files get CORS mode (required for integrity checks) and no referrer.
 */
function quillTagAttributes(array $assets, string $kind): string
{
    if ($assets['mode'] !== 'cdn') {
        return '';
    }

    $attributes = ' crossorigin="anonymous" referrerpolicy="no-referrer"';
    $integrity = (string) $assets[$kind . '_integrity'];

    if ($integrity !== '') {
        $attributes .= ' integrity="' . htmlspecialchars($integrity, ENT_QUOTES, 'UTF-8') . '"';
    }

    return $attributes;
}

/**
 * Icons used by the editor toolbar (Heroicons), keyed by purpose. Passed to
 * JavaScript as JSON in a data attribute (see admin/page-edit.php).
 *
 * @return array<string, string>
 */
function editorIcons(): array
{
    return iconSet([
        'bold', 'italic', 'underline', 'strikethrough',
        'h2', 'h3',
        'list-bullet', 'numbered-list',
        'align-right', 'align-center', 'align-justify',
        'chat-bubble-bottom-center-text', 'code-bracket', 'link', 'x-circle',
    ]);
}
