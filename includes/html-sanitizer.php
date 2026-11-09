<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Allow-list HTML sanitizer for CMS pages (Quill output)
|--------------------------------------------------------------------------
| Everything not explicitly allowed is removed. Scripts, styles, iframes,
| forms, event handlers (onclick=...), inline styles and javascript: links
| can never survive, even if an editor's browser is compromised or the
| request is forged.
*/

const RICH_ALLOWED_TAGS = [
    'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'sub', 'sup',
    'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'pre',
    'code', 'a', 'span', 'hr',
];

// Dropped together with everything inside them.
const RICH_DROP_TAGS = [
    'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed',
    'applet', 'form', 'input', 'button', 'textarea', 'select', 'option',
    'link', 'meta', 'base', 'svg', 'math', 'noscript', 'template', 'title',
    'head', 'audio', 'video', 'source', 'img', 'canvas',
];

function sanitizeRichHtml(string $html): string
{
    $html = trim($html);

    if ($html === '' || !class_exists('DOMDocument')) {
        // Without ext-dom we cannot sanitize safely: fall back to plain text.
        return $html === '' ? '' : '<p>' . nl2br(htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8')) . '</p>';
    }

    $previous = libxml_use_internal_errors(true);

    $document = new DOMDocument('1.0', 'UTF-8');

    $document->loadHTML(
        '<?xml encoding="UTF-8"><div id="rich-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
    );

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    $root = $document->getElementById('rich-root');

    if (!$root) {
        return '';
    }

    richSanitizeChildren($root);

    $output = '';

    foreach ($root->childNodes as $child) {
        $output .= $document->saveHTML($child);
    }

    return trim($output);
}

function richSanitizeChildren(DOMNode $parent): void
{
    $children = [];

    foreach ($parent->childNodes as $child) {
        $children[] = $child;
    }

    foreach ($children as $child) {
        if ($child instanceof DOMText) {
            continue;
        }

        if (!($child instanceof DOMElement)) {
            $parent->removeChild($child); // comments, CDATA, processing instructions
            continue;
        }

        $tag = strtolower($child->tagName);

        if (in_array($tag, RICH_DROP_TAGS, true)) {
            $parent->removeChild($child);
            continue;
        }

        richSanitizeChildren($child);

        if (!in_array($tag, RICH_ALLOWED_TAGS, true) && $tag !== 'h1' && $tag !== 'div') {
            richUnwrap($parent, $child);
            continue;
        }

        if ($tag === 'div') {
            richUnwrap($parent, $child);
            continue;
        }

        // Keep a single <h1> per page (the page title): demote editor h1 to h2.
        if ($tag === 'h1') {
            $replacement = $child->ownerDocument->createElement('h2');

            while ($child->firstChild) {
                $replacement->appendChild($child->firstChild);
            }

            $parent->replaceChild($replacement, $child);
            $child = $replacement;
            $tag = 'h2';
        }

        richSanitizeAttributes($child, $tag);
    }
}

function richUnwrap(DOMNode $parent, DOMNode $node): void
{
    while ($node->firstChild) {
        $parent->insertBefore($node->firstChild, $node);
    }

    $parent->removeChild($node);
}

function richSanitizeAttributes(DOMElement $element, string $tag): void
{
    $names = [];

    foreach ($element->attributes as $attribute) {
        $names[] = $attribute->nodeName;
    }

    foreach ($names as $name) {
        $lower = strtolower($name);
        $value = $element->getAttribute($name);

        $element->removeAttribute($name);

        if ($lower === 'class') {
            // Only Quill formatting classes (ql-align-center, ql-direction-rtl, ...)
            $classes = [];

            foreach (preg_split('/\s+/', trim($value)) ?: [] as $class) {
                if (preg_match('/^ql-[a-z0-9-]{1,40}$/', $class)) {
                    $classes[] = $class;
                }
            }

            if ($classes) {
                $element->setAttribute('class', implode(' ', $classes));
            }

            continue;
        }

        if ($tag === 'a' && $lower === 'href') {
            $safe = richSafeHref($value);

            if ($safe !== null) {
                $element->setAttribute('href', $safe);
            }

            continue;
        }

        if ($tag === 'a' && $lower === 'title') {
            $element->setAttribute('title', mb_substr($value, 0, 200));
        }
    }

    if ($tag === 'a') {
        if ($element->hasAttribute('href')) {
            $href = $element->getAttribute('href');
            $external = preg_match('#^https?://#i', $href) === 1;

            if ($external) {
                $element->setAttribute('target', '_blank');
                $element->setAttribute('rel', 'noopener noreferrer nofollow');
            }
        }
    }
}

/**
 * Allow http(s), mailto, tel, site-relative paths and #anchors only.
 */
function richSafeHref(string $value): ?string
{
    // Browsers ignore whitespace/control characters inside the scheme
    // ("java\nscript:"), so check a normalized copy.
    $normalized = preg_replace('/[\x00-\x20\x7F]+/u', '', $value) ?? '';

    if ($normalized === '' || strlen($normalized) > 2000) {
        return null;
    }

    if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $normalized, $match)) {
        $scheme = strtolower($match[1]);

        if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
            return null;
        }

        return $normalized;
    }

    // No scheme: allow only same-site paths and anchors, not "//evil.com".
    if (($normalized[0] === '/' && ($normalized[1] ?? '') !== '/') || $normalized[0] === '#') {
        return $normalized;
    }

    return null;
}

/**
 * Plain-text length of sanitized HTML (to reject "empty" pages like <p><br></p>).
 */
function richTextLength(string $html): int
{
    return mb_strlen(trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
}
