/**
 * Sidebar — modo lista de hubs vs painel do hub selecionado.
 */
(function () {
    'use strict';

    function setPickerVisible(sidebar, visible) {
        sidebar.querySelectorAll('.sidebar-hub-group').forEach(function (el) {
            el.classList.toggle('is-hidden', !visible);
        });
        sidebar.querySelectorAll('.sidebar-hub-pick-item').forEach(function (el) {
            el.classList.toggle('is-hidden', !visible);
        });
    }

    function setHubMode(inHub) {
        var sidebar = document.querySelector('.nav-sidebar');
        if (!sidebar) {
            return;
        }

        sidebar.classList.toggle('sidebar--hub-picker', !inHub);
        sidebar.classList.toggle('sidebar--hub-open', inHub);

        setPickerVisible(sidebar, !inHub);

        sidebar.querySelectorAll('[data-sidebar-hub-panel]').forEach(function (panel) {
            panel.classList.add('is-hidden');
        });

        sidebar.querySelectorAll('[data-sidebar-hub-back]').forEach(function (back) {
            back.classList.toggle('is-hidden', !inHub);
        });
    }

    function showPanel(hubId) {
        var sidebar = document.querySelector('.nav-sidebar');
        if (!sidebar || !hubId) {
            return;
        }

        sidebar.classList.add('sidebar--hub-open');
        sidebar.classList.remove('sidebar--hub-picker');

        setPickerVisible(sidebar, false);

        sidebar.querySelectorAll('[data-sidebar-hub-panel]').forEach(function (panel) {
            panel.classList.toggle('is-hidden', panel.getAttribute('data-sidebar-hub-panel') !== hubId);
        });

        sidebar.querySelectorAll('[data-sidebar-hub-back]').forEach(function (back) {
            back.classList.remove('is-hidden');
        });
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

    function initHubBack(sidebar) {
        sidebar.querySelectorAll('[data-sidebar-hub-back]').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                event.preventDefault();
                setHubMode(false);
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

        initHubBack(sidebar);
        initModuleTrees(sidebar);

        window.unioSidebarHubs = {
            showPicker: function () {
                setHubMode(false);
            },
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
