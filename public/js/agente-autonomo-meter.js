(function () {
    var root = document.getElementById('agenteAutonomoMeter');
    if (!root) return;

    var valueEl = document.getElementById('agenteAutonomoMeterValue');
    var dotEl = document.getElementById('agenteAutonomoMeterDot');
    var url = root.getAttribute('data-status-url');
    if (!url || !valueEl) return;

    function formatAlertas(n) {
        var count = Number(n) || 0;
        if (count === 1) return '1 alerta';
        return count + ' alertas';
    }

    function refresh() {
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) {
                    valueEl.textContent = '—';
                    if (dotEl) dotEl.classList.remove('is-online');
                    return;
                }
                valueEl.textContent = formatAlertas(data.alertas_hoje);
                if (dotEl) dotEl.classList.toggle('is-online', !!data.ativo);
            })
            .catch(function () {
                valueEl.textContent = '—';
                if (dotEl) dotEl.classList.remove('is-online');
            });
    }

    refresh();
    setInterval(refresh, 60000);
})();
