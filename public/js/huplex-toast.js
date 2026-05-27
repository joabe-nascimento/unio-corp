/**
 * Huplex Toast — notificações + API programática (estilo Notyf)
 */
(function () {
    'use strict';

    var DISMISS_MS = 4800;
    var EXIT_MS = 280;
    var ICONS = {
        success: 'fa-circle-check',
        error: 'fa-circle-xmark',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info',
    };

    function getStack() {
        var stack = document.getElementById('huplexToastStack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'huplex-toast-stack';
            stack.id = 'huplexToastStack';
            stack.setAttribute('aria-live', 'polite');
            document.body.appendChild(stack);
        }
        return stack;
    }

    function dismiss(toast) {
        if (!toast || toast.classList.contains('is-leaving')) return;
        toast.classList.add('is-leaving');
        window.setTimeout(function () { toast.remove(); }, EXIT_MS);
    }

    function initToast(toast) {
        if (toast.dataset.toastInit === '1') return;
        toast.dataset.toastInit = '1';
        var closeBtn = toast.querySelector('[data-huplex-toast-close]');
        if (closeBtn) closeBtn.addEventListener('click', function () { dismiss(toast); });
        window.setTimeout(function () { dismiss(toast); }, DISMISS_MS);
    }

    function show(message, type, duration) {
        type = ICONS[type] ? type : 'info';
        var stack = getStack();
        var toast = document.createElement('div');
        toast.className = 'huplex-toast huplex-toast--' + type;
        toast.setAttribute('data-huplex-toast', '');
        toast.setAttribute('role', 'status');
        toast.innerHTML =
            '<span class="huplex-toast-icon" aria-hidden="true"><i class="fas ' + ICONS[type] + '"></i></span>' +
            '<span class="huplex-toast-message"></span>' +
            '<button type="button" class="huplex-toast-close" data-huplex-toast-close aria-label="Fechar">' +
            '<i class="fas fa-times"></i></button>';
        toast.querySelector('.huplex-toast-message').textContent = message;
        stack.appendChild(toast);
        initToast(toast);
        if (duration && duration > 0) {
            window.setTimeout(function () { dismiss(toast); }, duration);
        }
        return toast;
    }

    function scan() {
        document.querySelectorAll('[data-huplex-toast]').forEach(initToast);
    }

    window.HuplexToast = { show: show, dismiss: dismiss };

    document.addEventListener('DOMContentLoaded', scan);
    document.addEventListener('turbo:load', scan);
    if (document.readyState !== 'loading') scan();
})();
