(function () {
    'use strict';

    function headerOffset() {
        var header = document.querySelector('.mkt-header--v2, .mkt-header');
        if (!header) {
            return 88;
        }
        return Math.ceil(header.getBoundingClientRect().height) + 12;
    }

    function scrollToHash(hash, behavior) {
        if (!hash || hash === '#') {
            return false;
        }

        var target = document.querySelector(hash);
        if (!target) {
            return false;
        }

        var top = target.getBoundingClientRect().top + window.pageYOffset - headerOffset();
        window.scrollTo({
            top: Math.max(0, top),
            behavior: behavior || 'smooth',
        });

        return true;
    }

    function handleClick(event) {
        var href = event.currentTarget.getAttribute('href');
        if (!href || href.charAt(0) !== '#') {
            return;
        }

        var hash;
        try {
            hash = new URL(href, window.location.origin).hash;
        } catch (err) {
            return;
        }

        if (!hash || hash === '#') {
            return;
        }

        if (scrollToHash(hash, 'smooth')) {
            event.preventDefault();
            window.history.replaceState(null, '', window.location.pathname + window.location.search + hash);
        }
    }

    function boot() {
        var root = document.querySelector('.marketing-home');
        if (!root) {
            return;
        }

        root.querySelectorAll('.mkt-header a[href^="#"], .mkt-footer a[href^="#"]').forEach(function (link) {
            link.addEventListener('click', handleClick);
        });

        if (window.location.hash) {
            requestAnimationFrame(function () {
                scrollToHash(window.location.hash, 'auto');
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
