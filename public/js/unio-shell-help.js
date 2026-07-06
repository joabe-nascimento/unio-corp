/**
 * Hub de Ajuda — painel de fluxos e guias (somente sob demanda).
 */
(function () {
    'use strict';

    var wrap = document.querySelector('[data-shell-help]');
    if (!wrap) {
        return;
    }

    var btn = document.getElementById('shellHelpBtn');
    var panel = document.getElementById('shellHelpPanel');
    if (!btn || !panel) {
        return;
    }

    var MOBILE_MQ = '(max-width: 1199.98px)';
    var scrollResizeBound = false;

    function isMobileHelp() {
        return window.matchMedia(MOBILE_MQ).matches;
    }

    function readMobileBottomPad() {
        var root = getComputedStyle(document.documentElement);
        var offset = parseInt(root.getPropertyValue('--shell-mobile-nav-offset'), 10);
        if (!isNaN(offset) && offset > 0) {
            return offset + 12;
        }
        var nav = document.querySelector('.shell-mobile-nav__inner') || document.querySelector('.shell-mobile-nav');
        if (nav && getComputedStyle(nav).display !== 'none') {
            return Math.ceil(nav.getBoundingClientRect().height) + 12;
        }
        return 24;
    }

    function positionPanel() {
        if (panel.hidden || !isMobileHelp()) {
            return;
        }

        var rect = btn.getBoundingClientRect();
        var gutter = 12;
        var width = Math.min(340, window.innerWidth - gutter * 2);
        var left = Math.round(Math.min(
            Math.max(gutter, rect.right - width),
            window.innerWidth - width - gutter
        ));
        var top = Math.round(rect.bottom + 10);
        var maxHeight = Math.max(200, window.innerHeight - top - readMobileBottomPad());

        if (panel.parentNode !== document.body) {
            document.body.appendChild(panel);
        }

        panel.classList.add('is-mobile-floating');
        panel.style.position = 'fixed';
        panel.style.top = top + 'px';
        panel.style.left = left + 'px';
        panel.style.width = width + 'px';
        panel.style.right = 'auto';
        panel.style.maxHeight = maxHeight + 'px';
        panel.style.zIndex = '1080';
    }

    function resetPanelPosition() {
        panel.classList.remove('is-mobile-floating');
        panel.style.position = '';
        panel.style.top = '';
        panel.style.left = '';
        panel.style.width = '';
        panel.style.right = '';
        panel.style.maxHeight = '';
        panel.style.zIndex = '';
        if (panel.parentNode !== wrap) {
            wrap.appendChild(panel);
        }
    }

    function onPanelFollow() {
        if (!panel.hidden && isMobileHelp()) {
            positionPanel();
        }
    }

    function bindPanelFollow() {
        if (scrollResizeBound) {
            return;
        }
        scrollResizeBound = true;
        window.addEventListener('resize', onPanelFollow, { passive: true });
        window.addEventListener('scroll', onPanelFollow, true);
    }

    function unbindPanelFollow() {
        if (!scrollResizeBound) {
            return;
        }
        scrollResizeBound = false;
        window.removeEventListener('resize', onPanelFollow);
        window.removeEventListener('scroll', onPanelFollow, true);
    }

    function open() {
        panel.hidden = false;
        btn.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        document.body.classList.toggle('shell-help-open', isMobileHelp());
        if (isMobileHelp()) {
            positionPanel();
            bindPanelFollow();
        }
    }

    function close() {
        panel.hidden = true;
        btn.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('shell-help-open');
        unbindPanelFollow();
        if (isMobileHelp()) {
            resetPanelPosition();
        }
    }

    function toggle() {
        if (panel.hidden) {
            open();
        } else {
            close();
        }
    }

    function scrollToChecklist() {
        var checklist = document.querySelector('[data-onboarding-checklist]');
        if (!checklist) {
            return;
        }
        checklist.hidden = false;
        checklist.scrollIntoView({ behavior: 'smooth', block: 'center' });
        checklist.classList.add('shell-help-target-flash');
        setTimeout(function () {
            checklist.classList.remove('shell-help-target-flash');
        }, 1600);
    }

    function focusSearch() {
        var input = document.querySelector('.global-search-input');
        if (!input) {
            return;
        }
        input.focus();
        input.dispatchEvent(new Event('focus', { bubbles: true }));
    }

    function runAction(action, navigateUrl) {
        if (action === 'scroll-checklist') {
            scrollToChecklist();
            return;
        }
        if (action === 'focus-search') {
            focusSearch();
            return;
        }
        if (action === 'navigate' && navigateUrl) {
            window.location.assign(navigateUrl);
        }
    }

    function runFlow(flowId, action, navigateUrl) {
        close();

        if (action) {
            runAction(action, navigateUrl);
            return;
        }

        if (!window.unioShellTour || typeof window.unioShellTour.openFlow !== 'function') {
            return;
        }

        window.setTimeout(function () {
            window.unioShellTour.openFlow(flowId);
        }, 120);
    }

    btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        toggle();
    });

    panel.querySelectorAll('[data-shell-help-close]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            close();
            btn.focus();
        });
    });

    panel.querySelectorAll('[data-shell-help-flow]').forEach(function (flowBtn) {
        flowBtn.addEventListener('click', function (e) {
            e.preventDefault();
            var flowId = flowBtn.getAttribute('data-shell-help-flow');
            var action = flowBtn.getAttribute('data-shell-help-action') || '';
            var navigateUrl = flowBtn.getAttribute('data-shell-help-navigate') || '';
            runFlow(flowId, action, navigateUrl);
        });
    });

    document.addEventListener('click', function (e) {
        if (panel.hidden) {
            return;
        }
        if (wrap.contains(e.target)) {
            return;
        }
        close();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !panel.hidden) {
            close();
            btn.focus();
        }
    });

    document.querySelectorAll('[data-shell-help-open]').forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            open();
        });
    });

    document.querySelectorAll('[data-shell-tour-start]').forEach(function (trigger) {
        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            close();
            if (window.unioShellTour && typeof window.unioShellTour.openFlow === 'function') {
                window.unioShellTour.openFlow('full');
            }
        });
    });

    window.unioShellHelp = {
        open: open,
        close: close,
        toggle: toggle,
    };
})();
