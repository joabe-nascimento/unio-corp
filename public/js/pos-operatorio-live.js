(function () {
    'use strict';

    var root = document.querySelector('[data-pos-op-live]');
    if (!root) return;

    var mercureUrl = root.getAttribute('data-pos-op-mercure-url');
    var pollUrl = root.getAttribute('data-pos-op-poll-url');
    var pollInterval = 15000;
    var pollTimer = null;
    var es = null;
    var lastStats = null;

    try {
        lastStats = JSON.parse(root.getAttribute('data-pos-op-stats') || '{}');
    } catch (e) {
        lastStats = {};
    }

    function statsChanged(next) {
        if (!next) return false;
        return JSON.stringify(next) !== JSON.stringify(lastStats);
    }

    function applyRefresh(nextStats) {
        if (nextStats) {
            lastStats = nextStats;
        }
        window.location.reload();
    }

    function handlePayload(data) {
        if (!data || data.type !== 'pos_operatorio.alerta_update') return;
        applyRefresh(data.stats || null);
    }

    function fetchPoll() {
        if (!pollUrl) return;
        fetch(pollUrl, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (statsChanged(data.stats)) {
                    applyRefresh(data.stats);
                }
            })
            .catch(function () {});
    }

    function startPoll() {
        if (pollTimer !== null) return;
        fetchPoll();
        pollTimer = window.setInterval(fetchPoll, pollInterval);
    }

    function connectMercure() {
        if (!mercureUrl) {
            startPoll();
            return;
        }
        fetch(mercureUrl, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (cfg) {
                if (!cfg.hub_url) {
                    startPoll();
                    return;
                }
                es = new EventSource(cfg.hub_url, { withCredentials: true });
                es.onmessage = function (ev) {
                    try {
                        handlePayload(JSON.parse(ev.data));
                    } catch (e) { /* ignore */ }
                };
                es.onerror = function () {
                    if (es) {
                        es.close();
                        es = null;
                    }
                    startPoll();
                };
            })
            .catch(startPoll);
    }

    connectMercure();
})();
