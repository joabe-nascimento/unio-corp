/**
 * Huplex Toast — notificações no canto superior direito, auto-dismiss
 */
(function () {
    'use strict';

    var DISMISS_MS = 4800;
    var EXIT_MS = 280;

    function dismiss(toast) {
        if (!toast || toast.classList.contains('is-leaving')) {
            return;
        }
        toast.classList.add('is-leaving');
        window.setTimeout(function () {
            toast.remove();
        }, EXIT_MS);
    }

    function initToast(toast) {
        if (toast.dataset.toastInit === '1') {
            return;
        }
        toast.dataset.toastInit = '1';

        var closeBtn = toast.querySelector('[data-huplex-toast-close]');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                dismiss(toast);
            });
        }

        window.setTimeout(function () {
            dismiss(toast);
        }, DISMISS_MS);
    }

    function scan() {
        document.querySelectorAll('[data-huplex-toast]').forEach(initToast);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }
})();
