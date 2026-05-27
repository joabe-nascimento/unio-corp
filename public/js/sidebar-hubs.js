/**
 * Sidebar — modo lista de hubs vs painel do hub selecionado.
 */
(function () {
    'use strict';

    function setHubMode(inHub) {
        var sidebar = document.querySelector('.nav-sidebar');
        if (!sidebar) {
            return;
        }

        sidebar.classList.toggle('sidebar--hub-picker', !inHub);
        sidebar.classList.toggle('sidebar--hub-open', inHub);

        sidebar.querySelectorAll('.sidebar-hub-pick-item, [data-sidebar-hub-picker] .sidebar-hub-pick-item').forEach(function (el) {
            el.classList.toggle('is-hidden', inHub);
        });

        sidebar.querySelectorAll('[data-sidebar-hub-panel]').forEach(function (panel) {
            panel.classList.add('is-hidden');
        });

        var back = sidebar.querySelector('[data-sidebar-hub-back]');
        if (back) {
            back.classList.toggle('is-hidden', !inHub);
        }
    }

    function showPanel(hubId) {
        var sidebar = document.querySelector('.nav-sidebar');
        if (!sidebar || !hubId) {
            return;
        }

        sidebar.classList.add('sidebar--hub-open');
        sidebar.classList.remove('sidebar--hub-picker');

        sidebar.querySelectorAll('.sidebar-hub-pick-item').forEach(function (el) {
            el.classList.add('is-hidden');
        });

        sidebar.querySelectorAll('[data-sidebar-hub-panel]').forEach(function (panel) {
            panel.classList.toggle('is-hidden', panel.getAttribute('data-sidebar-hub-panel') !== hubId);
        });

        var back = sidebar.querySelector('[data-sidebar-hub-back]');
        if (back) {
            back.classList.remove('is-hidden');
        }
    }

    function storageKeyForItem(item) {
        return item.getAttribute('data-storage-key')
            || (item.hasAttribute('data-rh-module-tree') ? 'rh-sidebar-collapsed' : null);
    }

    function initModuleTrees(sidebar) {
        sidebar.querySelectorAll('[data-module-tree], [data-rh-module-tree]').forEach(function (item) {
            var storageKey = storageKeyForItem(item);
            var autoOpen = item.getAttribute('data-auto-open') === '1';

            if (!autoOpen) {
                return;
            }

            if (storageKey) {
                try {
                    if (sessionStorage.getItem(storageKey) !== '1') {
                        item.classList.add('menu-open');
                    }
                } catch (e) {
                    item.classList.add('menu-open');
                }
            } else {
                item.classList.add('menu-open');
            }
        });

        if (typeof jQuery === 'undefined') {
            return;
        }

        jQuery(sidebar).on('expanded.lte.treeview collapsed.lte.treeview', function () {
            sidebar.querySelectorAll('[data-module-tree], [data-rh-module-tree]').forEach(function (item) {
                var key = storageKeyForItem(item);
                if (!key) {
                    return;
                }
                try {
                    sessionStorage.setItem(key, item.classList.contains('menu-open') ? '0' : '1');
                } catch (e) { /* ignore */ }
            });
        });
    }

    function boot() {
        var sidebar = document.querySelector('.nav-sidebar[data-sidebar-hubs]');
        if (!sidebar) {
            return;
        }

        var activeHub = sidebar.getAttribute('data-active-hub') || '';

        if (activeHub) {
            showPanel(activeHub);
        } else {
            setHubMode(false);
        }

        initModuleTrees(sidebar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
