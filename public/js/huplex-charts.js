/**
 * Huplex Charts — componente Chart.js reutilizável
 *
 * HTML:  [data-huplex-charts-panel="id"] + script.huplex-charts-data (JSON das seções)
 * API:   HuplexCharts.init() | HuplexCharts.initPanel(element) | HuplexCharts.destroyPanel(element)
 */
(function (global) {
    'use strict';

    var registry = {};
    var prefersReduced = global.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function cssVar(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    }

    function hexToRgba(hex, alpha) {
        var h = (hex || '').replace('#', '');
        if (h.length === 3) {
            h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        }
        if (h.length !== 6) {
            return 'rgba(79,127,255,' + alpha + ')';
        }
        var r = parseInt(h.slice(0, 2), 16);
        var g = parseInt(h.slice(2, 4), 16);
        var b = parseInt(h.slice(4, 6), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

    function palette(count) {
        var accent = cssVar('--accent', '#4F7FFF');
        var base = [accent, '#22c55e', '#f59e0b', '#a78bfa', '#f472b6', '#38bdf8', '#fb7185', '#94a3b8'];
        var out = [];
        for (var i = 0; i < count; i++) {
            out.push(base[i % base.length]);
        }
        return out;
    }

    function themeOptions() {
        var text = cssVar('--text-2', '#8A96A3');
        var grid = cssVar('--border', 'rgba(255,255,255,0.08)');
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: prefersReduced ? false : { duration: 700, easing: 'easeOutQuart' },
            plugins: {
                legend: {
                    labels: {
                        color: text,
                        font: { family: 'Inter', size: 11 },
                        boxWidth: 12,
                        padding: 14
                    }
                },
                tooltip: {
                    backgroundColor: cssVar('--surface-2', '#1a2030'),
                    titleColor: cssVar('--text-1', '#E8EDF5'),
                    bodyColor: cssVar('--text-2', '#8A96A3'),
                    borderColor: grid,
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 8
                }
            },
            scales: {}
        };
    }

    function buildChart(canvas, def) {
        if (typeof global.Chart === 'undefined') {
            return null;
        }

        var colors = palette(def.labels ? def.labels.length : (def.data ? def.data.length : 4));
        var type = def.type || 'bar';
        var opts = themeOptions();

        var config = {
            type: type === 'doughnut' ? 'doughnut' : type,
            data: { labels: def.labels || [], datasets: [] },
            options: opts
        };

        if (type === 'line' && def.datasets) {
            config.data.datasets = def.datasets.map(function (ds, idx) {
                var c = colors[idx % colors.length];
                return {
                    label: ds.label,
                    data: ds.data,
                    borderColor: c,
                    backgroundColor: hexToRgba(c, 0.12),
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                };
            });
            config.options.scales = {
                x: {
                    ticks: { color: cssVar('--text-3', '#8A96A3'), font: { size: 11 } },
                    grid: { color: cssVar('--border', 'rgba(255,255,255,0.06)') }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: cssVar('--text-3', '#8A96A3'), precision: 0, font: { size: 11 } },
                    grid: { color: cssVar('--border', 'rgba(255,255,255,0.06)') }
                }
            };
        } else if (type === 'doughnut' || type === 'pie') {
            config.data.datasets = [{
                data: def.data || [],
                backgroundColor: colors.map(function (c) { return hexToRgba(c, 0.85); }),
                borderColor: cssVar('--surface', '#161C24'),
                borderWidth: 2,
                hoverOffset: 6
            }];
            config.options.cutout = type === 'doughnut' ? '62%' : undefined;
            config.options.plugins.legend.position = 'bottom';
        } else {
            var isHorizontal = def.indexAxis === 'y';
            config.data.datasets = [{
                label: def.title || '',
                data: def.data || [],
                backgroundColor: colors.map(function (c) { return hexToRgba(c, 0.75); }),
                borderColor: colors,
                borderWidth: 0,
                borderRadius: 6,
                maxBarThickness: 36
            }];
            config.options.indexAxis = isHorizontal ? 'y' : 'x';
            config.options.scales = {
                x: {
                    beginAtZero: true,
                    ticks: { color: cssVar('--text-3', '#8A96A3'), precision: 0, font: { size: 11 } },
                    grid: { display: !isHorizontal, color: cssVar('--border', 'rgba(255,255,255,0.06)') }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: cssVar('--text-3', '#8A96A3'), precision: 0, font: { size: 11 } },
                    grid: { display: isHorizontal, color: cssVar('--border', 'rgba(255,255,255,0.06)') }
                }
            };
            if (isHorizontal) {
                config.options.plugins.legend.display = false;
            }
        }

        return new global.Chart(canvas, config);
    }

    function canvasId(panelId, chartId) {
        return 'huplex-chart-' + panelId + '-' + chartId;
    }

    function parseSections(panelEl) {
        var dataEl = panelEl.querySelector('.huplex-charts-data');
        if (!dataEl) {
            return [];
        }
        try {
            return JSON.parse(dataEl.textContent || '[]');
        } catch (e) {
            return [];
        }
    }

    function destroyPanel(panelEl) {
        var panelId = panelEl.getAttribute('data-huplex-charts-panel');
        if (!panelId || !registry[panelId]) {
            return;
        }
        Object.keys(registry[panelId]).forEach(function (chartId) {
            if (registry[panelId][chartId]) {
                registry[panelId][chartId].destroy();
            }
        });
        delete registry[panelId];
    }

    function initPanel(panelEl) {
        if (typeof global.Chart === 'undefined') {
            return;
        }

        var panelId = panelEl.getAttribute('data-huplex-charts-panel') || 'main';
        destroyPanel(panelEl);

        var sections = parseSections(panelEl);
        registry[panelId] = {};

        sections.forEach(function (section) {
            (section.charts || []).forEach(function (chartDef) {
                var id = canvasId(panelId, chartDef.id);
                var canvas = document.getElementById(id);
                if (!canvas) {
                    return;
                }
                registry[panelId][chartDef.id] = buildChart(canvas, chartDef);
            });
        });
    }

    function init(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('[data-huplex-charts-panel]').forEach(initPanel);
    }

    function onThemeChange() {
        document.querySelectorAll('[data-huplex-charts-panel]').forEach(function (panelEl) {
            initPanel(panelEl);
        });
    }

    global.HuplexCharts = {
        init: init,
        initPanel: initPanel,
        destroyPanel: destroyPanel,
        buildChart: buildChart
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(); });
    } else {
        init();
    }

    var themeObserver = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            if (m.attributeName === 'data-theme') {
                onThemeChange();
            }
        });
    });
    themeObserver.observe(document.documentElement, { attributes: true });

    global.addEventListener('resize', function () {
        Object.keys(registry).forEach(function (panelId) {
            Object.keys(registry[panelId]).forEach(function (chartId) {
                if (registry[panelId][chartId]) {
                    registry[panelId][chartId].resize();
                }
            });
        });
    });
})(window);
