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

    function samePageHash(href) {
        if (!href || href.indexOf('#') === -1) {
            return href && href.charAt(0) === '#' ? href : null;
        }

        try {
            var target = new URL(href, window.location.origin);
            if (target.pathname === window.location.pathname && target.hash) {
                return target.hash;
            }
        } catch (err) {
            if (href.charAt(0) === '#') {
                return href;
            }
        }

        return null;
    }

    function handleClick(event) {
        var hash = samePageHash(event.currentTarget.getAttribute('href'));
        if (!hash) {
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

        root.querySelectorAll('.mkt-header a[href*="#"], .mkt-footer a[href*="#"]').forEach(function (link) {
            link.addEventListener('click', handleClick);
        });

        window.MktNavHash = {
            scrollToHash: scrollToHash,
            headerOffset: headerOffset,
        };

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
