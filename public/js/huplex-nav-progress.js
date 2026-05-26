/**
 * Barra de progresso no topo durante navegação entre páginas (substitui a do Turbo Drive).
 * Não usa Turbo — compatível com AdminLTE e navegação clássica do Symfony.
 */
(function () {
    'use strict';

    var bar = null;
    var trickleTimer = null;
    var hideTimer = null;
    var progress = 0;

    function ensureBar() {
        if (bar) {
            return bar;
        }
        bar = document.createElement('div');
        bar.className = 'huplex-nav-progress';
        bar.setAttribute('aria-hidden', 'true');
        return bar;
    }

    function mount() {
        var el = ensureBar();
        if (!el.parentNode) {
            document.documentElement.appendChild(el);
        }
        el.classList.remove('huplex-nav-progress--done');
        el.classList.add('huplex-nav-progress--active');
        progress = 0.08;
        el.style.width = (progress * 100) + '%';
    }

    function setProgress(value) {
        if (!bar || !bar.classList.contains('huplex-nav-progress--active')) {
            return;
        }
        progress = Math.min(0.92, Math.max(progress, value));
        bar.style.width = (progress * 100) + '%';
    }

    function startTrickle() {
        stopTrickle();
        trickleTimer = window.setInterval(function () {
            setProgress(progress + 0.04 + Math.random() * 0.06);
        }, 280);
    }

    function stopTrickle() {
        if (trickleTimer) {
            window.clearInterval(trickleTimer);
            trickleTimer = null;
        }
    }

    function start() {
        if (hideTimer) {
            window.clearTimeout(hideTimer);
            hideTimer = null;
        }
        mount();
        startTrickle();
    }

    function finish() {
        stopTrickle();
        if (!bar) {
            return;
        }
        bar.style.width = '100%';
        bar.classList.add('huplex-nav-progress--done');
        bar.classList.remove('huplex-nav-progress--active');
        hideTimer = window.setTimeout(function () {
            if (bar && bar.parentNode) {
                bar.parentNode.removeChild(bar);
            }
            bar = null;
            progress = 0;
        }, 320);
    }

    function shouldTrackLink(anchor) {
        if (!anchor || anchor.tagName !== 'A') {
            return false;
        }
        if (anchor.hasAttribute('download') || anchor.target === '_blank') {
            return false;
        }
        if (anchor.getAttribute('data-turbo') === 'false') {
            return false;
        }
        if (anchor.hasAttribute('data-toggle') || anchor.hasAttribute('data-widget')) {
            return false;
        }
        var href = anchor.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) {
            return false;
        }
        try {
            var url = new URL(href, window.location.href);
            return url.origin === window.location.origin && url.pathname !== window.location.pathname;
        } catch (e) {
            return false;
        }
    }

    document.addEventListener('click', function (event) {
        var anchor = event.target.closest('a[href]');
        if (!shouldTrackLink(anchor)) {
            return;
        }
        start();
    }, true);

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            finish();
        }
    });

    window.addEventListener('load', finish);
    window.addEventListener('beforeunload', start);
})();
