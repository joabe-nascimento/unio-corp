/**
 * Painel de conta — dropdown no topo da sidebar (mesma largura).
 */
(function () {
    'use strict';

    var btn = document.getElementById('sidebarIdentityBtn');
    var panel = document.getElementById('sidebarIdentityPanel');
    if (!btn || !panel) {
        return;
    }

    function open() {
        panel.hidden = false;
        btn.classList.add('is-open');
        btn.setAttribute('aria-expanded', 'true');
        document.body.classList.add('sidebar-identity-open');
    }

    function close() {
        panel.hidden = true;
        btn.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('sidebar-identity-open');
    }

    function toggle() {
        if (panel.hidden) {
            open();
        } else {
            close();
        }
    }

    btn.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        toggle();
    });

    panel.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () {
            close();
        });
    });

    document.addEventListener('click', function (event) {
        if (panel.hidden) {
            return;
        }
        if (btn.contains(event.target) || panel.contains(event.target)) {
            return;
        }
        close();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !panel.hidden) {
            close();
            btn.focus();
        }
    });
})();
