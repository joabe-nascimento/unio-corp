/**
 * Welcome — carrega Chart.js/ECharts/unio-charts sob demanda (IntersectionObserver).
 */
(function () {
    'use strict';

    var panel = document.querySelector('#welcome-analytics [data-unio-charts-panel]');
    if (!panel) return;

    var loaded = false;
    var loading = false;
    var pendingInit = false;

    function scripts() {
        return [
            { src: 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', crossOrigin: 'anonymous' },
            { src: 'https://cdn.jsdelivr.net/npm/echarts@5.5.1/dist/echarts.min.js', crossOrigin: 'anonymous' },
            { src: document.body.getAttribute('data-unio-charts-js') || '/js/unio-charts.js?v=5' }
        ];
    }

    function loadScript(def) {
        return new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = def.src;
            s.async = false;
            if (def.crossOrigin) s.crossOrigin = def.crossOrigin;
            s.onload = function () { resolve(); };
            s.onerror = function () { reject(new Error('Falha ao carregar ' + def.src)); };
            document.head.appendChild(s);
        });
    }

    function loadAll() {
        if (loaded || loading) return Promise.resolve();
        loading = true;
        panel.setAttribute('data-charts-loading', 'true');

        var chain = Promise.resolve();
        scripts().forEach(function (def) {
            chain = chain.then(function () { return loadScript(def); });
        });

        return chain.then(function () {
            loaded = true;
            loading = false;
            panel.removeAttribute('data-charts-loading');
            if (window.UnioCharts && pendingInit) {
                window.UnioCharts.init(document);
                pendingInit = false;
            }
        }).catch(function () {
            loading = false;
            panel.removeAttribute('data-charts-loading');
        });
    }

    function shouldLoad() {
        var section = document.getElementById('welcome-analytics');
        if (!section || section.hidden) return false;
        return true;
    }

    function boot() {
        if (!shouldLoad()) return;
        pendingInit = true;
        loadAll().then(function () {
            if (window.UnioCharts) window.UnioCharts.init(document);
        });
    }

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting && shouldLoad()) {
                    boot();
                    observer.disconnect();
                }
            });
        }, { rootMargin: '120px 0px', threshold: 0.05 });
        observer.observe(panel);
    } else {
        boot();
    }

    document.querySelectorAll('[data-welcome-pref-section="graficos"]').forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            if (checkbox.checked && shouldLoad()) boot();
        });
    });
})();
