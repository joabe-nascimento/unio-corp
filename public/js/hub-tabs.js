/**
 * Abas do layout hub (Visão geral, Analytics, Permissões).
 * Usa delegação de eventos para funcionar após navegação parcial / Turbo.
 */
(function (global) {
    'use strict';

    var BOUND = false;

    function normPath(path) {
        return (path || '/').replace(/\/$/, '') || '/';
    }

    function getTabsRoot() {
        return document.querySelector('.page-lead-zone--hub-tabs [data-hub-tabs]');
    }

    function getPanelsRoot() {
        return document.querySelector('.page-body-inner--hub-tabs');
    }

    function tabExists(name) {
        var tabs = getTabsRoot();
        return !!(tabs && tabs.querySelector('[data-hub-tab="' + name + '"]'));
    }

    function panelExists(name) {
        var root = getPanelsRoot();
        return !!(root && root.querySelector('[data-hub-panel="' + name + '"]'));
    }

    function getPageScrollEl() {
        return document.querySelector('.app-page-scroll');
    }

    function resetHubContentScroll() {
        var behavior = global.matchMedia('(max-width: 1199.98px)').matches ? 'auto' : 'smooth';
        var scrollEl = getPageScrollEl();
        if (scrollEl) {
            scrollEl.scrollTo({ top: 0, behavior: behavior });
        }
        /* scrollIntoView no chrome fixo podia deslocar document/window no mobile */
        if (global.scrollY > 0) {
            global.scrollTo({ top: 0, behavior: behavior });
        }
        var panelsRoot = getPanelsRoot();
        if (panelsRoot && panelsRoot.scrollTop > 0) {
            panelsRoot.scrollTo({ top: 0, behavior: behavior });
        }
        return !!scrollEl;
    }

    function focusTab(name) {
        /* Título + abas ficam em .app-page-chrome (fixo). scrollIntoView no lead empurra
         * o título sob a navbar no mobile ao trocar Visão geral ↔ Permissões. */
        if (resetHubContentScroll()) {
            return;
        }

        var root = getPanelsRoot();
        var panel = root ? root.querySelector('[data-hub-panel="' + name + '"]') : null;
        if (panel) {
            global.requestAnimationFrame(function () {
                panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }
    }

    function activateTab(name, options) {
        options = options || {};
        if (!name || (!tabExists(name) && !panelExists(name))) {
            return false;
        }

        var tabs = getTabsRoot();
        var root = getPanelsRoot();
        if (!root) {
            return false;
        }

        if (tabs) {
            tabs.querySelectorAll('[data-hub-tab]').forEach(function (btn) {
                var active = btn.getAttribute('data-hub-tab') === name;
                btn.classList.toggle('hub-overview-tab--active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        var leadZone = document.querySelector('.page-lead-zone--hub-tabs');
        if (leadZone) {
            leadZone.setAttribute('data-active-hub-tab', name);
        }
        if (root) {
            root.setAttribute('data-active-hub-tab', name);
        }

        root.querySelectorAll('[data-hub-panel]').forEach(function (panel) {
            var active = panel.getAttribute('data-hub-panel') === name;
            panel.hidden = !active;
            panel.classList.toggle('hub-tab-panel--active', active);
        });

        if (name === 'analytics') {
            global.requestAnimationFrame(function () {
                global.dispatchEvent(new CustomEvent('unio-charts-resize'));
                setTimeout(function () {
                    global.dispatchEvent(new CustomEvent('unio-charts-resize'));
                }, 150);
            });
        }

        if (!options.silent) {
            var nextUrl = global.location.pathname + global.location.search;
            if (name && name !== 'overview') {
                nextUrl += '#' + name;
            }
            var current = global.location.pathname + global.location.search + global.location.hash;
            if (current !== nextUrl && (name !== 'overview' || global.location.hash)) {
                global.history.replaceState(null, '', nextUrl);
            }
            if (!options.noScroll) {
                focusTab(name);
            }
        }

        return true;
    }

    function syncFromHash() {
        if (!getPanelsRoot()) {
            return;
        }

        var hashTab = (global.location.hash || '').replace('#', '').trim();
        if (hashTab && (tabExists(hashTab) || panelExists(hashTab))) {
            activateTab(hashTab, { silent: true });
            return;
        }
        if (!hashTab) {
            activateTab('overview', { silent: true });
        }
    }

    function onDocumentClick(event) {
        var tabBtn = event.target.closest('[data-hub-tab]');
        if (tabBtn && tabBtn.closest('.page-lead-zone--hub-tabs [data-hub-tabs]')) {
            var tabName = tabBtn.getAttribute('data-hub-tab');
            if (tabName && activateTab(tabName)) {
                event.preventDefault();
            }
            return;
        }

        var tabLink = event.target.closest('[data-hub-tab-link]');
        if (tabLink) {
            var linkedTab = tabLink.getAttribute('data-hub-tab-link');
            if (linkedTab && activateTab(linkedTab)) {
                event.preventDefault();
            }
            return;
        }

        var link = event.target.closest('a[href*="#"]');
        if (!link) {
            return;
        }

        try {
            var url = new URL(link.href, global.location.origin);
            if (normPath(url.pathname) !== normPath(global.location.pathname)) {
                return;
            }
            var hashTab = (url.hash || '').replace('#', '').trim();
            if (!hashTab || (!tabExists(hashTab) && !panelExists(hashTab))) {
                return;
            }
            if (activateTab(hashTab)) {
                event.preventDefault();
            }
        } catch (e) {
            /* ignore */
        }
    }

    function bind() {
        if (BOUND) {
            return;
        }
        BOUND = true;
        document.addEventListener('click', onDocumentClick, true);
        global.addEventListener('hashchange', syncFromHash);
        document.addEventListener('turbo:load', syncFromHash);
        document.addEventListener('turbo:render', syncFromHash);
    }

    function init() {
        bind();
        syncFromHash();
    }

    global.UnioHubTabs = {
        activate: activateTab,
        sync: syncFromHash,
        init: init,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window);
