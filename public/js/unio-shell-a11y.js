/**
 * Acessibilidade do shell — aria-current nos itens ativos da sidebar.
 */
(function () {
    'use strict';

    function syncNavAriaCurrent() {
        document.querySelectorAll('.main-sidebar .nav-link').forEach(function (link) {
            if (link.classList.contains('active')) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }

    function boot() {
        syncNavAriaCurrent();

        var sidebar = document.querySelector('.main-sidebar .nav-sidebar');
        if (sidebar && typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(syncNavAriaCurrent);
            observer.observe(sidebar, {
                subtree: true,
                attributes: true,
                attributeFilter: ['class'],
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
