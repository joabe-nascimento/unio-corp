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

    function open() {
        panel.hidden = false;
        btn.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
    }

    function close() {
        panel.hidden = true;
        btn.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
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
