/**
 * Hub Integrações — catálogo, offcanvas, Observatório Causal
 */
(function () {
    'use strict';

    function initCatalogFilter() {
        var root = document.querySelector('[data-integ-catalog-filter]');
        var grid = document.querySelector('[data-integ-catalog-grid]');
        if (!root || !grid) return;

        root.querySelectorAll('[data-filter]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var cat = btn.getAttribute('data-filter');
                root.querySelectorAll('[data-filter]').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                grid.querySelectorAll('[data-catalog-item]').forEach(function (item) {
                    var show = cat === 'all' || item.getAttribute('data-category') === cat;
                    item.style.display = show ? '' : 'none';
                });
            });
        });
    }

    function bindEditForm(selector, fields, urlAttr) {
        document.querySelectorAll(selector).forEach(function (btn) {
            btn.addEventListener('click', function () {
                var raw = btn.getAttribute('data-integ-edit')
                    || btn.getAttribute('data-integ-webhook-edit')
                    || btn.getAttribute('data-integ-map-edit');
                if (!raw) return;
                try {
                    var data = JSON.parse(raw);
                    var form = document.querySelector(fields.form);
                    if (!form) return;
                    var editUrl = form.getAttribute(urlAttr);
                    if (editUrl && data.db_id) {
                        form.action = editUrl.replace('/0/', '/' + data.db_id + '/');
                    }
                    Object.keys(fields.map).forEach(function (key) {
                        var el = form.querySelector(fields.map[key]);
                        if (!el) return;
                        var val = data[key];
                        if (el.type === 'checkbox') {
                            el.checked = !!val;
                        } else {
                            el.value = val != null ? val : '';
                        }
                    });
                } catch (e) { /* ignore */ }
            });
        });
    }

    function initOffcanvasEdit() {
        bindEditForm('[data-integ-edit]', {
            form: '[data-integ-conector-form]',
            map: {
                nome: '#integConNome',
                categoria: '#integConCat',
                endpoint_url: '#integConEndpoint',
                health: '#integConHealth',
                operational_status: '#integConStatus',
                config_notas: '#integConNotas',
            },
        }, 'data-integ-edit-url');

        bindEditForm('[data-integ-webhook-edit]', {
            form: '[data-integ-webhook-form]',
            map: {
                nome: '#integWhNome',
                direcao: '#integWhDir',
                conector_id: '#integWhCon',
                evento: '#integWhEvento',
                url: '#integWhUrl',
            },
        }, 'data-integ-edit-url');

        bindEditForm('[data-integ-map-edit]', {
            form: '[data-integ-map-form]',
            map: {
                nome: '#integMapNome',
                conector_id: '#integMapCon',
                campo_origem: '#integMapOrig',
                campo_destino: '#integMapDest',
                transformacao: '#integMapTrans',
            },
        }, 'data-integ-edit-url');
    }

    function initCortexTabs() {
        var nav = document.querySelector('[data-integ-cortex-nav]');
        if (!nav) return;

        nav.querySelectorAll('[data-cortex-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tab = btn.getAttribute('data-cortex-tab');
                nav.querySelectorAll('[data-cortex-tab]').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                document.querySelectorAll('[data-integ-cortex-panel]').forEach(function (panel) {
                    var show = panel.getAttribute('data-integ-cortex-panel') === tab;
                    panel.classList.toggle('d-none', !show);
                });
            });
        });
    }

    function initCortexFlowSwitch() {
        var list = document.querySelector('[data-integ-cortex-flows]');
        var chainPanel = document.querySelector('[data-integ-cortex-chain-panel]');
        var impactPanel = document.querySelector('[data-integ-cortex-impact-panel]');
        if (!list || !chainPanel) return;

        var hubLabels = {};
        try {
            var payload = document.getElementById('integ-cortex-payload');
            if (payload) hubLabels = JSON.parse(payload.textContent).hub_labels || {};
        } catch (e) { /* ignore */ }

        list.querySelectorAll('[data-flow-key]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                list.querySelectorAll('[data-flow-key]').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                try {
                    var trace = JSON.parse(btn.getAttribute('data-flow-json'));
                    chainPanel.innerHTML = renderChain(trace, hubLabels);
                    if (impactPanel) {
                        impactPanel.innerHTML = renderImpact(trace);
                    }
                    var mesh = document.querySelector('[data-integ-mesh]');
                    if (mesh && mesh.__meshApi) {
                        mesh.__meshApi.highlightFlow(btn.getAttribute('data-flow-key'));
                    }
                } catch (e) { /* ignore */ }
            });
        });
    }

    function initShadowReplay() {
        var form = document.querySelector('[data-integ-shadow-form]');
        if (!form) return;

        var mapSelect = form.querySelector('[data-shadow-map-select]');
        var destInput = form.querySelector('#shadowDest');
        if (mapSelect && destInput) {
            mapSelect.addEventListener('change', function () {
                var opt = mapSelect.options[mapSelect.selectedIndex];
                if (opt) destInput.value = opt.getAttribute('data-destino') || '';
            });
        }

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var submitBtn = form.querySelector('[data-shadow-submit]');
            var results = document.querySelector('[data-integ-shadow-results]');
            if (!results) return;

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Executando…';
            }

            var fd = new FormData(form);
            fetch(form.getAttribute('data-action'), {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok && data.run) {
                        results.innerHTML = renderShadowRun(data.run);
                    } else {
                        results.innerHTML = '<p class="text-danger px-3">' + esc(data.error || 'Erro ao executar replay') + '</p>';
                    }
                })
                .catch(function () {
                    results.innerHTML = '<p class="text-danger px-3">Falha na comunicação com o servidor.</p>';
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-play mr-1" aria-hidden="true"></i> Executar';
                    }
                });
        });
    }

    function renderChain(trace, hubLabels) {
        if (!trace || !trace.nos) return '';
        var html = '<div class="section-header-wrap px-3 pt-1">' +
            '<h3 class="section-title mb-0">Cadeia causal</h3>' +
            '<p class="section-subtitle">' + esc(trace.titulo) + ' · ' + trace.confiabilidade + '% confiabilidade</p></div>' +
            '<div class="integ-causal-chain">';
        trace.nos.forEach(function (node, i) {
            var variant = node.status === 'error' ? 'danger' : (node.status === 'warn' ? 'warning' : 'success');
            html += '<div class="integ-causal-node integ-causal-node--' + esc(node.status || 'ok') + '">' +
                '<div class="integ-causal-node-icon"><i class="fas ' + esc(node.icon || 'fa-circle') + '"></i></div>' +
                '<div class="integ-causal-node-body">' +
                '<div class="integ-causal-node-head"><span class="integ-causal-hub">' +
                esc(hubLabels[node.hub] || node.hub) + '</span>' +
                '<span class="status-badge status-badge--' + variant + ' ti-badge ti-badge-metric ti-badge-metric--' + variant + '">' +
                esc((node.tipo || '').replace(/_/g, ' ')) + '</span></div>' +
                '<strong>' + esc(node.label) + '</strong>' +
                '<p class="section-subtitle mb-0">' + esc(node.detail || '') + '</p>';
            if (node.latency && node.latency !== '—') {
                html += '<small class="integ-causal-latency"><i class="fas fa-gauge-high"></i> ' + esc(node.latency) + '</small>';
            }
            html += '</div></div>';
            if (i < trace.nos.length - 1) {
                html += '<div class="integ-causal-connector"><span></span></div>';
            }
        });
        html += '</div>';
        return html;
    }

    function renderImpact(trace) {
        var html = '';
        var imp = trace.impacto || {};
        if ((imp.tickets_ti || 0) > 0) {
            html += '<section class="unio-card mt-4 integ-cortex-impact">' +
                '<div class="section-header-wrap px-3 pt-3"><h3 class="section-title mb-0">Impacto downstream</h3>' +
                '<p class="section-subtitle">Efeitos na plataforma UNio</p></div>' +
                '<div class="integ-cortex-impact-grid">' +
                '<div class="integ-cortex-impact-stat"><strong>' + imp.tickets_ti + '</strong><span>Chamados TI</span></div>' +
                '<div class="integ-cortex-impact-stat"><strong>' + (imp.usuarios_afetados || 0) + '</strong><span>Usuários afetados</span></div>' +
                '</div>';
            if (imp.chamados && imp.chamados.length) {
                html += '<ul class="integ-cortex-ticket-list mb-0">';
                imp.chamados.forEach(function (t) {
                    html += '<li><i class="fas fa-ticket"></i> ' + esc(t) + '</li>';
                });
                html += '</ul>';
            }
            html += '</section>';
        }
        var prev = trace.previsao || {};
        if (prev.risco_48h != null) {
            var pv = prev.risco_48h >= 75 ? 'danger' : (prev.risco_48h >= 50 ? 'warning' : 'success');
            html += '<section class="unio-card mt-4"><div class="px-3 py-3">' +
                '<h3 class="section-title mb-1">Previsão do fluxo</h3>' +
                '<span class="status-badge status-badge--' + pv + ' ti-badge ti-badge-metric ti-badge-metric--' + pv + '">' +
                prev.risco_48h + '% em 48h</span>' +
                '<p class="section-subtitle mt-2 mb-1">' + esc(prev.mensagem || '') + '</p>' +
                '<small>' + esc(prev.acao_sugerida || '') + '</small></div></section>';
        }
        return html;
    }

    function renderShadowRun(run) {
        var taxaVariant = run.taxa_sucesso >= 99 ? 'success' : (run.taxa_sucesso >= 95 ? 'warning' : 'danger');
        var html = '<div class="integ-shadow-run">' +
            '<div class="integ-shadow-stats mb-3">' +
            statBlock(run.total_eventos, 'Eventos replay', 'info') +
            statBlock(run.taxa_sucesso + '%', 'Sucesso previsto', taxaVariant) +
            statBlock(run.falhas, 'Falhas simuladas', 'warning') +
            statBlock(run.duplicatas, 'Duplicatas evitadas', 'danger') +
            '</div>' +
            '<p class="section-subtitle mb-2"><code>' + esc(run.campo_origem) + '</code> : ' +
            '<code>' + esc(run.campo_destino_atual) + '</code> → <code>' + esc(run.campo_destino_proposto) + '</code>' +
            ' · ' + run.periodo_dias + ' dia(s) · ' + esc(run.criado_em) + '</p>';

        if (run.amostras && run.amostras.length) {
            html += '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr>' +
                '<th>Hora</th><th>Payload</th><th>Atual</th><th>Proposto</th><th>Resultado</th></tr></thead><tbody>';
            run.amostras.forEach(function (s) {
                var rv = s.resultado === 'ok' ? 'success' : (s.resultado === 'fail' ? 'danger' : 'warning');
                html += '<tr><td>' + esc(s.timestamp || '—') + '</td>' +
                    '<td class="admin-table-mono">' + esc(s.payload) + '</td>' +
                    '<td class="admin-table-mono">' + esc(s.atual) + '</td>' +
                    '<td class="admin-table-mono">' + esc(s.proposto) + '</td>' +
                    '<td><span class="status-badge status-badge--' + rv + ' ti-badge ti-badge-metric ti-badge-metric--' + rv + '">' +
                    esc((s.resultado || '').toUpperCase()) + '</span>' +
                    (s.motivo ? '<br><small class="text-muted">' + esc(s.motivo) + '</small>' : '') + '</td></tr>';
            });
            html += '</tbody></table></div>';
        }
        html += '</div>';
        return html;
    }

    function statBlock(val, label, variant) {
        return '<div class="integ-shadow-stat"><strong>' + esc(val) + '</strong><span>' + esc(label) + '</span>' +
            '<span class="status-badge status-badge--' + variant + ' ti-badge ti-badge-metric ti-badge-metric--' + variant + '">' +
            variant.toUpperCase() + '</span></div>';
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function initDriftActions() {
        document.querySelectorAll('[data-drift-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var acao = btn.getAttribute('data-drift-action');
                var id = btn.getAttribute('data-drift-id');
                var url = btn.getAttribute('data-drift-url');
                var token = btn.getAttribute('data-drift-token');
                var row = document.querySelector('[data-drift-row="' + id + '"]');
                var actionsCell = document.querySelector('[data-drift-actions="' + id + '"]');

                btn.disabled = true;

                var fd = new FormData();
                fd.append('_token', token);
                fd.append('acao', acao);

                fetch(url, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        var labelMap = { aceito: 'ACEITO', ignorado: 'IGNORADO', mapeado: 'MAPEADO' };
                        var colorMap = { aceito: 'success', ignorado: 'secondary', mapeado: 'info' };
                        var res = data.resolucao || 'aceito';
                        if (actionsCell) {
                            actionsCell.innerHTML =
                                '<span class="status-badge status-badge--' + colorMap[res] +
                                ' ti-badge ti-badge-metric ti-badge-metric--' + colorMap[res] + '">' +
                                esc(labelMap[res] || res.toUpperCase()) + '</span>';
                        }
                        if (row) row.classList.add('text-muted');
                    } else {
                        btn.disabled = false;
                        alert(data.error || 'Erro ao processar ação.');
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    alert('Falha na comunicação com o servidor.');
                });
            });
        });
    }

    function initDeadLetterActions() {
        document.querySelectorAll('[data-dl-payload-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-dl-payload-toggle');
                var row = document.getElementById('dlPayload' + id);
                if (row) row.classList.toggle('d-none');
            });
        });

        document.querySelectorAll('[data-dl-filter]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var filter = btn.getAttribute('data-dl-filter');
                document.querySelectorAll('[data-dl-filter]').forEach(function (b) {
                    b.classList.toggle('is-active', b === btn);
                });
                document.querySelectorAll('[data-dl-row]').forEach(function (row) {
                    var status = row.getAttribute('data-dl-status');
                    var show = filter === 'all' || status === filter;
                    row.style.display = show ? '' : 'none';
                    var payloadRow = document.getElementById('dlPayload' + row.getAttribute('data-dl-row'));
                    if (payloadRow && !show) payloadRow.style.display = 'none';
                });
            });
        });

        document.querySelectorAll('[data-dl-action]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (btn.disabled) return;
                var acao = btn.getAttribute('data-dl-action');
                if (acao === 'descartar' && !confirm('Descartar este evento permanentemente?')) return;

                var id = btn.getAttribute('data-dl-id');
                var url = btn.getAttribute('data-dl-url');
                var token = btn.getAttribute('data-dl-token');
                var row = document.querySelector('[data-dl-row="' + id + '"]');

                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                var fd = new FormData();
                fd.append('_token', token);

                fetch(url, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        var feedback = document.getElementById('dlActionFeedback');
                        if (feedback) {
                            var toast = document.createElement('div');
                            var msg = acao === 'retry'
                                ? (data.status === 'resolvido' ? 'Evento reprocessado com sucesso.' : 'Retry executado — ainda pendente.')
                                : 'Evento descartado.';
                            toast.className = 'alert alert-' + (data.status === 'resolvido' || acao === 'descartar' ? 'success' : 'warning') + ' alert-dismissible mb-2';
                            toast.innerHTML = msg + ' <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>';
                            feedback.appendChild(toast);
                            setTimeout(function () { toast.remove(); }, 4500);
                        }

                        if (row) {
                            if (acao === 'descartar') {
                                row.classList.add('text-muted');
                                row.setAttribute('data-dl-status', 'descartado');
                            } else if (data.status) {
                                row.setAttribute('data-dl-status', data.status);
                            }

                            var statusCell = document.querySelector('[data-dl-status-cell="' + id + '"]');
                            if (statusCell && data.status) {
                                var svMap = { resolvido: 'success', descartado: 'secondary', retry: 'info', pendente: 'warning' };
                                var sv = svMap[data.status] || 'warning';
                                statusCell.innerHTML = '<span class="status-badge status-badge--' + sv +
                                    ' ti-badge ti-badge-metric ti-badge-metric--' + sv + '">' +
                                    esc(data.status.toUpperCase()) + '</span>';
                            }

                            if (data.tentativas) {
                                var tentCell = document.querySelector('[data-dl-tentativas="' + id + '"]');
                                if (tentCell) {
                                    var tv = data.tentativas > 3 ? 'danger' : 'warning';
                                    tentCell.innerHTML = '<span class="ti-badge ti-badge-metric ti-badge-metric--' + tv + '">' +
                                        esc(data.tentativas) + '×</span>';
                                }
                            }

                            if (data.proxima_retry !== undefined) {
                                var retryCell = document.querySelector('[data-dl-retry="' + id + '"]');
                                if (retryCell) retryCell.textContent = data.proxima_retry || '—';
                            }

                            var actionsCell = document.querySelector('[data-dl-actions="' + id + '"]');
                            if (actionsCell && (acao === 'descartar' || data.status === 'resolvido')) {
                                actionsCell.innerHTML = acao === 'descartar'
                                    ? '<small class="text-muted">Descartado</small>'
                                    : '<small class="text-success"><i class="fas fa-check"></i> Resolvido</small>';
                            } else {
                                btn.disabled = false;
                                btn.innerHTML = '<i class="fas fa-rotate-right"></i> Retry';
                            }
                        }
                    } else {
                        btn.disabled = false;
                        btn.innerHTML = acao === 'retry' ? '<i class="fas fa-rotate-right"></i> Retry' : '<i class="fas fa-trash"></i>';
                        alert(data.error || 'Erro ao processar.');
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.innerHTML = acao === 'retry' ? '<i class="fas fa-rotate-right"></i> Retry' : '<i class="fas fa-trash"></i>';
                    alert('Falha na comunicação com o servidor.');
                });
            });
        });
    }

    function initSimuladorImpacto() {
        document.querySelectorAll('[data-simular-impacto]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var url = btn.getAttribute('data-simular-url');
                var token = btn.getAttribute('data-simular-token');
                var nome = btn.getAttribute('data-simular-nome');
                var content = document.getElementById('simuladorImpactoContent');

                if (window.UnioOffcanvas) {
                    window.UnioOffcanvas.open('integ-simular-impacto');
                }

                if (content) {
                    content.innerHTML = '<div class="text-center text-muted py-5">' +
                        '<i class="fas fa-spinner fa-spin fa-2x mb-3 d-block"></i>Analisando impacto de <strong>' +
                        esc(nome) + '</strong>…</div>';
                }

                var fd = new FormData();
                fd.append('_token', token);

                fetch(url, {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (content) content.innerHTML = renderSimulacao(data);
                })
                .catch(function () {
                    if (content) content.innerHTML = '<p class="text-danger px-3">Falha ao simular impacto.</p>';
                });
            });
        });
    }

    function renderSimulacao(data) {
        if (data.error) return '<p class="text-danger px-3">' + esc(data.error) + '</p>';
        var riscoClass = data.risco_global_pct > 60 ? 'danger' : (data.risco_global_pct > 30 ? 'warning' : 'success');
        var html = '<div class="integ-simulador">' +
            '<p class="integ-simulador-lead">Impacto estimado ao pausar <strong>' + esc(data.conector) + '</strong></p>' +
            '<div class="integ-shadow-stats mb-3">' +
            statBlock(data.risco_global_pct + '%', 'Risco global', riscoClass) +
            statBlock(data.total_fluxos, 'Fluxos afetados', data.total_fluxos > 0 ? 'warning' : 'success') +
            statBlock(data.usuarios_estimados, 'Usuários estimados', 'info') +
            statBlock(data.chamados_estimados, 'Chamados TI est.', 'warning') +
            '</div>' +
            '<div class="alert alert-' + (riscoClass === 'danger' ? 'danger' : (riscoClass === 'warning' ? 'warning' : 'success')) + ' mb-3">' +
            '<i class="fas fa-circle-info me-1"></i>' + esc(data.recomendacao) + '</div>';

        if (data.fluxos_afetados && data.fluxos_afetados.length) {
            html += '<h5 class="integ-simulador-subtitle">Fluxos afetados</h5>' +
                '<ul class="integ-simulador-flows">';
            data.fluxos_afetados.forEach(function (f) {
                html += '<li class="integ-simulador-flow">' +
                    '<span class="integ-simulador-flow-title">' + esc(f.titulo) + '</span>' +
                    '<span class="ti-badge ti-badge-metric ti-badge-metric--secondary">' + f.confiabilidade + '%</span></li>';
            });
            html += '</ul>';
        } else {
            html += '<p class="text-muted">Nenhum fluxo afetado diretamente identificado.</p>';
        }

        html += '</div>';
        return html;
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCatalogFilter();
        initOffcanvasEdit();
        initCortexTabs();
        initCortexFlowSwitch();
        initShadowReplay();
        initMeshTree();
        initDriftActions();
        initDeadLetterActions();
        initSimuladorImpacto();
    });

    function initMeshTree() {
        var tree = document.querySelector('[data-integ-mesh]');
        var flowList = document.querySelector('[data-integ-cortex-flows]');
        if (!tree || !flowList) return;

        var activeFilter = null;

        function applyFilter(flowKeys, sourceEl) {
            if (!flowKeys || !flowKeys.length) {
                clearFilter();
                return;
            }
            activeFilter = flowKeys;
            tree.querySelectorAll('[data-mesh-hub], [data-mesh-edge], .integ-mesh-link').forEach(function (el) {
                var keys = (el.getAttribute('data-flow-keys') || el.getAttribute('data-flow-key') || '').split(',').filter(Boolean);
                var match = keys.some(function (k) { return flowKeys.indexOf(k) >= 0; });
                el.classList.toggle('is-active', el === sourceEl);
                el.classList.toggle('is-dim', !match && el !== sourceEl);
            });
            tree.querySelectorAll('.integ-mesh-path').forEach(function (path) {
                var keys = (path.getAttribute('data-flow-keys') || '').split(',').filter(Boolean);
                var match = keys.some(function (k) { return flowKeys.indexOf(k) >= 0; });
                path.classList.toggle('is-highlight', match);
                path.classList.toggle('is-dim', !match);
            });
            flowList.querySelectorAll('[data-flow-key]').forEach(function (btn) {
                var key = btn.getAttribute('data-flow-key');
                var show = flowKeys.indexOf(key) >= 0;
                btn.classList.toggle('is-mesh-filtered-out', !show);
                btn.classList.toggle('is-mesh-highlight', show);
            });
            var first = flowList.querySelector('[data-flow-key="' + flowKeys[0] + '"]');
            if (first) first.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }

        function clearFilter() {
            activeFilter = null;
            tree.querySelectorAll('.is-active, .is-dim').forEach(function (el) {
                el.classList.remove('is-active', 'is-dim');
            });
            tree.querySelectorAll('.integ-mesh-path').forEach(function (p) {
                p.classList.remove('is-highlight', 'is-dim');
            });
            flowList.querySelectorAll('[data-flow-key]').forEach(function (btn) {
                btn.classList.remove('is-mesh-filtered-out', 'is-mesh-highlight');
            });
        }

        tree.querySelectorAll('[data-mesh-hub]').forEach(function (node) {
            node.addEventListener('click', function () {
                var keys = (node.getAttribute('data-flow-keys') || '').split(',').filter(Boolean);
                if (activeFilter && keys.join(',') === activeFilter.join(',')) {
                    clearFilter();
                } else {
                    applyFilter(keys, node);
                }
            });
        });

        tree.querySelectorAll('[data-mesh-edge], .integ-mesh-link').forEach(function (chip) {
            chip.addEventListener('click', function () {
                var keys = (chip.getAttribute('data-flow-keys') || chip.getAttribute('data-flow-key') || '').split(',').filter(Boolean);
                if (!keys.length) return;
                var same = activeFilter && activeFilter.length === keys.length &&
                    keys.every(function (k) { return activeFilter.indexOf(k) >= 0; });
                if (same) {
                    clearFilter();
                } else {
                    applyFilter(keys, chip);
                    if (keys.length === 1) {
                        var flowBtn = flowList.querySelector('[data-flow-key="' + keys[0] + '"]');
                        if (flowBtn) flowBtn.click();
                    }
                }
            });
        });

        tree.querySelectorAll('.integ-mesh-path').forEach(function (path) {
            path.addEventListener('click', function () {
                var keys = (path.getAttribute('data-flow-keys') || '').split(',').filter(Boolean);
                if (keys.length === 1 && activeFilter && activeFilter.length === 1 && activeFilter[0] === keys[0]) {
                    clearFilter();
                } else {
                    applyFilter(keys, path);
                    if (keys.length === 1) {
                        var flowBtn = flowList.querySelector('[data-flow-key="' + keys[0] + '"]');
                        if (flowBtn) flowBtn.click();
                    }
                }
            });
        });

        tree.__meshApi = {
            highlightFlow: function (flowKey) {
                if (!flowKey) return;
                applyFilter([flowKey], null);
            },
            clearFilter: clearFilter,
        };
    }
})();

