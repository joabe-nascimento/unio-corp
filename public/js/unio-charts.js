/**
 * Unio Charts — Chart.js + ECharts enterprise analytics
 */
(function (global) {
    'use strict';

    var registry = {};
    var echartsRegistry = {};
    var liveTimers = {};
    var LIVE_INTERVAL_MS = 90000;
    var prefersReduced = global.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var ECHART_TYPES = {
        sankey: 1, heatmap: 1, bubble: 1, scatter: 1, radar: 1, 'area-line': 1,
        'stacked-bar': 1, funnel: 1, treemap: 1, gauge: 1, ring: 1, 'bar-pro': 1
    };

    function cssVar(name, fallback) {
        var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
        return v || fallback;
    }

    function isMobileViewport() {
        return global.matchMedia('(max-width: 991.98px)').matches;
    }

    function isNarrowChart(host) {
        if (!host) return isMobileViewport();
        var wrap = host.closest('.unio-chart-canvas-wrap') || host.parentElement;
        var w = wrap ? wrap.clientWidth : 0;
        return (w > 0 && w < 420) || isMobileViewport();
    }

    function echartLegend(narrow, text2, opts) {
        opts = opts || {};
        if (narrow || opts.forceBottom) {
            return {
                type: 'plain',
                bottom: opts.bottom != null ? opts.bottom : 0,
                left: 'center',
                width: opts.width || '94%',
                orient: 'horizontal',
                textStyle: { color: text2, fontSize: narrow ? 10 : 11 },
                itemWidth: 10,
                itemHeight: 10,
                itemGap: narrow ? 8 : 12
            };
        }
        return { top: 4, textStyle: { color: text2, fontSize: 11 } };
    }

    function echartGridBottom(grid, extra) {
        return Object.assign({}, grid, { bottom: (grid.bottom || 28) + (extra || 0) });
    }

    function hexToRgba(hex, alpha) {
        var h = (hex || '').replace('#', '');
        if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        if (h.length !== 6) return 'rgba(79,127,255,' + alpha + ')';
        return 'rgba(' + parseInt(h.slice(0, 2), 16) + ',' + parseInt(h.slice(2, 4), 16) + ',' + parseInt(h.slice(4, 6), 16) + ',' + alpha + ')';
    }

    function palette(count) {
        var accent = cssVar('--accent', '#4F7FFF');
        var base = [accent, '#22c55e', '#f59e0b', '#a78bfa', '#f472b6', '#38bdf8', '#fb7185', '#94a3b8'];
        var out = [];
        for (var i = 0; i < count; i++) out.push(base[i % base.length]);
        return out;
    }

    function formatTime(iso) {
        if (!iso) return '';
        try {
            return new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
        } catch (e) { return ''; }
    }

    function themeOptions() {
        var text = cssVar('--text-2', '#8A96A3');
        var grid = cssVar('--border', 'rgba(255,255,255,0.08)');
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: prefersReduced ? false : { duration: 700, easing: 'easeOutQuart' },
            plugins: {
                legend: { labels: { color: text, font: { family: 'Inter', size: 11 }, boxWidth: 12, padding: 14 } },
                tooltip: {
                    backgroundColor: cssVar('--surface-2', '#1a2030'),
                    titleColor: cssVar('--text-1', '#E8EDF5'),
                    bodyColor: text,
                    borderColor: grid,
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 8
                }
            },
            scales: {}
        };
    }

    function colorizeSankeyNodes(nodes, links) {
        var colors = palette(Math.max(nodes.length, 4));
        var depthMap = {};
        nodes.forEach(function (n) { depthMap[n.name] = 0; });
        for (var i = 0; i < 6; i++) {
            links.forEach(function (link) {
                if (depthMap[link.target] !== undefined && depthMap[link.source] !== undefined) {
                    depthMap[link.target] = Math.max(depthMap[link.target], depthMap[link.source] + 1);
                }
            });
        }
        return nodes.map(function (node, idx) {
            var color = colors[(depthMap[node.name] + idx) % colors.length];
            return { name: node.name, itemStyle: { color: hexToRgba(color, 0.92) }, label: { color: cssVar('--text-1', '#E8EDF5') } };
        });
    }

    function buildChart(canvas, def) {
        if (typeof global.Chart === 'undefined') return null;
        var colors = palette(def.labels ? def.labels.length : (def.data ? def.data.length : 4));
        var type = def.type || 'bar';
        var opts = themeOptions();
        var config = { type: type === 'doughnut' ? 'doughnut' : type, data: { labels: def.labels || [], datasets: [] }, options: opts };

        if (type === 'line' && def.datasets) {
            config.data.datasets = def.datasets.map(function (ds, idx) {
                var c = colors[idx % colors.length];
                return { label: ds.label, data: ds.data, borderColor: c, backgroundColor: hexToRgba(c, 0.12), borderWidth: 2, tension: 0.35, fill: true, pointRadius: 4, pointHoverRadius: 6 };
            });
            config.options.scales = {
                x: { ticks: { color: cssVar('--text-3', '#8A96A3'), font: { size: 11 } }, grid: { color: cssVar('--border', 'rgba(255,255,255,0.06)') } },
                y: { beginAtZero: true, ticks: { color: cssVar('--text-3', '#8A96A3'), precision: 0, font: { size: 11 } }, grid: { color: cssVar('--border', 'rgba(255,255,255,0.06)') } }
            };
        } else if (type === 'doughnut' || type === 'pie') {
            config.data.datasets = [{ data: def.data || [], backgroundColor: colors.map(function (c) { return hexToRgba(c, 0.85); }), borderColor: cssVar('--surface', '#161C24'), borderWidth: 2, hoverOffset: 6 }];
            config.options.cutout = type === 'doughnut' ? '62%' : undefined;
            config.options.plugins.legend.position = 'bottom';
        } else {
            var isHorizontal = def.indexAxis === 'y';
            config.data.datasets = [{ label: def.title || '', data: def.data || [], backgroundColor: colors.map(function (c) { return hexToRgba(c, 0.75); }), borderColor: colors, borderWidth: 0, borderRadius: 6, maxBarThickness: 36 }];
            config.options.indexAxis = isHorizontal ? 'y' : 'x';
            config.options.scales = {
                x: { beginAtZero: true, ticks: { color: cssVar('--text-3', '#8A96A3'), precision: 0, font: { size: 11 } }, grid: { display: !isHorizontal, color: cssVar('--border', 'rgba(255,255,255,0.06)') } },
                y: { beginAtZero: true, ticks: { color: cssVar('--text-3', '#8A96A3'), precision: 0, font: { size: 11 } }, grid: { display: isHorizontal, color: cssVar('--border', 'rgba(255,255,255,0.06)') } }
            };
            if (isHorizontal) config.options.plugins.legend.display = false;
        }
        return new global.Chart(canvas, config);
    }

    function echartBaseOption() {
        return {
            animation: !prefersReduced,
            animationDuration: prefersReduced ? 0 : 900,
            animationDurationUpdate: prefersReduced ? 0 : 650,
            animationEasing: 'cubicOut',
            backgroundColor: 'transparent',
            textStyle: { fontFamily: 'Inter, sans-serif', color: cssVar('--text-2', '#8A96A3') }
        };
    }

    function buildEchartOption(def, ctx) {
        var text1 = cssVar('--text-1', '#E8EDF5');
        var text2 = cssVar('--text-2', '#8A96A3');
        var text3 = cssVar('--text-3', '#8A96A3');
        var accent = cssVar('--accent', '#4F7FFF');
        var border = cssVar('--border', 'rgba(255,255,255,0.08)');
        var surface = cssVar('--surface-2', '#1a2030');
        var type = def.type || 'sankey';
        var colors = palette((def.datasets || def.series || def.data || []).length || 6);
        var narrow = ctx && ctx.narrow;
        var option = echartBaseOption();

        if (type === 'sankey') {
            option.tooltip = { trigger: 'item', backgroundColor: surface, borderColor: border, textStyle: { color: text1, fontSize: 12 } };
            option.series = [{
                type: 'sankey', layout: 'none', top: 12, bottom: 12, left: 16, right: 140,
                nodeWidth: 18, nodeGap: 12, draggable: false,
                emphasis: { focus: 'adjacency', lineStyle: { opacity: 0.72 } },
                lineStyle: { color: 'gradient', curveness: 0.5, opacity: 0.45 },
                label: { color: text1, fontSize: 11, fontWeight: 600 },
                data: colorizeSankeyNodes(def.nodes || [], def.links || []),
                links: def.links || []
            }];
        } else if (type === 'heatmap') {
            var matrix = def.matrix || [];
            var maxVal = 0;
            matrix.forEach(function (cell) { maxVal = Math.max(maxVal, cell[2] || 0); });
            option.tooltip = { position: 'top', backgroundColor: surface, borderColor: border, textStyle: { color: text1, fontSize: 12 } };
            option.grid = { top: 24, left: 110, right: 24, bottom: 56 };
            option.xAxis = { type: 'category', data: def.xLabels || [], splitArea: { show: true }, axisLabel: { color: text3, fontSize: 11 }, axisLine: { lineStyle: { color: border } } };
            option.yAxis = { type: 'category', data: def.yLabels || [], splitArea: { show: true }, axisLabel: { color: text3, fontSize: 11 }, axisLine: { lineStyle: { color: border } } };
            option.visualMap = { min: 0, max: Math.max(maxVal, 1), calculable: true, orient: 'horizontal', left: 'center', bottom: 4, inRange: { color: [hexToRgba(accent, 0.06), hexToRgba(accent, 0.98)] }, textStyle: { color: text3, fontSize: 10 } };
            option.series = [{ type: 'heatmap', data: matrix, label: { show: true, color: text1, fontSize: 11, fontWeight: 600 }, emphasis: { itemStyle: { shadowBlur: 10, shadowColor: hexToRgba(accent, 0.4) } } }];
        } else if (type === 'bubble') {
            option.grid = { top: 28, left: 48, right: 24, bottom: 40 };
            option.tooltip = { trigger: 'item', backgroundColor: surface, borderColor: border, formatter: function (p) { var d = p.data.value || p.data || []; return (p.data.name || 'Projeto') + '<br/>Tarefas: ' + d[0] + '<br/>Maturidade: ' + d[1]; } };
            option.xAxis = { name: def.xName || '', nameTextStyle: { color: text3, fontSize: 11 }, splitLine: { lineStyle: { color: border, type: 'dashed' } }, axisLabel: { color: text3 } };
            option.yAxis = { name: def.yName || '', nameTextStyle: { color: text3, fontSize: 11 }, splitLine: { lineStyle: { color: border, type: 'dashed' } }, axisLabel: { color: text3 } };
            option.series = [{
                type: 'scatter',
                symbolSize: function (d) { return (d[2] || d.value && d.value[2]) || 12; },
                data: (def.points || []).map(function (p, idx) {
                    return { value: [p.x, p.y, p.r], name: p.label || ('Projeto ' + (idx + 1)), itemStyle: { color: hexToRgba(colors[idx % colors.length], 0.62) } };
                }),
                itemStyle: { borderColor: accent, borderWidth: 1.5, shadowBlur: 8, shadowColor: hexToRgba(accent, 0.28) }
            }];
        } else if (type === 'area-line') {
            var areaDs = def.datasets || [];
            var areaBottomLegend = narrow || areaDs.length >= 3;
            option.grid = areaBottomLegend
                ? echartGridBottom({ top: narrow ? 8 : 36, left: 44, right: 16, bottom: 28 }, narrow ? 30 : 24)
                : { top: 36, left: 44, right: 20, bottom: 32 };
            option.tooltip = { trigger: 'axis', backgroundColor: surface, borderColor: border, textStyle: { color: text1, fontSize: 12 } };
            option.legend = echartLegend(narrow, text2, { forceBottom: areaDs.length >= 3 });
            option.xAxis = { type: 'category', boundaryGap: false, data: def.labels || [], axisLine: { lineStyle: { color: border } }, axisLabel: { color: text3, fontSize: 11 } };
            option.yAxis = { type: 'value', splitLine: { lineStyle: { color: border, type: 'dashed' } }, axisLabel: { color: text3, fontSize: 11 } };
            option.series = (def.datasets || []).map(function (ds, idx) {
                var c = colors[idx % colors.length];
                return {
                    name: ds.label, type: 'line', smooth: true, symbol: 'circle', symbolSize: 7,
                    lineStyle: { width: 2.5, color: c }, itemStyle: { color: c, borderWidth: 2, borderColor: surface },
                    areaStyle: { color: { type: 'linear', x: 0, y: 0, x2: 0, y2: 1, colorStops: [{ offset: 0, color: hexToRgba(c, 0.38) }, { offset: 1, color: hexToRgba(c, 0.02) }] } },
                    emphasis: { focus: 'series' }, data: ds.data || []
                };
            });
        } else if (type === 'stacked-bar') {
            var horizontal = !!def.horizontal;
            var stackDs = def.datasets || [];
            var stackBottomLegend = narrow || stackDs.length >= 3;
            var stackGrid = horizontal
                ? { top: 12, left: narrow ? 84 : 100, right: 12, bottom: 28 }
                : { top: stackBottomLegend ? 8 : 36, left: 40, right: 12, bottom: 28 };
            option.grid = stackBottomLegend ? echartGridBottom(stackGrid, narrow ? 32 : 28) : stackGrid;
            option.tooltip = { trigger: 'axis', axisPointer: { type: 'shadow' }, backgroundColor: surface, borderColor: border };
            option.legend = echartLegend(narrow, text2, { forceBottom: stackDs.length >= 3 });
            if (horizontal) {
                option.xAxis = { type: 'value', splitLine: { lineStyle: { color: border, type: 'dashed' } }, axisLabel: { color: text3 } };
                option.yAxis = { type: 'category', data: def.labels || [], axisLabel: { color: text3, fontSize: 11 }, axisLine: { lineStyle: { color: border } } };
            } else {
                option.xAxis = { type: 'category', data: def.labels || [], axisLabel: { color: text3, fontSize: 11 }, axisLine: { lineStyle: { color: border } } };
                option.yAxis = { type: 'value', splitLine: { lineStyle: { color: border, type: 'dashed' } }, axisLabel: { color: text3 } };
            }
            option.series = (def.datasets || []).map(function (ds, idx) {
                return {
                    name: ds.label, type: 'bar', stack: 'total', barMaxWidth: 32,
                    itemStyle: { borderRadius: horizontal ? [0, 4, 4, 0] : [4, 4, 0, 0], color: hexToRgba(colors[idx % colors.length], 0.82) },
                    emphasis: { focus: 'series' }, data: ds.data || []
                };
            });
        } else if (type === 'bar-pro') {
            var h = !!def.horizontal;
            option.grid = h ? { top: 12, left: 96, right: 24, bottom: 24 } : { top: 12, left: 44, right: 16, bottom: 40 };
            option.tooltip = { trigger: 'axis', backgroundColor: surface, borderColor: border };
            if (h) {
                option.xAxis = { type: 'value', splitLine: { lineStyle: { color: border, type: 'dashed' } }, axisLabel: { color: text3 } };
                option.yAxis = { type: 'category', data: def.labels || [], axisLabel: { color: text3, fontSize: 11 } };
            } else {
                option.xAxis = { type: 'category', data: def.labels || [], axisLabel: { color: text3, fontSize: 11, rotate: (def.labels || []).length > 4 ? 18 : 0 } };
                option.yAxis = { type: 'value', splitLine: { lineStyle: { color: border, type: 'dashed' } }, axisLabel: { color: text3 } };
            }
            option.series = [{
                type: 'bar', barMaxWidth: 36,
                data: (def.data || []).map(function (v, idx) {
                    return { value: v, itemStyle: { color: hexToRgba(colors[idx % colors.length], 0.82), borderRadius: h ? [0, 6, 6, 0] : [6, 6, 0, 0] } };
                })
            }];
        } else if (type === 'funnel') {
            option.tooltip = { trigger: 'item', backgroundColor: surface, borderColor: border, formatter: '{b}: {c}' };
            option.series = [{
                type: 'funnel', left: '8%', top: 24, bottom: 12, width: '84%',
                min: 0, max: 100, minSize: '12%', maxSize: '100%', sort: 'descending', gap: 4,
                label: { show: true, position: 'inside', color: text1, fontSize: 11, fontWeight: 600 },
                itemStyle: { borderColor: surface, borderWidth: 1 },
                data: (def.steps || []).map(function (s, idx) {
                    return { name: s.name, value: s.value, itemStyle: { color: hexToRgba(colors[idx % colors.length], 0.78) } };
                })
            }];
        } else if (type === 'treemap') {
            option.tooltip = { backgroundColor: surface, borderColor: border, formatter: function (p) { return p.name + ': ' + (p.value || ''); } };
            option.series = [{
                type: 'treemap', top: 8, bottom: 8, left: 8, right: 8, roam: false, nodeClick: false,
                breadcrumb: { show: false },
                label: { show: true, formatter: '{b}', color: text1, fontSize: 11, fontWeight: 600 },
                upperLabel: { show: true, height: 24, color: text1, fontWeight: 700 },
                itemStyle: { borderColor: surface, borderWidth: 2, gapWidth: 2 },
                levels: [{ itemStyle: { borderWidth: 0, gapWidth: 3 } }, { colorSaturation: [0.35, 0.65], itemStyle: { gapWidth: 2, borderColorSaturation: 0.6 } }],
                data: def.tree || []
            }];
        } else if (type === 'gauge') {
            var val = def.value || 0;
            var max = def.max || 100;
            option.series = [{
                type: 'gauge', startAngle: 200, endAngle: -20, min: 0, max: max,
                center: ['50%', '58%'], radius: '88%',
                progress: { show: true, width: 14, roundCap: true, itemStyle: { color: accent } },
                axisLine: { lineStyle: { width: 14, color: [[1, hexToRgba(accent, 0.12)]] } },
                axisTick: { show: false }, splitLine: { show: false },
                axisLabel: { color: text3, fontSize: 10, distance: 18 },
                pointer: { show: false },
                anchor: { show: false },
                title: { show: false },
                detail: {
                    valueAnimation: true, fontSize: 28, fontWeight: 800, fontFamily: 'Sora, sans-serif',
                    color: text1, offsetCenter: [0, '12%'],
                    formatter: function (v) { return Math.round(v) + (def.unit || ''); }
                },
                data: [{ value: val }]
            }];
        } else if (type === 'ring') {
            option.tooltip = { trigger: 'item', backgroundColor: surface, borderColor: border };
            option.legend = { bottom: 0, textStyle: { color: text2, fontSize: 10 } };
            option.series = [{
                type: 'pie', radius: ['46%', '72%'], center: ['50%', '46%'],
                padAngle: 2, itemStyle: { borderRadius: 6, borderColor: surface, borderWidth: 2 },
                label: { show: false },
                emphasis: { label: { show: true, fontSize: 12, fontWeight: 700, color: text1 } },
                data: (def.labels || []).map(function (label, idx) {
                    return { name: label, value: (def.data || [])[idx] || 0, itemStyle: { color: hexToRgba(colors[idx % colors.length], 0.85) } };
                })
            }];
        } else if (type === 'radar') {
            option.tooltip = { backgroundColor: surface, borderColor: border, textStyle: { color: text1, fontSize: 12 } };
            option.radar = {
                center: ['50%', '54%'], radius: '62%', indicator: def.indicators || [],
                splitArea: { areaStyle: { color: [hexToRgba(accent, 0.04), 'transparent'] } },
                splitLine: { lineStyle: { color: border } }, axisLine: { lineStyle: { color: border } },
                axisName: { color: text2, fontSize: 11 }
            };
            option.series = (def.series || []).map(function (s, idx) {
                var c = colors[idx % colors.length];
                return { name: s.name, type: 'radar', symbol: 'circle', symbolSize: 6, lineStyle: { width: 2, color: c }, itemStyle: { color: c }, areaStyle: { color: hexToRgba(c, 0.22) }, data: [{ value: s.value || [], name: s.name }] };
            });
        }

        return option;
    }

    function buildEchart(host, def) {
        if (typeof global.echarts === 'undefined' || !host) return null;
        var chart = global.echarts.init(host, null, { renderer: 'canvas' });
        chart.setOption(buildEchartOption(def, { narrow: isNarrowChart(host) }));
        return chart;
    }

    function isEchartDef(def) {
        return !!(ECHART_TYPES[def.type] || def.engine === 'echarts');
    }

    function canvasId(panelId, chartId) {
        return 'unio-chart-' + panelId + '-' + chartId;
    }

    function parseSections(panelEl) {
        var dataEl = panelEl.querySelector('.unio-charts-data');
        if (!dataEl) return [];
        try { return JSON.parse(dataEl.textContent || '[]'); } catch (e) { return []; }
    }

    function setSectionsJson(panelEl, sections) {
        var dataEl = panelEl.querySelector('.unio-charts-data');
        if (dataEl) dataEl.textContent = JSON.stringify(sections);
    }

    function flattenCharts(sections) {
        var out = [];
        sections.forEach(function (section) {
            (section.charts || []).forEach(function (chart) { out.push(chart); });
        });
        return out;
    }

    function updateKpis(panelEl, sections) {
        flattenCharts(sections).forEach(function (chart) {
            if (!chart.kpi) return;
            var kpiEl = panelEl.querySelector('[data-unio-chart-kpi="' + chart.id + '"]');
            if (!kpiEl) return;
            var valueEl = kpiEl.querySelector('[data-unio-chart-kpi-value]');
            var labelEl = kpiEl.querySelector('[data-unio-chart-kpi-label]');
            if (valueEl) valueEl.textContent = chart.kpi.value;
            if (labelEl) labelEl.textContent = chart.kpi.label;
        });
    }

    function updateExecutiveStrip(panelEl, executive) {
        if (!executive || !executive.kpis) return;
        var dataEl = panelEl.querySelector('.unio-charts-executive-data');
        if (dataEl) dataEl.textContent = JSON.stringify(executive);
        executive.kpis.forEach(function (kpi) {
            var card = panelEl.querySelector('[data-unio-executive-kpi="' + kpi.id + '"]');
            if (!card) return;
            var valueEl = card.querySelector('[data-unio-executive-value]');
            var labelEl = card.querySelector('[data-unio-executive-label]');
            var hintEl = card.querySelector('[data-unio-executive-hint]');
            if (valueEl) valueEl.textContent = kpi.value;
            if (labelEl) labelEl.textContent = kpi.label;
            if (hintEl) hintEl.textContent = kpi.hint;
        });
    }

    function updatePanelMeta(panelEl, meta) {
        if (!meta) return;
        var metaEl = panelEl.querySelector('[data-unio-charts-meta]');
        if (metaEl) {
            var parts = [];
            if (meta.chart_count) parts.push(meta.chart_count + ' gráfico' + (meta.chart_count === 1 ? '' : 's'));
            if (meta.section_count) parts.push(meta.section_count + ' módulo' + (meta.section_count === 1 ? '' : 's'));
            if (meta.generated_at) parts.push('atualizado ' + formatTime(meta.generated_at));
            metaEl.textContent = parts.join(' · ');
        }
        var liveEl = panelEl.querySelector('[data-unio-charts-live]');
        if (liveEl && panelEl.hasAttribute('data-unio-charts-feed-url')) liveEl.hidden = false;
    }

    function setPanelLoading(panelEl, loading) {
        panelEl.classList.toggle('unio-charts-panel--loading', !!loading);
        var statusEl = panelEl.querySelector('[data-unio-charts-loading-status]');
        if (statusEl) statusEl.hidden = !loading;
    }

    function revealCards(panelEl) {
        panelEl.querySelectorAll('.unio-chart-card--animate').forEach(function (card, idx) {
            if (prefersReduced) { card.classList.add('unio-chart-card--revealed'); return; }
            card.style.animationDelay = (idx * 0.05) + 's';
            card.classList.add('unio-chart-card--revealed');
        });
    }

    function destroyPanel(panelEl) {
        var panelId = panelEl.getAttribute('data-unio-charts-panel');
        if (!panelId) return;
        if (registry[panelId]) {
            Object.keys(registry[panelId]).forEach(function (id) { if (registry[panelId][id]) registry[panelId][id].destroy(); });
            delete registry[panelId];
        }
        if (echartsRegistry[panelId]) {
            Object.keys(echartsRegistry[panelId]).forEach(function (id) { if (echartsRegistry[panelId][id]) echartsRegistry[panelId][id].dispose(); });
            delete echartsRegistry[panelId];
        }
        if (liveTimers[panelId]) { clearInterval(liveTimers[panelId]); delete liveTimers[panelId]; }
    }

    function initChartsFromSections(panelEl, sections, options) {
        var panelId = panelEl.getAttribute('data-unio-charts-panel') || 'main';
        var softUpdate = options && options.softUpdate;
        if (!softUpdate) {
            destroyPanel(panelEl);
            registry[panelId] = {};
            echartsRegistry[panelId] = {};
        } else {
            if (!registry[panelId]) registry[panelId] = {};
            if (!echartsRegistry[panelId]) echartsRegistry[panelId] = {};
        }

        sections.forEach(function (section) {
            (section.charts || []).forEach(function (chartDef) {
                var id = canvasId(panelId, chartDef.id);
                if (isEchartDef(chartDef)) {
                    if (typeof global.echarts === 'undefined') return;
                    var host = document.getElementById(id);
                    if (!host) return;
                    var option = buildEchartOption(chartDef, { narrow: isNarrowChart(host) });
                    var existing = echartsRegistry[panelId][chartDef.id];
                    if (existing && softUpdate) { existing.setOption(option, true); return; }
                    if (existing) existing.dispose();
                    echartsRegistry[panelId][chartDef.id] = buildEchart(host, chartDef);
                    return;
                }
                if (typeof global.Chart === 'undefined') return;
                var canvas = document.getElementById(id);
                if (!canvas) return;
                if (registry[panelId][chartDef.id] && softUpdate) registry[panelId][chartDef.id].destroy();
                registry[panelId][chartDef.id] = buildChart(canvas, chartDef);
            });
        });

        if (!softUpdate) revealCards(panelEl);
    }

    function initPanel(panelEl, options) {
        initChartsFromSections(panelEl, parseSections(panelEl), options);
    }

    function refreshPanel(panelEl) {
        var url = panelEl.getAttribute('data-unio-charts-feed-url');
        if (!url) { initPanel(panelEl); return Promise.resolve(null); }
        setPanelLoading(panelEl, true);
        return fetch(url, { method: 'GET', headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (res) { if (!res.ok) throw new Error('HTTP ' + res.status); return res.json(); })
            .then(function (data) {
                var sections = data.sections || data;
                setSectionsJson(panelEl, sections);
                updateKpis(panelEl, sections);
                updateExecutiveStrip(panelEl, data.executive || null);
                updatePanelMeta(panelEl, data.meta || null);
                initChartsFromSections(panelEl, sections, { softUpdate: true });
                panelEl.dispatchEvent(new CustomEvent('unio-charts:refreshed', { bubbles: true, detail: { meta: data.meta || null } }));
                return data;
            })
            .catch(function () { return null; })
            .finally(function () { setPanelLoading(panelEl, false); });
    }

    function bindLivePanel(panelEl) {
        var panelId = panelEl.getAttribute('data-unio-charts-panel') || 'main';
        if (!panelEl.getAttribute('data-unio-charts-feed-url') || panelEl.dataset.unioChartsLiveBound === '1') return;
        panelEl.dataset.unioChartsLiveBound = '1';

        var refreshBtn = panelEl.querySelector('[data-unio-charts-refresh]');
        if (refreshBtn) refreshBtn.addEventListener('click', function () { refreshPanel(panelEl); });

        if (panelEl.getAttribute('data-unio-charts-live-refresh') === '1') {
            var schedule = function () {
                if (liveTimers[panelId]) clearInterval(liveTimers[panelId]);
                liveTimers[panelId] = setInterval(function () {
                    if (!document.hidden) refreshPanel(panelEl);
                }, LIVE_INTERVAL_MS);
            };
            if ('IntersectionObserver' in global) {
                new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) schedule();
                        else if (liveTimers[panelId]) { clearInterval(liveTimers[panelId]); delete liveTimers[panelId]; }
                    });
                }, { threshold: 0.12 }).observe(panelEl);
            } else schedule();
            refreshPanel(panelEl);
        } else {
            updatePanelMeta(panelEl, { chart_count: flattenCharts(parseSections(panelEl)).length, generated_at: new Date().toISOString() });
        }
    }

    function init(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('[data-unio-charts-panel]').forEach(function (panelEl) {
            initPanel(panelEl);
            bindLivePanel(panelEl);
        });
    }

    global.UnioCharts = { init: init, initPanel: initPanel, refreshPanel: refreshPanel, destroyPanel: destroyPanel, buildChart: buildChart };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(); });
    else init();

    new MutationObserver(function (mutations) {
        mutations.forEach(function (m) { if (m.attributeName === 'data-theme') document.querySelectorAll('[data-unio-charts-panel]').forEach(initPanel); });
    }).observe(document.documentElement, { attributes: true });

    global.addEventListener('resize', resizeAllCharts);
    global.addEventListener('unio-charts-resize', resizeAllCharts);

    var resizeLayoutTimer = null;

    function resizeAllCharts() {
        Object.keys(registry).forEach(function (pid) {
            Object.keys(registry[pid]).forEach(function (id) { if (registry[pid][id]) registry[pid][id].resize(); });
        });
        Object.keys(echartsRegistry).forEach(function (pid) {
            Object.keys(echartsRegistry[pid]).forEach(function (id) {
                var chart = echartsRegistry[pid][id];
                if (chart) chart.resize();
            });
        });
        if (resizeLayoutTimer) clearTimeout(resizeLayoutTimer);
        resizeLayoutTimer = setTimeout(function () {
            document.querySelectorAll('[data-unio-charts-panel]').forEach(function (panelEl) {
                initChartsFromSections(panelEl, parseSections(panelEl), { softUpdate: true });
            });
        }, 180);
    }
})(window);
