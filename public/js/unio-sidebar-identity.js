/**
 * Painel de conta — abre via #sidebarIdentityBtn ou [data-identity-open].
 */
(function () {
    'use strict';

    var primaryBtn = document.getElementById('sidebarIdentityBtn');
    var panel = document.getElementById('sidebarIdentityPanel');
    if (!panel) {
        return;
    }

    var allTriggers = document.querySelectorAll('#sidebarIdentityBtn, [data-identity-open]');

    function open() {
        panel.hidden = false;
        allTriggers.forEach(function (t) { t.setAttribute('aria-expanded', 'true'); });
        if (primaryBtn) { primaryBtn.classList.add('is-open'); }
        document.body.classList.add('sidebar-identity-open');
    }

    function close() {
        panel.hidden = true;
        allTriggers.forEach(function (t) { t.setAttribute('aria-expanded', 'false'); });
        if (primaryBtn) { primaryBtn.classList.remove('is-open'); }
        document.body.classList.remove('sidebar-identity-open');
    }

    function toggle() {
        if (panel.hidden) { open(); } else { close(); }
    }

    allTriggers.forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            toggle();
        });
    });

    panel.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', function () { close(); });
    });

    document.addEventListener('click', function (event) {
        if (panel.hidden) { return; }
        var inTrigger = Array.from(allTriggers).some(function (t) { return t.contains(event.target); });
        if (inTrigger || panel.contains(event.target)) { return; }
        close();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !panel.hidden) {
            close();
            if (primaryBtn) { primaryBtn.focus(); }
        }
    });
})();
