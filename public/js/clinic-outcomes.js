/**
 * Unio Outcomes™ — Chart.js, tabela de risco, tour Driver.js e export CSV
 */
(function (global, document) {
    'use strict';

    var TOUR_STORAGE_KEY = 'unio-outcomes-tour-v1';
    var UNIO_RISK_COLORS = ['#d64550', '#e8a43c', '#4b72be'];
    var riskRowsCache = [];
    var pageInitialized = false;

    function readJson(id) {
        var el = document.getElementById(id);
        if (!el) return null;
        try {
            return JSON.parse(el.textContent || '{}');
        } catch (e) {
            return null;
        }
    }

    function esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function csvCell(value) {
        var s = String(value == null ? '' : value);
        if (/[;"\n\r]/.test(s)) {
            return '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }

    function downloadCsv(filename, lines) {
        var blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    var outcomesCenterLabelPlugin = {
        id: 'outcomesCenterLabel',
        afterDraw: function (chart) {
            var dataset = chart.data.datasets[0];
            if (!dataset || !dataset.data.length) return;

            var total = dataset.data.reduce(function (a, b) { return a + b; }, 0);
            if (!total) return;

            var alto = Number(dataset.data[0]) || 0;
            var pct = Math.round((alto / total) * 100);
            var area = chart.chartArea;
            var cx = (area.left + area.right) / 2;
            var cy = (area.top + area.bottom) / 2;
            var ctx = chart.ctx;
            var centerMain = alto > 0 ? pct + '%' : String(total);
            var centerSub = alto > 0 ? 'risco alto' : (total === 1 ? 'monitorado' : 'monitorados');

            ctx.save();
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillStyle = '#1e2a3a';
            ctx.font = '700 26px Nunito, Quicksand, system-ui, sans-serif';
            ctx.fillText(centerMain, cx, cy - 6);
            ctx.fillStyle = '#64748b';
            ctx.font = '600 11px Quicksand, system-ui, sans-serif';
            ctx.fillText(centerSub, cx, cy + 14);
            ctx.restore();
        },
    };

    function renderRiskLegend(payload, total) {
        var legend = document.getElementById('outcomes-risk-legend');
        if (!legend || !payload || !payload.labels) return;

        legend.innerHTML = '';
        payload.labels.forEach(function (label, i) {
            var value = payload.values[i] || 0;
            var pct = total > 0 ? Math.round((value / total) * 100) : 0;
            var li = document.createElement('li');
            li.className = 'outcomes-risk-chart-legend__item';
            li.innerHTML =
                '<span class="outcomes-risk-chart-legend__dot" style="background:' + UNIO_RISK_COLORS[i] + '"></span>' +
                '<span class="outcomes-risk-chart-legend__label">' + esc(label) + '</span>' +
                '<span class="outcomes-risk-chart-legend__value">' + value + ' <small>(' + pct + '%)</small></span>';
            legend.appendChild(li);
        });
    }

    function initRiskChart(payload) {
        if (!payload || !payload.values || typeof Chart === 'undefined') return;
        var canvas = document.getElementById('outcomes-risk-chart');
        if (!canvas) return;

        var total = payload.values.reduce(function (a, b) { return a + b; }, 0);
        if (total === 0) return;

        renderRiskLegend(payload, total);

        new Chart(canvas, {
            type: 'doughnut',
            data: {
                labels: payload.labels || [],
                datasets: [{
                    data: payload.values,
                    backgroundColor: UNIO_RISK_COLORS,
                    borderWidth: 2,
                    borderColor: '#fff',
                    hoverOffset: 4,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                layout: { padding: 4 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var v = ctx.parsed || 0;
                                var p = total > 0 ? Math.round((v / total) * 100) : 0;
                                return ctx.label + ': ' + v + ' (' + p + '%)';
                            },
                        },
                    },
                },
            },
            plugins: [outcomesCenterLabelPlugin],
        });
    }

    function formatAdesao(value) {
        if (value === null || value === undefined || value === '') {
            return '—';
        }
        return String(value) + '%';
    }

    function buildRiskRowHtml(r) {
        return (
            '<tr data-outcomes-row>' +
            '<td>' +
            '<strong class="outcomes-table__name">' + esc(r.nome) + '</strong>' +
            '<span class="outcomes-table__meta">' + esc(r.meta) + '</span>' +
            '</td>' +
            '<td class="outcomes-table__muted">' + esc(r.medico) + '</td>' +
            '<td>' +
            '<div class="outcomes-risk-cell">' +
            '<span class="outcomes-risk outcomes-risk--' + esc(r.nivel) + '">' + esc(r.score) + '</span>' +
            '<span class="outcomes-risk__label">' + esc(r.nivel_label) + '</span>' +
            '</div>' +
            '</td>' +
            '<td class="outcomes-table__num">' + esc(formatAdesao(r.adesao_pct)) + '</td>' +
            '<td class="outcomes-table__num">' + esc(r.dor || '—') + '</td>' +
            '<td class="outcomes-table__action">' +
            (r.ficha_href
                ? '<a href="' + esc(r.ficha_href) + '" class="btn-unio btn-sm btn-outline-unio">Ficha</a>'
                : '') +
            '</td>' +
            '</tr>'
        );
    }

    function initRiskGrid(rows) {
        var mount = document.getElementById('outcomes-risk-grid');
        if (!mount || !rows || !rows.length) return;

        riskRowsCache = rows.slice();

        mount.innerHTML =
            '<div class="table-responsive outcomes-table-wrap">' +
            '<table class="table table-sm outcomes-table outcomes-table--interactive mb-0">' +
            '<thead><tr>' +
            '<th scope="col">Paciente</th>' +
            '<th scope="col">Cirurgião</th>' +
            '<th scope="col">Score</th>' +
            '<th scope="col">Adesão</th>' +
            '<th scope="col">Dor</th>' +
            '<th scope="col"><span class="sr-only">Ações</span></th>' +
            '</tr></thead>' +
            '<tbody id="outcomes-risk-tbody"></tbody>' +
            '</table></div>' +
            '<p class="outcomes-risk-empty small text-muted mb-0 mt-2" hidden>Nenhum paciente encontrado.</p>' +
            '<p class="outcomes-risk-count small text-muted mb-0 mt-2"></p>';

        var tbody = mount.querySelector('#outcomes-risk-tbody');
        var emptyEl = mount.querySelector('.outcomes-risk-empty');
        var countEl = mount.querySelector('.outcomes-risk-count');
        var searchInput = document.querySelector('.outcomes-risk-search');

        function renderTable(term) {
            var filtered = filterRiskRows(riskRowsCache, term);
            tbody.innerHTML = filtered.map(buildRiskRowHtml).join('');
            if (emptyEl) {
                emptyEl.hidden = filtered.length > 0;
            }
            if (countEl) {
                var label = filtered.length === 1 ? '1 paciente' : filtered.length + ' pacientes';
                countEl.textContent = term
                    ? label + ' encontrado(s) com o filtro atual'
                    : label + ' em acompanhamento';
            }
        }

        renderTable('');

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                renderTable(String(searchInput.value || '').trim().toLowerCase());
            });
        }
    }

    function getGridSearchTerm() {
        var input = document.querySelector('.outcomes-risk-search');
        return input ? String(input.value || '').trim().toLowerCase() : '';
    }

    function filterRiskRows(rows, term) {
        if (!term) return rows;
        return rows.filter(function (r) {
            var hay = [
                r.nome,
                r.meta,
                r.codigo,
                r.medico,
                r.nivel_label,
                r.score,
                r.adesao_pct,
                r.dor,
            ].join(' ').toLowerCase();
            return hay.indexOf(term) !== -1;
        });
    }

    function exportRiskGridCsv() {
        if (!riskRowsCache.length) return;

        var term = getGridSearchTerm();
        var filtered = filterRiskRows(riskRowsCache, term);
        if (!filtered.length) {
            if (global.UnioToast && global.UnioToast.show) {
                global.UnioToast.show('Nenhuma linha para exportar com o filtro atual.', 'warning');
            }
            return;
        }

        var header = [
            'Paciente',
            'Código',
            'Cirurgião',
            'Score',
            'Nível',
            'Adesão %',
            'Dor',
        ].map(csvCell).join(';');

        var lines = [header];
        filtered.forEach(function (r) {
            lines.push([
                csvCell(r.nome),
                csvCell(r.codigo || r.meta),
                csvCell(r.medico),
                csvCell(r.score),
                csvCell(r.nivel_label),
                csvCell(r.adesao_pct),
                csvCell(r.dor),
            ].join(';'));
        });

        var stamp = new Date().toISOString().slice(0, 10);
        var suffix = term ? '-filtrado' : '';
        downloadCsv('outcomes-risco' + suffix + '-' + stamp + '.csv', lines);

        if (global.UnioToast && global.UnioToast.show) {
            global.UnioToast.show(
                filtered.length + ' linha(s) exportada(s)' + (term ? ' (filtro da tabela)' : '') + '.',
                'success'
            );
        }
    }

    function buildTourSteps() {
        return [
            {
                element: '.page-lead-kpis',
                popover: {
                    title: 'KPIs em tempo real',
                    description: 'Métricas animadas de pacientes monitorados, índice Outcomes, risco elevado e conversão CRM.',
                    side: 'bottom',
                },
            },
            {
                element: '.outcomes-panel--risk',
                popover: {
                    title: 'Score de risco preditivo',
                    description: 'Donut de distribuição + tabela com busca. Exporte só o que está filtrado na grade.',
                    side: 'top',
                },
            },
            {
                element: '.outcomes-panel--surgeons',
                popover: {
                    title: 'Índice por cirurgião',
                    description: 'Ranking white-label com adesão, dor, satisfação e alertas P1 por médico.',
                    side: 'top',
                },
            },
            {
                element: '.outcomes-panel--revenue',
                popover: {
                    title: 'Loop receita ↔ cuidado',
                    description: 'Funil CRM → cirurgia → pós-op → indicação com prova de resultado.',
                    side: 'top',
                },
            },
        ];
    }

    function markTourSeen() {
        try {
            localStorage.setItem(TOUR_STORAGE_KEY, '1');
        } catch (e) { /* ignore */ }
        updateTourButtonLabel();
    }

    function hasSeenTour() {
        try {
            return localStorage.getItem(TOUR_STORAGE_KEY) === '1';
        } catch (e) {
            return false;
        }
    }

    function updateTourButtonLabel() {
        var btn = document.querySelector('[data-outcomes-tour]');
        if (!btn) return;
        var seen = hasSeenTour();
        btn.title = seen ? 'Refazer tour do Outcomes' : 'Tour guiado do Outcomes';
        var label = btn.querySelector('[data-outcomes-tour-label]');
        if (label) {
            label.textContent = seen ? 'Refazer tour' : 'Tour';
        }
    }

    function driveOutcomesTour() {
        var Driver = global.UnioUx && global.UnioUx.getDriver ? global.UnioUx.getDriver() : null;
        if (!Driver) return;

        Driver({
            showProgress: true,
            progressText: '{{current}} de {{total}}',
            nextBtnText: 'Próximo',
            prevBtnText: 'Anterior',
            doneBtnText: 'Concluir',
            steps: buildTourSteps(),
            onDestroyed: markTourSeen,
        }).drive();
    }

    function initOutcomesTour() {
        var btn = document.querySelector('[data-outcomes-tour]');
        if (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                driveOutcomesTour();
            });
        }

        updateTourButtonLabel();

        if (!hasSeenTour()) {
            window.setTimeout(function () {
                if (!hasSeenTour()) {
                    driveOutcomesTour();
                }
            }, 900);
        }
    }

    function initGridExport() {
        var btn = document.querySelector('[data-outcomes-grid-export]');
        if (!btn) return;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            exportRiskGridCsv();
        });
    }

    function init() {
        if (pageInitialized) return;
        var payload = readJson('outcomes-page-data');
        if (!payload) return;
        pageInitialized = true;

        initRiskChart(payload.risk_chart);
        initRiskGrid(payload.risk_rows);
        initOutcomesTour();
        initGridExport();
    }

    function boot() {
        document.addEventListener('unio:ux-ready', init, { once: true });
        window.addEventListener('load', init);
    }

    boot();
})(window, document);
