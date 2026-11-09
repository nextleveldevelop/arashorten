'use strict';

document.addEventListener('DOMContentLoaded', function () {
    // Mobile sidebar
    const toggle = document.getElementById('adminMenuToggle');
    const sidebar = document.getElementById('adminSidebar');

    if (toggle && sidebar) {
        toggle.addEventListener('click', function () {
            const isOpen = sidebar.classList.toggle('is-open');

            toggle.setAttribute('aria-expanded', String(isOpen));
        });
    }

    // Confirm dialogs: <button data-confirm="..."> or <form data-confirm="...">
    document.addEventListener('submit', function (event) {
        const form = event.target;
        const submitter = event.submitter;
        const message =
            (submitter && submitter.getAttribute('data-confirm')) ||
            form.getAttribute('data-confirm');

        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    // Character counters: <span data-counter-for="field_id" data-recommended="160">
    document.querySelectorAll('[data-counter-for]').forEach(function (counter) {
        const field = document.getElementById(counter.getAttribute('data-counter-for'));
        const recommended = parseInt(counter.getAttribute('data-recommended') || '0', 10);

        if (!field) {
            return;
        }

        function update() {
            const length = field.value.length;

            counter.textContent = length + (recommended ? ' / ' + recommended : '') + ' حرف';
            counter.classList.toggle('is-over', recommended > 0 && length > recommended);
        }

        field.addEventListener('input', update);
        update();
    });
});
