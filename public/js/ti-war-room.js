(function () {
    'use strict';

    var wrRoot = document.querySelector('.ti-war-room[data-wr-poll-url]');
    var pollUrl = wrRoot ? wrRoot.getAttribute('data-wr-poll-url') : document.body.getAttribute('data-wr-poll-url');
    var pollInterval = 15000;

    function pad(n) { return String(n).padStart(2, '0'); }

    function updateClock() {
        var el = document.getElementById('wrClock');
        if (!el) return;
        var now = new Date();
        el.textContent = pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
    }

    function tickDeathClocks() {
        document.querySelectorAll('[data-wr-death-clock]').forEach(function (el) {
            var sec = parseInt(el.getAttribute('data-wr-death-clock'), 10);
            if (isNaN(sec) || sec <= 0) return;
            sec -= 1;
            el.setAttribute('data-wr-death-clock', sec);
            var h = Math.floor(sec / 3600);
            var m = Math.floor((sec % 3600) / 60);
            var s = sec % 60;
            el.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
            if (sec < 900) {
                el.closest('.wr-incident')?.classList.add('is-imminent');
            }
        });
    }

    function initCommsTabs() {
        var tabs = document.querySelectorAll('.wr-comms-tab');
        var box = document.getElementById('wrCommsBox');
        if (!tabs.length || !box) return;

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                tabs.forEach(function (t) { t.classList.remove('is-active'); });
                tab.classList.add('is-active');
                box.textContent = tab.getAttribute('data-comms-text') || '';
            });
        });

        var copyBtn = document.getElementById('wrCommsCopy');
        if (copyBtn) {
            copyBtn.addEventListener('click', function () {
                var text = box.textContent || '';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () {
                        copyBtn.textContent = 'Copiado!';
                        setTimeout(function () { copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copiar mensagem'; }, 2000);
                    });
                }
            });
        }
    }

    function updateSeverityGauge(score) {
        var circle = document.getElementById('wrSeverityCircle');
        var val = document.getElementById('wrSeverityVal');
        if (!circle || !val) return;
        var pct = Math.min(100, Math.max(0, score));
        var circumference = 2 * Math.PI * 22;
        circle.setAttribute('stroke-dasharray', circumference);
        circle.setAttribute('stroke-dashoffset', circumference - (pct / 100) * circumference);
        val.textContent = pct;
    }

    function poll() {
        if (!pollUrl) return;
        fetch(pollUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.severity_score !== undefined) {
                    updateSeverityGauge(data.severity_score);
                    document.querySelector('.ti-war-room.wr-root')?.setAttribute('data-severity', data.severity_level || 'stable');
                }
                var p1Badge = document.getElementById('wrP1Badge');
                if (p1Badge && data.p1_count !== undefined) {
                    if (data.p1_count > 0) {
                        p1Badge.className = 'status-badge status-badge--danger';
                        p1Badge.textContent = data.p1_count + ' P1 ATIVO' + (data.p1_count !== 1 ? 'S' : '');
                    } else {
                        p1Badge.className = 'status-badge status-badge--success';
                        p1Badge.textContent = 'SISTEMA ESTÁVEL';
                    }
                }
            })
            .catch(function () { /* silent */ });
    }

    document.addEventListener('DOMContentLoaded', function () {
        updateClock();
        setInterval(updateClock, 1000);
        setInterval(tickDeathClocks, 1000);
        initCommsTabs();

        var score = parseInt(
            (wrRoot && wrRoot.getAttribute('data-wr-severity')) || document.body.getAttribute('data-wr-severity') || '0',
            10
        );
        updateSeverityGauge(score);

        if (pollUrl) {
            setInterval(poll, pollInterval);
        }

        document.querySelectorAll('[data-ti-playbook]').forEach(function (root) {
            var url = root.getAttribute('data-url');
            var csrf = root.getAttribute('data-csrf');
            if (!url || !csrf) return;
            root.querySelectorAll('[data-ti-playbook-step]').forEach(function (input) {
                input.addEventListener('change', function () {
                    var row = input.closest('.ti-playbook-step, .wr-runbook-step, li');
                    if (row) row.classList.toggle('is-done', input.checked);
                    var body = new URLSearchParams();
                    body.set('_token', csrf);
                    body.set('step', input.getAttribute('data-ti-playbook-step'));
                    body.set('done', input.checked ? '1' : '0');
                    fetch(url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                        body: body.toString(),
                    }).catch(function () {
                        input.checked = !input.checked;
                        if (row) row.classList.toggle('is-done', input.checked);
                    });
                });
            });
        });
    });
})();
