'use strict';

/*
 * Turns the #contentField textarea into a Quill 2 rich-text editor.
 *
 * Loaded together with Quill 2.0.3 (self-hosted or from the CDN, see
 * includes/editor.php). If Quill cannot be loaded for any reason the
 * textarea stays fully usable and a notice is shown. The server sanitizes
 * the HTML on save (includes/html-sanitizer.php) in every case, so nothing
 * here is a security boundary.
 *
 * Quill 2 specifics this file takes care of:
 *   - quill.root.innerHTML writes bullet lists as <ol data-list="bullet">,
 *     which would become numbered lists. getSemanticHTML() returns proper
 *     <ul>/<ol>/<pre> markup, so that is what gets saved.
 *   - dangerouslyPasteHTML() is replaced by clipboard.convert({ html }).
 *   - Toolbar icons are the site's Heroicons (passed in data-icons).
 */
document.addEventListener('DOMContentLoaded', function () {
    const field = document.getElementById('contentField');
    const host = document.getElementById('editorHost');
    const form = document.getElementById('pageForm');
    const notice = document.getElementById('editorFallbackNotice');

    if (!field || !host || !form) {
        return;
    }

    function showFallback() {
        host.hidden = true;
        field.hidden = false;

        if (notice) {
            notice.hidden = false;
        }
    }

    // CDN blocked, offline, or integrity check failed: keep the textarea.
    if (typeof window.Quill === 'undefined') {
        showFallback();
        return;
    }

    try {
        initEditor(window.Quill);
    } catch (error) {
        if (window.console) {
            window.console.error('Quill failed to start:', error);
        }

        showFallback();
    }

    function initEditor(Quill) {
        applyHeroicons(Quill);

        // Only the formats the server-side sanitizer keeps. 'indent' and
        // 'direction' have no toolbar button but are accepted so that older
        // saved content is not stripped when re-opened.
        const formats = [
            'header', 'bold', 'italic', 'underline', 'strike',
            'list', 'indent', 'align', 'direction',
            'blockquote', 'code-block', 'link'
        ];

        host.hidden = false;

        const quill = new Quill(host, {
            theme: 'snow',
            placeholder: 'اكتب محتوى الصفحة هنا...',
            formats: formats,
            modules: {
                toolbar: [
                    [{ header: 2 }, { header: 3 }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ align: ['', 'center', 'justify'] }],
                    ['blockquote', 'code-block', 'link'],
                    ['clean']
                ]
            }
        });

        // Load the existing (already sanitized) HTML into the editor.
        const initialHtml = field.value.trim();

        if (initialHtml !== '') {
            quill.setContents(quill.clipboard.convert({ html: initialHtml }), 'silent');
        }

        quill.root.setAttribute('dir', 'rtl');
        quill.root.setAttribute('lang', 'ar');
        quill.root.setAttribute('aria-label', 'محتوى الصفحة');

        labelToolbar(quill.getModule('toolbar').container);

        // The textarea is only the fallback / transport field now.
        field.hidden = true;
        field.removeAttribute('required');
        field.setAttribute('aria-hidden', 'true');

        // Warn before leaving with unsaved changes.
        let dirty = false;

        function markDirty() {
            dirty = true;
        }

        quill.on('text-change', function (delta, oldDelta, source) {
            if (source === 'user') {
                markDirty();
            }
        });

        form.addEventListener('input', markDirty);
        form.addEventListener('change', markDirty);

        window.addEventListener('beforeunload', function (event) {
            if (dirty) {
                event.preventDefault();
                event.returnValue = '';
            }
        });

        form.addEventListener('submit', function () {
            dirty = false;

            // An empty Quill document still contains one newline.
            field.value = quill.getText().trim() === '' ? '' : quill.getSemanticHTML();
        });
    }

    /*
     * Replace Quill's built-in toolbar icons with Heroicons. Must run before
     * the editor is created. Anything missing keeps Quill's default icon.
     */
    function applyHeroicons(Quill) {
        let icons = {};

        try {
            icons = JSON.parse(host.getAttribute('data-icons') || '{}') || {};
        } catch (error) {
            return;
        }

        let registry;

        try {
            registry = Quill.import('ui/icons');
        } catch (error) {
            return;
        }

        if (!registry) {
            return;
        }

        function put(target, key, name) {
            if (target && icons[name]) {
                target[key] = icons[name];
            }
        }

        put(registry, 'bold', 'bold');
        put(registry, 'italic', 'italic');
        put(registry, 'underline', 'underline');
        put(registry, 'strike', 'strikethrough');
        put(registry, 'blockquote', 'chat-bubble-bottom-center-text');
        put(registry, 'code-block', 'code-bracket');
        put(registry, 'link', 'link');
        put(registry, 'clean', 'x-circle');

        if (typeof registry.header === 'object') {
            put(registry.header, '2', 'h2');
            put(registry.header, '3', 'h3');
        }

        if (typeof registry.list === 'object') {
            put(registry.list, 'ordered', 'numbered-list');
            put(registry.list, 'bullet', 'list-bullet');
        }

        if (typeof registry.align === 'object') {
            // The editor is right-to-left, so the default ('') is right-aligned.
            put(registry.align, '', 'align-right');
            put(registry.align, 'center', 'align-center');
            put(registry.align, 'right', 'align-right');
            put(registry.align, 'justify', 'align-justify');
        }
    }

    /*
     * Arabic tooltips and accessible names for the toolbar controls.
     */
    function labelToolbar(container) {
        const labels = [
            ['button.ql-header[value="2"]', 'عنوان رئيسي'],
            ['button.ql-header[value="3"]', 'عنوان فرعي'],
            ['button.ql-bold', 'غامق'],
            ['button.ql-italic', 'مائل'],
            ['button.ql-underline', 'تحته خط'],
            ['button.ql-strike', 'يتوسطه خط'],
            ['button.ql-list[value="ordered"]', 'قائمة مرقّمة'],
            ['button.ql-list[value="bullet"]', 'قائمة نقطية'],
            ['.ql-align .ql-picker-label', 'المحاذاة'],
            ['.ql-align .ql-picker-item[data-value=""]', 'محاذاة افتراضية'],
            ['.ql-align .ql-picker-item[data-value="center"]', 'توسيط'],
            ['.ql-align .ql-picker-item[data-value="justify"]', 'ضبط'],
            ['button.ql-blockquote', 'اقتباس'],
            ['button.ql-code-block', 'كتلة كود'],
            ['button.ql-link', 'رابط'],
            ['button.ql-clean', 'إزالة التنسيق']
        ];

        labels.forEach(function (entry) {
            container.querySelectorAll(entry[0]).forEach(function (element) {
                element.setAttribute('title', entry[1]);
                element.setAttribute('aria-label', entry[1]);
            });
        });
    }
});