// ── Playbook Runs ────────────────────────────────────────────────────────────
(function initPlaybookRuns() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Toggle step panels
        document.querySelectorAll('.integ-pb-run-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var runId = btn.getAttribute('data-run-id');
                var panel = document.getElementById('integ-pb-steps-' + runId);
                if (!panel) return;
                var isExpanded = btn.getAttribute('aria-expanded') === 'true';
                panel.classList.toggle('d-none', isExpanded);
                btn.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
                var icon = btn.querySelector('.fa-chevron-down, .fa-chevron-up');
                if (icon) {
                    icon.classList.toggle('fa-chevron-down', isExpanded);
                    icon.classList.toggle('fa-chevron-up', !isExpanded);
                }
            });
        });

        // Step checkboxes — AJAX mark step
        document.querySelectorAll('.integ-pb-step-check').forEach(function (chk) {
            chk.addEventListener('change', function () {
                var runId = chk.getAttribute('data-run-id');
                var stepIndex = parseInt(chk.getAttribute('data-step-index'), 10);
                var url = chk.getAttribute('data-url');
                var done = chk.checked;
                var stepRow = chk.closest('.integ-pb-step');
                var stepTitle = stepRow ? stepRow.querySelector('.fw-medium') : null;

                var fd = new FormData();
                fd.append('step_index', stepIndex);
                fd.append('done', done ? '1' : '0');

                fetch(url, { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data.ok) {
                            alert(data.error || 'Erro ao marcar passo.');
                            chk.checked = !done;
                            return;
                        }
                        if (stepTitle) {
                            stepTitle.classList.toggle('text-decoration-line-through', done);
                            stepTitle.classList.toggle('text-muted', done);
                        }
                        var run = data.run;
                        var runItem = document.querySelector('.integ-pb-run-item[data-run-id="' + runId + '"]');
                        if (!runItem) return;

                        var bar = runItem.querySelector('.progress-bar');
                        if (bar) {
                            bar.style.width = run.progress_pct + '%';
                            bar.setAttribute('aria-valuenow', run.progress_pct);
                            if (run.status === 'concluido') bar.classList.add('bg-success');
                        }
                        var meta = runItem.querySelector('.integ-pb-progress small');
                        if (meta) meta.textContent = run.steps_done + '/' + run.steps_total + ' passos · ' + run.progress_pct + '%';

                        if (run.status === 'concluido') {
                            runItem.classList.add('is-done');
                            var icon = runItem.querySelector('.fa-circle-play');
                            if (icon) {
                                icon.classList.remove('fa-circle-play', 'text-info');
                                icon.classList.add('fa-circle-check', 'text-success');
                            }
                            runItem.querySelectorAll('.integ-pb-step-check').forEach(function (c) { c.disabled = true; });
                        }
                    })
                    .catch(function () {
                        chk.checked = !done;
                        alert('Erro de rede ao marcar passo.');
                    });
            });
        });

        // Auto-expand first in-progress run
        var firstActive = document.querySelector('.integ-pb-run-item:not(.is-done) .integ-pb-run-toggle');
        if (firstActive) firstActive.click();

        // "Iniciar execução" buttons
        document.querySelectorAll('.integ-pb-iniciar-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-url');
                var titulo = btn.getAttribute('data-playbook-titulo');
                if (!confirm('Iniciar execução de "' + titulo + '"?')) return;

                btn.disabled = true;
                fetch(url, { method: 'POST' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        btn.disabled = false;
                        if (!data.ok) {
                            alert(data.error || 'Erro ao iniciar playbook.');
                            return;
                        }
                        // Reload the page to show the new run
                        window.location.reload();
                    })
                    .catch(function () {
                        btn.disabled = false;
                        alert('Erro de rede ao iniciar playbook.');
                    });
            });
        });

        // "Publicar evento" button in observatório
        var eventPublishBtn = document.getElementById('integ-evento-publicar-btn');
        if (eventPublishBtn) {
            eventPublishBtn.addEventListener('click', function () {
                var url = eventPublishBtn.getAttribute('data-url');
                var tipo = (document.getElementById('integ-event-tipo') || {}).value || '';
                var origem = (document.getElementById('integ-event-origem') || {}).value || null;
                var payload = (document.getElementById('integ-event-payload') || {}).value || '{}';

                if (!tipo) { alert('Informe o tipo do evento.'); return; }

                var fd = new FormData();
                fd.append('tipo', tipo);
                fd.append('origem', origem || '');
                fd.append('payload', payload);

                eventPublishBtn.disabled = true;
                fetch(url, { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        eventPublishBtn.disabled = false;
                        if (!data.ok) {
                            alert(data.error || 'Erro ao publicar evento.');
                            return;
                        }
                        // Prepend event to feed
                        var feed = document.getElementById('integ-domain-events-feed');
                        if (!feed) return;
                        var ev = data.event;
                        var statusClass = ev.status === 'processado' ? 'healthy' : (ev.status === 'falhou' ? 'down' : 'degraded');
                        var html = '<div class="integ-domain-event d-flex align-items-start gap-3 py-2 border-bottom">'
                            + '<div class="flex-shrink-0 mt-1"><span class="integ-int-pulse integ-int-pulse--' + statusClass + '"></span></div>'
                            + '<div class="flex-fill">'
                            + '<div class="d-flex align-items-center gap-2 flex-wrap"><strong class="text-mono small">' + (ev.tipo || '') + '</strong>'
                            + (ev.origem ? '<span class="status-badge status-badge--info" style="font-size:.7rem">' + ev.origem + '</span>' : '')
                            + '<span class="status-badge status-badge--' + (ev.status === 'processado' ? 'success' : (ev.status === 'falhou' ? 'danger' : 'warning')) + '">' + ev.status + '</span></div>'
                            + '<p class="section-subtitle mb-0">' + (ev.payload_preview || '') + '</p>'
                            + '<small class="text-muted d-block">' + (ev.criado_em || '') + '</small>'
                            + '</div></div>';
                        feed.insertAdjacentHTML('afterbegin', html);
                    })
                    .catch(function () {
                        eventPublishBtn.disabled = false;
                        alert('Erro de rede ao publicar evento.');
                    });
            });
        }
    });
})();
