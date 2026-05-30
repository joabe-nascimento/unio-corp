/**
 * Hub Inovação — ECharts (radar + funil) + live ticker + pipeline filter
 */
(function () {
    'use strict';

    /* ── Helpers ─────────────────────────────────────────────── */
    function getPayload() {
        var el = document.getElementById('inov-hub-payload');
        if (!el) return null;
        try { return JSON.parse(el.textContent); } catch (e) { return null; }
    }

    function hub() { return document.getElementById('inovHub'); }

    /* ── Charts ──────────────────────────────────────────────── */
    function initCharts(payload) {
        if (typeof echarts === 'undefined') return;

        var radarEl  = document.getElementById('inovRadarChart');
        var funnelEl = document.getElementById('inovFunnelChart');

        if (radarEl && payload.radar && payload.radar.length) {
            initRadar(radarEl, payload.radar);
        }
        if (funnelEl && payload.funnel && payload.funnel.length) {
            initFunnel(funnelEl, payload.funnel);
        }
    }

    function getCssVar(name) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }

    function initRadar(el, radar) {
        var chart = echarts.init(el, null, { renderer: 'svg' });
        var indicators = radar.map(function (d) { return { name: d.label, max: 100 }; });
        var values     = radar.map(function (d) { return d.value; });
        var accent     = getCssVar('--accent') || '#4F7FFF';
        var text3      = getCssVar('--text-3') || '#888';
        var border     = getCssVar('--border') || '#e0e0e0';

        chart.setOption({
            tooltip: { trigger: 'item' },
            radar: {
                indicator: indicators,
                shape: 'polygon',
                splitNumber: 4,
                axisName: { color: text3, fontSize: 11 },
                splitLine: { lineStyle: { color: border } },
                splitArea: { areaStyle: { color: ['rgba(255,255,255,0)', 'rgba(255,255,255,0)'] } },
                axisLine: { lineStyle: { color: border } },
            },
            series: [{
                type: 'radar',
                data: [{
                    value: values,
                    name: 'Maturidade',
                    areaStyle: { color: (accent + '28') },
                    lineStyle: { color: accent, width: 2 },
                    itemStyle: { color: accent },
                    symbol: 'circle',
                    symbolSize: 5,
                }],
            }],
        });

        window.addEventListener('resize', function () { chart.resize(); });

        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        mq.addEventListener('change', function () { chart.dispose(); initRadar(el, radar); });
    }

    function initFunnel(el, funnel) {
        var chart  = echarts.init(el, null, { renderer: 'svg' });
        var accent = getCssVar('--accent') || '#4F7FFF';
        var text1  = getCssVar('--text-1') || '#111';
        var text3  = getCssVar('--text-3') || '#888';

        var colors = [accent, accent, accent, accent, accent];
        var data   = funnel.map(function (d, i) {
            return { value: d.count, name: d.stage, itemStyle: { color: colors[i] || accent } };
        });

        chart.setOption({
            tooltip: {
                trigger: 'item',
                formatter: function (p) { return p.name + ': <strong>' + p.value + '</strong>'; },
            },
            series: [{
                type: 'funnel',
                left: '10%',
                width: '80%',
                minSize: 20,
                maxSize: '100%',
                sort: 'descending',
                gap: 3,
                label: { show: true, position: 'inside', color: '#fff', fontSize: 11, fontWeight: 700 },
                labelLine: { show: false },
                itemStyle: { borderWidth: 0 },
                data: data,
            }],
        });

        window.addEventListener('resize', function () { chart.resize(); });
    }

    /* ── Pipeline kanban drag-and-drop ──────────────────────────── */
    function initPipelineKanban() {
        var board = document.querySelector('[data-inov-kanban-dnd]');
        if (!board || typeof Sortable === 'undefined') return;

        var sortables = [];

        function updateCounts() {
            board.querySelectorAll('[data-inov-kanban-count]').forEach(function (badge) {
                var stageId = badge.getAttribute('data-inov-kanban-count');
                var col = board.querySelector('[data-inov-kanban-column="' + stageId + '"]');
                badge.textContent = col ? col.querySelectorAll('.inov-kanban-card').length : 0;
            });
        }

        function revertDrag(evt) {
            var item = evt.item;
            var from = evt.from;
            var ref = from.children[evt.oldIndex] || null;
            if (ref === item) return;
            from.insertBefore(item, ref);
            updateCounts();
        }

        async function onDragEnd(evt) {
            var card = evt.item;
            var dbId = card.getAttribute('data-db-id');
            var moveUrl = card.getAttribute('data-move-url');
            var csrf = card.getAttribute('data-csrf');
            var fromStage = evt.from.getAttribute('data-inov-kanban-column');
            var newStage = evt.to.getAttribute('data-inov-kanban-column');

            if (!dbId || !moveUrl || !newStage) return;
            if (fromStage === newStage && evt.oldIndex === evt.newIndex) return;

            var body = new FormData();
            body.append('_token', csrf);
            body.append('stage', newStage);

            card.classList.add('dev-kanban-card--saving');

            try {
                var res = await fetch(moveUrl, {
                    method: 'POST',
                    body: body,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                    },
                    credentials: 'same-origin',
                });
                var ct = res.headers.get('content-type') || '';
                if (!res.ok || ct.indexOf('json') === -1) throw new Error('move_failed');
                var data = await res.json();
                if (!data || !data.ok) throw new Error('move_rejected');
                card.setAttribute('data-stage', newStage);
                card.classList.remove('dev-kanban-card--saving');
                updateCounts();
            } catch (e) {
                card.classList.remove('dev-kanban-card--saving');
                revertDrag(evt);
                if (window.UnioToast && window.UnioToast.show) {
                    window.UnioToast.show('Não foi possível mover o item. Tente novamente.', 'error');
                }
            }
        }

        board.querySelectorAll('[data-inov-kanban-column]').forEach(function (columnEl) {
            var sortable = Sortable.create(columnEl, {
                group: 'inov-pipeline',
                animation: 160,
                draggable: '.inov-kanban-card',
                ghostClass: 'dev-kanban-ghost',
                dragClass: 'dev-kanban-drag',
                filter: 'a, button, input, select, textarea, label',
                preventOnFilter: true,
                delay: 80,
                delayOnTouchOnly: true,
                touchStartThreshold: 3,
                forceFallback: true,
                fallbackOnBody: true,
                swapThreshold: 0.65,
                onEnd: onDragEnd,
            });
            sortables.push(sortable);
        });
    }

    /* ── Pipeline filter ────────────────────────────────────────── */
    function initPipelineFilter() {
        var strip = document.querySelector('[data-inov-stage-strip]');
        var board = document.querySelector('[data-inov-pipeline-board]');
        var page  = document.querySelector('[data-inov-pipeline]');
        if (!strip || !board) return;

        function applyFilter(filter) {
            strip.querySelectorAll('[data-inov-stage]').forEach(function (c) {
                c.classList.toggle('is-active', c.getAttribute('data-inov-stage') === filter);
            });

            board.querySelectorAll('[data-stage]').forEach(function (card) {
                var match = filter === 'all' || card.getAttribute('data-stage') === filter;
                card.style.display = match ? '' : 'none';
            });

            board.querySelectorAll('[data-inov-pipeline-col]').forEach(function (col) {
                var stageId = col.getAttribute('data-inov-pipeline-col');
                var show = filter === 'all' || filter === stageId;
                col.classList.toggle('is-filtered-out', !show && filter !== 'all');
                col.style.display = show ? '' : 'none';
            });
        }

        strip.querySelectorAll('[data-inov-stage]').forEach(function (chip) {
            chip.addEventListener('click', function () {
                applyFilter(chip.getAttribute('data-inov-stage'));
            });
        });

        if (page) {
            page.classList.add('is-ready');

            page.querySelectorAll('[data-blocked-item]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var itemId = btn.getAttribute('data-blocked-item');
                    var stage  = btn.getAttribute('data-blocked-stage');
                    applyFilter('all');

                    var card = board.querySelector('[data-item-id="' + itemId + '"]');
                    if (!card) return;

                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    card.classList.remove('is-highlighted');
                    void card.offsetWidth;
                    card.classList.add('is-highlighted');
                    setTimeout(function () { card.classList.remove('is-highlighted'); }, 2200);

                    if (stage) {
                        setTimeout(function () {
                            var stageChip = strip.querySelector('[data-inov-stage="' + stage + '"]');
                            if (stageChip) stageChip.click();
                        }, 600);
                    }
                });
            });
        }
    }

    /* ── Impact timeline chart ───────────────────────────────────── */
    function initTimelineChart() {
        if (typeof echarts === 'undefined') return;
        var el = document.getElementById('inovTimelineChart');
        if (!el) return;
        var data = window.__inovImpactTimeline || [];
        if (!data.length) return;

        var chart   = echarts.init(el, null, { renderer: 'svg' });
        var accent  = getCssVar('--accent') || '#4F7FFF';
        var text3   = getCssVar('--text-3') || '#888';
        var border  = getCssVar('--border') || '#e0e0e0';
        var months  = data.map(function (d) { return d.month; });
        var captured  = data.map(function (d) { return d.captured || null; });
        var projected = data.map(function (d) { return d.projected; });

        chart.setOption({
            tooltip: {
                trigger: 'axis',
                formatter: function (params) {
                    var s = '<strong>' + params[0].axisValue + '</strong><br>';
                    params.forEach(function (p) {
                        if (p.value !== null) s += p.marker + ' ' + p.seriesName + ': <strong>R$ ' + p.value + ' k</strong><br>';
                    });
                    return s;
                },
            },
            legend: { bottom: 0, textStyle: { color: text3, fontSize: 11 } },
            grid: { top: 16, left: 40, right: 20, bottom: 40 },
            xAxis: {
                type: 'category', data: months,
                axisLine: { lineStyle: { color: border } },
                axisTick: { show: false },
                axisLabel: { color: text3, fontSize: 11 },
            },
            yAxis: {
                type: 'value',
                axisLabel: { color: text3, fontSize: 11, formatter: 'R${value}k' },
                splitLine: { lineStyle: { color: border } },
            },
            series: [
                {
                    name: 'Capturado', type: 'bar', barWidth: '35%',
                    data: captured,
                    itemStyle: { color: accent, borderRadius: [4, 4, 0, 0] },
                },
                {
                    name: 'Projetado', type: 'line',
                    data: projected,
                    smooth: true,
                    lineStyle: { color: accent, type: 'dashed', width: 2 },
                    itemStyle: { color: accent },
                    symbol: 'circle', symbolSize: 5,
                },
            ],
        });

        window.addEventListener('resize', function () { chart.resize(); });
    }

    /* ── Multi-step Idea Form ────────────────────────────────────────── */
    function initIdeaForm() {
        var form = document.getElementById('inovIdeaForm');
        if (!form) return;

        var stepper = document.getElementById('inovFormStepper');
        var current = 1;

        function goTo(step) {
            form.querySelectorAll('[data-form-panel]').forEach(function (p) {
                p.classList.toggle('is-active', parseInt(p.getAttribute('data-form-panel')) === step);
            });
            if (stepper) {
                stepper.querySelectorAll('[data-step]').forEach(function (s) {
                    var n = parseInt(s.getAttribute('data-step'));
                    var num = s.querySelector('.inov-form-step-num');
                    s.classList.remove('is-active', 'is-done');
                    if (num) {
                        num.textContent = n;
                    }
                    if (n === step) {
                        s.classList.add('is-active');
                    }
                    if (n < step) {
                        s.classList.add('is-done');
                        if (num) num.innerHTML = '<i class="fas fa-check" aria-hidden="true"></i>';
                    }
                });
            }
            current = step;
            if (step === 4) populateConfirmation();
        }

        function populateConfirmation() {
            setText('confirmTitle',      form.querySelector('[name="title"]'));
            setText('confirmHypothesis', form.querySelector('[name="hypothesis"]'));
            setText('confirmMetric',     form.querySelector('[name="metric"]'));
            var imp = form.querySelector('[name="impact"]');
            var eff = form.querySelector('[name="effort"]');
            var matEl = document.getElementById('confirmMatrix');
            if (imp && eff && matEl) {
                matEl.textContent = 'Impacto ' + imp.value + '% · Esforço ' + eff.value + '%';
            }
        }

        function setText(id, el) {
            var out = document.getElementById(id);
            if (out && el) out.textContent = el.value || '—';
        }

        form.addEventListener('click', function (e) {
            var next = e.target.closest('[data-form-next]');
            var back = e.target.closest('[data-form-back]');
            if (next) goTo(parseInt(next.getAttribute('data-form-next')));
            if (back) goTo(parseInt(back.getAttribute('data-form-back')));
        });

        // Range labels
        form.querySelectorAll('[data-range-label]').forEach(function (input) {
            var labelId = input.getAttribute('data-range-label');
            var label = document.getElementById(labelId);
            function updateLabel() {
                if (label) label.textContent = input.value + '%';
            }
            input.addEventListener('input', updateLabel);
            updateLabel();
        });
    }

    function initBacklogBoard() {
        var root = document.querySelector('[data-inov-backlog]');
        if (!root) return;

        function highlight(id) {
            root.querySelectorAll('.inov-matrix-row, [data-idea-id]').forEach(function (el) {
                if (!el.getAttribute('data-idea-id')) return;
                el.classList.toggle('is-highlighted', el.getAttribute('data-idea-id') === id);
            });
            var card = document.getElementById('idea-' + id);
            if (card) card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        root.querySelectorAll('.inov-matrix-row').forEach(function (dot) {
            dot.addEventListener('click', function () {
                highlight(dot.getAttribute('data-idea-id'));
            });
        });

        root.querySelectorAll('[data-idea-id]').forEach(function (card) {
            if (card.classList.contains('inov-matrix-row')) return;
            if (card.tagName === 'BUTTON') return;
            card.addEventListener('mouseenter', function () {
                highlight(card.getAttribute('data-idea-id'));
            });
        });

        root.querySelectorAll('[data-filter-tag]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tag = btn.getAttribute('data-filter-tag');
                root.querySelectorAll('[data-filter-tag]').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });

                var visible = 0;
                root.querySelectorAll('[data-idea-id]').forEach(function (el) {
                    var raw = el.getAttribute('data-idea-tags') || el.getAttribute('data-idea-tag') || '';
                    var tags = raw.split(',').map(function (t) { return t.trim(); }).filter(Boolean);
                    var show = tag === 'all' || tags.indexOf(tag) !== -1;
                    el.classList.toggle('is-filtered-out', !show);
                    if (show) visible++;
                });

                root.querySelectorAll('[data-matrix-group]').forEach(function (group) {
                    var rows = group.querySelectorAll('.inov-matrix-row');
                    var shown = 0;
                    rows.forEach(function (row) {
                        if (row.classList.contains('is-filtered-out')) return;
                        shown++;
                    });
                    var countEl = group.querySelector('[data-matrix-count]');
                    if (countEl) countEl.textContent = '(' + shown + ')';
                    group.classList.toggle('is-filtered-out', tag !== 'all' && shown === 0);
                });

                var emptyEl = root.querySelector('[data-inov-backlog-empty]');
                if (emptyEl) emptyEl.hidden = tag === 'all' || visible > 0;
            });
        });
    }

    /* ── Boot ───────────────────────────────────────────────────── */
    function boot() {
        var payload = getPayload();
        var section = payload ? payload.section : null;

        initPipelineFilter();
        initPipelineKanban();
        initIdeaForm();
        initBacklogBoard();

        if (section === 'overview' || section === 'analytics') {
            initCharts(payload || {});
        }

        if (section === 'impact') {
            initTimelineChart();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
