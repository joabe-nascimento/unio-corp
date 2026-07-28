(function () {
    var root = document.getElementById('aiTokenMeter');
    if (!root) return;

    var valueEl = document.getElementById('aiTokenMeterValue');
    var dotEl = document.getElementById('aiTokenMeterDot');
    var url = root.getAttribute('data-usage-url');
    if (!url || !valueEl) return;

    function formatTokens(n) {
        var num = Number(n) || 0;
        if (num >= 1000000) return (num / 1000000).toFixed(1).replace('.0', '') + 'M';
        if (num >= 1000) return (num / 1000).toFixed(1).replace('.0', '') + 'k';
        return String(num);
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
                var today = data.today || {};
                valueEl.textContent = formatTokens(today.total_tokens) + ' tok';
                if (dotEl) {
                    dotEl.classList.toggle('is-online', !!data.online);
                }
            })
            .catch(function () {
                valueEl.textContent = '—';
                if (dotEl) dotEl.classList.remove('is-online');
            });
    }

    refresh();
    setInterval(refresh, 30000);
})();
