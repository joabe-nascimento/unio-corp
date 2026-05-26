/**
 * Dashboard — relógio ao vivo no hero.
 */
(function () {
    'use strict';

    var timeEl = document.querySelector('[data-dashboard-time]');
    if (!timeEl) return;

    function tick() {
        try {
            var fmt = new Intl.DateTimeFormat('pt-BR', {
                timeZone: 'America/Sao_Paulo',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
            timeEl.textContent = fmt.format(new Date());
        } catch (e) { /* ignore */ }
    }

    tick();
    setInterval(tick, 30000);
})();
