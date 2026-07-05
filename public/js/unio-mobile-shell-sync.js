/**
 * Mobile shell sync — altura real da nav inferior, respiro do scroll e portal do FAB.
 */
(function () {
    'use strict';

    var MOBILE_MQ = '(max-width: 1199.98px)';

    function isMobileShell() {
        if (!window.matchMedia(MOBILE_MQ).matches) {
            return false;
        }
        var nav = document.querySelector('.shell-mobile-nav');
        if (!nav) {
            return false;
        }
        return window.getComputedStyle(nav).display !== 'none';
    }

    function syncNavHeight() {
        if (!isMobileShell()) {
            return null;
        }

        var measured = measureNavPx();
        var pad = syncContentPad();

        return { measured: measured, padPx: pad ? pad.padPx : null };
    }

    function measureNavPx() {
        var inner = document.querySelector('.shell-mobile-nav__inner') || document.querySelector('.shell-mobile-nav');
        if (!inner) {
            return 68;
        }
        return Math.ceil(inner.getBoundingClientRect().height);
    }

    function syncContentPad() {
        var scroll = document.querySelector('.app-page-scroll');
        var existingSpacer = scroll ? scroll.querySelector('.shell-mobile-scroll-spacer') : null;

        if (!isMobileShell()) {
            document.querySelectorAll('[data-mobile-content-pad]').forEach(function (el) {
                el.style.removeProperty('padding-bottom');
                el.removeAttribute('data-mobile-content-pad');
            });
            document.querySelectorAll('[data-mobile-body-pad]').forEach(function (el) {
                el.style.removeProperty('padding-bottom');
                el.removeAttribute('data-mobile-body-pad');
            });
            var panel = document.querySelector('.app-inset-panel');
            if (panel) {
                panel.style.removeProperty('padding-bottom');
            }
            if (existingSpacer) {
                existingSpacer.remove();
            }
            return null;
        }

        var navPx = measureNavPx();
        var fabPx = document.querySelector('body > .toolbar-mobile-fab:not([hidden])') ? 72 : 0;
        var padPx = navPx + fabPx + 16;
        var pad = padPx + 'px';
        var root = document.documentElement;

        root.style.setProperty('--shell-mobile-nav-height', navPx + 'px');
        root.style.setProperty(
            '--shell-mobile-nav-offset',
            'calc(' + navPx + 'px + env(safe-area-inset-bottom, 0px))'
        );
        root.style.setProperty('--shell-mobile-content-pad', pad);

        if (scroll) {
            var spacer = existingSpacer;
            if (!spacer) {
                spacer = document.createElement('div');
                spacer.className = 'shell-mobile-scroll-spacer';
                spacer.setAttribute('aria-hidden', 'true');
                scroll.appendChild(spacer);
            }
            spacer.style.height = pad;
        }

        var content = scroll ? scroll.querySelector(':scope > .content') : null;
        if (content) {
            content.setAttribute('data-mobile-content-pad', '1');
            content.style.setProperty('padding-bottom', pad, 'important');
        }

        var pageBody = document.querySelector('.page-body-inner, .rh-hub, .welcome-page-inner');
        if (pageBody) {
            pageBody.setAttribute('data-mobile-body-pad', '1');
            pageBody.style.setProperty('padding-bottom', pad, 'important');
        }

        var panel = document.querySelector('.app-inset-panel');
        if (panel) {
            panel.style.setProperty('padding-bottom', navPx + 'px');
        }

        return { pad: pad, padPx: padPx, navPx: navPx, fabPx: fabPx };
    }

    function syncFabOffset() {
        if (!isMobileShell()) {
            document.documentElement.style.setProperty('--shell-mobile-fab-extra', '0px');
            return { visibleFab: false };
        }

        var visibleFab = document.querySelector('body > .toolbar-mobile-fab:not([hidden])');
        document.documentElement.style.setProperty('--shell-mobile-fab-extra', visibleFab ? '72px' : '0px');
        syncContentPad();
        return { visibleFab: !!visibleFab };
    }

    function sync() {
        var nav = syncNavHeight();
        var fab = syncFabOffset();
        return { nav: nav, fab: fab };
    }

    function scheduleSync() {
        window.requestAnimationFrame(function () {
            sync();
            window.requestAnimationFrame(sync);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scheduleSync);
    } else {
        scheduleSync();
    }

    window.addEventListener('resize', scheduleSync);
    window.addEventListener('orientationchange', scheduleSync);

    if (typeof ResizeObserver !== 'undefined') {
        var navInner = document.querySelector('.shell-mobile-nav__inner');
        if (navInner) {
            new ResizeObserver(scheduleSync).observe(navInner);
        }
    }

    window.UnioMobileShellSync = { sync: sync, isMobileShell: isMobileShell };
})();
