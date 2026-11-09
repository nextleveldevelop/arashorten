'use strict';

document.addEventListener('DOMContentLoaded', function () {
    // Mobile navigation
    const menuButton = document.querySelector('.menu-toggle');
    const navigation = document.querySelector('.main-navigation');

    if (menuButton && navigation) {
        menuButton.addEventListener('click', function () {
            const isOpen = navigation.classList.toggle('is-open');

            menuButton.setAttribute('aria-expanded', String(isOpen));
        });
    }

    // Copy text from an input element
    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const targetId = button.getAttribute('data-copy-target');
            const input = document.getElementById(targetId);

            if (!input) {
                return;
            }

            await copyText(input.value, button);
        });
    });

    // Copy a value stored in a data attribute
    document.querySelectorAll('[data-copy-text]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const value = button.getAttribute('data-copy-text');

            if (value === null) {
                return;
            }

            await copyText(value, button);
        });
    });

    // Confirm destructive actions
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const message = form.getAttribute('data-confirm');

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
});

async function copyText(value, button) {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(value);
        } else {
            const temporaryInput = document.createElement('textarea');

            temporaryInput.value = value;
            temporaryInput.setAttribute('readonly', '');
            temporaryInput.style.position = 'absolute';
            temporaryInput.style.left = '-9999px';

            document.body.appendChild(temporaryInput);

            temporaryInput.select();

            const copied = document.execCommand('copy');

            temporaryInput.remove();

            if (!copied) {
                throw new Error('Copy failed');
            }
        }

        // Keep the icon: only the text label changes, and CSS swaps the icon.
        const label = button.querySelector('.btn-label');
        const originalText = label ? label.textContent : button.textContent;

        if (label) {
            label.textContent = 'تم النسخ';
        } else {
            button.textContent = 'تم النسخ';
        }

        button.classList.add('is-copied');

        window.setTimeout(function () {
            if (label) {
                label.textContent = originalText;
            } else {
                button.textContent = originalText;
            }

            button.classList.remove('is-copied');
        }, 1500);
    } catch (error) {
        window.alert('تعذر نسخ الرابط. يرجى نسخه يدوياً.');
    }
}