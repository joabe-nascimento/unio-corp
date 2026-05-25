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
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
