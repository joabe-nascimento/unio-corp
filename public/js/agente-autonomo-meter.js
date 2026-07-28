(function () {
    var root = document.getElementById('agenteAutonomoMeter');
    if (!root) return;

    var valueEl = document.getElementById('agenteAutonomoMeterValue');
    var dotEl = document.getElementById('agenteAutonomoMeterDot');
    var url = root.getAttribute('data-status-url');
    if (!url || !valueEl) return;

    function formatMinutos(min) {
        if (min === null || min === undefined) return 'nunca rodou';
        if (min < 1) return 'agora mesmo';
        if (min < 60) return 'há ' + min + ' min';
        var horas = Math.floor(min / 60);
        if (horas < 24) return 'há ' + horas + 'h';
        return 'há ' + Math.floor(horas / 24) + 'd';
    }

    function setTooltip(data) {
        root.title = [
            'Agente Autônomo Jurídico',
            data.ativo ? 'Ativo — monitorando prazos, tarefas e carteira' : 'Aguardando próxima varredura',
            'Última varredura: ' + formatMinutos(data.minutos_desde_execucao),
            'Alertas enviados hoje: ' + (data.alertas_hoje || 0),
            'Escritórios monitorados: ' + (data.empresas_monitoradas || 0),
        ].join('\n');
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
                valueEl.textContent = (data.alertas_hoje || 0) + ' alerta(s)';
                if (dotEl) dotEl.classList.toggle('is-online', !!data.ativo);
                setTooltip(data);
            })
            .catch(function () {
                valueEl.textContent = '—';
                if (dotEl) dotEl.classList.remove('is-online');
            });
    }

    refresh();
    setInterval(refresh, 60000);
})();
