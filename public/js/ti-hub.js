/**
 * Hub TI — charts, chamados (wizard + filtros + kanban/lista)
 */
(function () {
    'use strict';

    function getPayload() {
        var el = document.getElementById('ti-hub-payload');
        if (!el) return null;
        try { return JSON.parse(el.textContent); } catch (e) { return null; }
    }

    function getCssVar(name) {
        return getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    }

    function initVolumeChart(payload) {
        if (typeof echarts === 'undefined') return;
        var el = document.getElementById('tiVolumeChart');
        if (!el || !payload || !payload.analytics_volume || !payload.analytics_volume.length) return;

        var chart = echarts.init(el, null, { renderer: 'svg' });
        var accent = getCssVar('--ti-accent') || '#06B6D4';
        var ok = '#10B981';
        var text3 = getCssVar('--text-3') || '#888';
        var border = getCssVar('--border') || '#e0e0e0';
        var data = payload.analytics_volume;

        chart.setOption({
            tooltip: { trigger: 'axis' },
            legend: { bottom: 0, textStyle: { color: text3, fontSize: 11 } },
            grid: { top: 16, left: 40, right: 16, bottom: 40 },
            xAxis: {
                type: 'category',
                data: data.map(function (d) { return d.month; }),
                axisLabel: { color: text3, fontSize: 11 },
                axisLine: { lineStyle: { color: border } },
            },
            yAxis: {
                type: 'value',
                axisLabel: { color: text3, fontSize: 11 },
                splitLine: { lineStyle: { color: border } },
            },
            series: [
                {
                    name: 'Abertos',
                    type: 'bar',
                    data: data.map(function (d) { return d.opened; }),
                    itemStyle: { color: accent, borderRadius: [4, 4, 0, 0] },
                    barWidth: '35%',
                },
                {
                    name: 'Resolvidos',
                    type: 'line',
                    smooth: true,
                    data: data.map(function (d) { return d.resolved; }),
                    lineStyle: { color: ok, width: 2 },
                    itemStyle: { color: ok },
                },
            ],
        });

        window.addEventListener('resize', function () { chart.resize(); });
    }

    function initChamadoWizard() {
        var root = document.querySelector('[data-ti-chamado-wizard]');
        var form = document.getElementById('tiChamadoForm');
        if (!root || !form) return;

        var steps = root.querySelectorAll('[data-ti-wizard-step]');
        var panels = root.querySelectorAll('[data-ti-wizard-panel]');
        var btnPrev = root.querySelector('[data-ti-wizard-prev]');
        var btnNext = root.querySelector('[data-ti-wizard-next]');
        var btnSubmit = root.querySelector('[data-ti-wizard-submit]');
        var progress = root.querySelector('[data-ti-wizard-progress]');
        var catalogInput = document.getElementById('tiCatalogItem');
        var selectedService = root.querySelector('[data-ti-selected-service]');
        var selectedLabel = root.querySelector('[data-ti-selected-service-label]');
        var prioritySelect = form.querySelector('[data-ti-priority-select]');
        var slaRulesEl = document.getElementById('ti-sla-rules-data');
        var slaRules = {};
        var formLabels = {};
        var currentStep = 1;
        var maxStep = panels.length || 4;
        var serviceChosen = false;

        if (slaRulesEl) {
            try { slaRules = JSON.parse(slaRulesEl.textContent); } catch (e) { slaRules = {}; }
        }
        var labelsEl = document.getElementById('ti-form-labels-data');
        if (labelsEl) {
            try { formLabels = JSON.parse(labelsEl.textContent); } catch (e) { formLabels = {}; }
        }

        function labelFor(field, value) {
            if (!value) return '—';
            var list = formLabels[field] || [];
            for (var i = 0; i < list.length; i++) {
                if (list[i].id === value) return list[i].label;
            }
            return value;
        }

        function fieldValue(name) {
            var el = form.querySelector('[name="' + name + '"]');
            if (!el) return '';
            if (el.type === 'radio') {
                var checked = form.querySelector('[name="' + name + '"]:checked');
                return checked ? checked.value : '';
            }
            if (el.type === 'checkbox') return el.checked ? 'Sim' : '';
            return (el.value || '').trim();
        }

        function updateReview() {
            var map = {
                title: fieldValue('title'),
                summary: fieldValue('summary'),
                category: labelFor('categories', fieldValue('category')),
                priority: fieldValue('priority'),
                impact: labelFor('impact_levels', fieldValue('impact')),
                location: labelFor('locations', fieldValue('location')),
                affected_users: fieldValue('affected_users'),
                contact_channel: labelFor('contact_channels', fieldValue('contact_channel')),
                contact_phone: fieldValue('contact_phone'),
                asset_tag: fieldValue('asset_tag'),
                preferred_time: fieldValue('preferred_time'),
            };
            Object.keys(map).forEach(function (key) {
                var out = root.querySelector('[data-ti-review-out="' + key + '"]');
                if (out) out.textContent = map[key] || '—';
                var row = root.querySelector('[data-ti-review-row="' + key + '"]');
                if (row) row.hidden = !map[key];
            });
        }

        function setStep(step) {
            currentStep = step;
            if (step === maxStep) updateReview();
            steps.forEach(function (el) {
                var n = parseInt(el.getAttribute('data-ti-wizard-step'), 10);
                el.classList.toggle('is-active', n === step);
                el.classList.toggle('is-done', n < step);
            });
            panels.forEach(function (panel) {
                var n = parseInt(panel.getAttribute('data-ti-wizard-panel'), 10);
                panel.hidden = n !== step;
                panel.classList.toggle('is-active', n === step);
            });
            if (progress) {
                var pct = Math.round((step / maxStep) * 100);
                progress.style.width = pct + '%';
                progress.setAttribute('aria-valuenow', String(pct));
            }
            if (btnPrev) btnPrev.hidden = step <= 1;
            if (btnNext) btnNext.hidden = step >= maxStep;
            if (btnSubmit) btnSubmit.hidden = step < maxStep;
        }

        function updateSlaPreview() {
            if (!prioritySelect) return;
            var p = prioritySelect.value;
            var rule = slaRules[p];
            var preview = root.querySelector('[data-ti-sla-preview]');
            if (!preview || !rule) return;
            var badge = preview.querySelector('.status-badge');
            if (badge) badge.textContent = p;
            var resp = preview.querySelector('[data-ti-sla-response]');
            var reso = preview.querySelector('[data-ti-sla-resolution]');
            var comp = preview.querySelector('[data-ti-sla-compliance]');
            if (resp) resp.textContent = rule.response || '—';
            if (reso) reso.textContent = rule.resolution || '—';
            if (comp) comp.textContent = (rule.compliance || '—') + '%';
            if (badge) {
                var variant = p === 'P1' ? 'danger' : (p === 'P2' ? 'warning' : 'secondary');
                badge.className = 'status-badge status-badge--' + variant + ' ti-badge ti-badge-priority ti-badge-priority--' + p.toLowerCase();
            }
        }

        function selectCatalog(btn) {
            root.querySelectorAll('[data-ti-catalog-pick], [data-ti-catalog-custom]').forEach(function (b) {
                b.classList.remove('is-selected');
            });
            btn.classList.add('is-selected');
            serviceChosen = true;

            var titleEl = form.querySelector('[name="title"]');
            var catEl = form.querySelector('[name="category"]');
            var priEl = form.querySelector('[name="priority"]');
            var summaryEl = form.querySelector('[name="summary"]');

            if (btn.hasAttribute('data-ti-catalog-custom')) {
                if (catalogInput) catalogInput.value = '';
                if (selectedService) selectedService.hidden = true;
                if (titleEl) titleEl.value = '';
                if (summaryEl) summaryEl.value = '';
            } else {
                var id = btn.getAttribute('data-ti-catalog-id') || '';
                var title = btn.getAttribute('data-ti-catalog-title') || '';
                var cat = btn.getAttribute('data-ti-catalog-cat') || 'sistema';
                var pri = btn.getAttribute('data-ti-catalog-priority') || 'P3';
                var desc = btn.getAttribute('data-ti-catalog-desc') || '';
                if (catalogInput) catalogInput.value = id;
                if (titleEl) titleEl.value = title;
                if (catEl) catEl.value = cat;
                if (priEl) priEl.value = pri;
                if (summaryEl && !summaryEl.value) summaryEl.value = desc;
                if (selectedService && selectedLabel) {
                    selectedService.hidden = false;
                    selectedLabel.textContent = title + ' · SLA ' + (btn.querySelector('.ti-chamado-catalog-sla')?.textContent?.replace('SLA ', '') || '');
                }
            }
            updateSlaPreview();
        }

        root.querySelectorAll('[data-ti-catalog-pick]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectCatalog(btn);
                setStep(2);
            });
        });

        var customBtn = root.querySelector('[data-ti-catalog-custom]');
        if (customBtn) {
            customBtn.addEventListener('click', function () {
                selectCatalog(customBtn);
                setStep(2);
            });
        }

        if (btnPrev) {
            btnPrev.addEventListener('click', function () {
                if (currentStep > 1) setStep(currentStep - 1);
            });
        }

        if (btnNext) {
            btnNext.addEventListener('click', function () {
                if (currentStep === 1 && !serviceChosen) {
                    window.alert('Selecione um serviço do catálogo ou "Outro assunto".');
                    return;
                }
                if (currentStep === 2 || currentStep === 3) {
                    if (!form.reportValidity()) return;
                }
                if (currentStep < maxStep) setStep(currentStep + 1);
            });
        }

        if (prioritySelect) {
            prioritySelect.addEventListener('change', updateSlaPreview);
        }

        root.closest('[data-unio-offcanvas]')?.addEventListener('transitionend', function () {
            /* reset ao fechar opcional — mantém dados se reabrir */
        });

        document.querySelector('[data-unio-offcanvas="ti-chamado-novo"]')?.querySelectorAll('[data-unio-offcanvas-close]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setTimeout(function () {
                    if (!document.querySelector('[data-unio-offcanvas="ti-chamado-novo"].is-open')) {
                        setStep(1);
                        serviceChosen = false;
                        form.reset();
                        if (catalogInput) catalogInput.value = '';
                        root.querySelectorAll('.is-selected').forEach(function (el) { el.classList.remove('is-selected'); });
                        if (selectedService) selectedService.hidden = true;
                    }
                }, 300);
            });
        });

        setStep(1);
        updateSlaPreview();
    }

    function initChamadosView() {
        var hub = document.getElementById('tiHub');
        if (!hub) return;

        var root = hub.querySelector('[data-ti-chamados-root]');
        if (!root) return;

        var toolbar = document.querySelector('.ti-chamados-toolbar-wrap');
        var btns = toolbar ? toolbar.querySelectorAll('[data-ti-view]') : [];
        var panels = root.querySelectorAll('[data-ti-view-panel]');
        if (!panels.length) return;

        var filterRoot = root.querySelector('[data-ti-ticket-filters]');

        function getInlineFilterControls() {
            if (!filterRoot) return null;
            return filterRoot.querySelector('.toolbar-inline-controls');
        }

        function getFilterElements() {
            var inline = getInlineFilterControls();
            if (!inline) {
                return { status: null, priority: null, category: null, search: null };
            }
            return {
                status: document.getElementById('ti-table-filter-status')
                    || inline.querySelector('[data-ti-filter="status"]:not([data-toolbar-mobile-clone])'),
                priority: document.getElementById('ti-table-filter-priority')
                    || inline.querySelector('[data-ti-filter="priority"]:not([data-toolbar-mobile-clone])'),
                category: document.getElementById('ti-table-filter-category')
                    || inline.querySelector('[data-ti-filter="category"]:not([data-toolbar-mobile-clone])'),
                search: inline.querySelector('[data-ti-filter-search]:not([data-toolbar-mobile-clone])'),
            };
        }

        function syncFilterSizerUi() {
            if (!window.UnioFilterSelect || typeof window.UnioFilterSelect.sync !== 'function') return;
            var els = getFilterElements();
            [els.status, els.priority, els.category].forEach(function (el) {
                if (el) window.UnioFilterSelect.sync(el);
            });
        }

        function getTableWrap() {
            return root.querySelector('[data-ti-ticket-table]');
        }

        function getBoard() {
            return root.querySelector('[data-ti-ticket-board]');
        }

        function getTableRows() {
            var table = getTableWrap();
            return table ? table.querySelectorAll('[data-ti-ticket-row]') : [];
        }

        function getKanbanCards() {
            var board = getBoard();
            return board ? board.querySelectorAll('[data-ti-ticket-card]') : [];
        }

        function readFilterValue(el) {
            if (!el) return '';
            return String(el.value ?? '').trim();
        }

        function getFilters() {
            var els = getFilterElements();
            return {
                status: readFilterValue(els.status),
                priority: readFilterValue(els.priority),
                category: readFilterValue(els.category),
                q: els.search ? els.search.value.trim().toLowerCase() : '',
            };
        }

        function matchesFilters(el) {
            var f = getFilters();
            if (f.status && (el.getAttribute('data-ti-status') || '') !== f.status) return false;
            if (f.priority && (el.getAttribute('data-ti-priority') || '') !== f.priority) return false;
            if (f.category && (el.getAttribute('data-ti-category') || '') !== f.category) return false;
            if (f.q && (el.getAttribute('data-ti-search') || '').indexOf(f.q) === -1) return false;
            return true;
        }

        function countVisibleRows() {
            var visible = 0;
            getTableRows().forEach(function (row) {
                if (!row.classList.contains('is-filter-hidden')) visible++;
            });
            return visible;
        }

        function updateFilteredSummary() {
            var table = getTableWrap();
            if (!table) return;

            var total = parseInt(table.getAttribute('data-ti-ticket-count') || '0', 10);
            var visible = countVisibleRows();
            var filtered = anyFilterActive();
            var subtitle = root.querySelector('.section-header--in-card .section-subtitle');
            var suffix = ' · P1→P4, depois data de abertura';

            if (subtitle) {
                if (filtered && visible !== total) {
                    subtitle.textContent = visible + ' de ' + total + ' registros' + suffix;
                } else {
                    subtitle.textContent = total + ' registros' + suffix;
                }
            }

            var emptyRow = table.querySelector('[data-ti-ticket-filter-empty]');
            if (emptyRow) {
                emptyRow.hidden = !(filtered && visible === 0 && total > 0);
            }
        }

        function clearFilters() {
            var els = getFilterElements();
            if (els.status) els.status.value = '';
            if (els.priority) els.priority.value = '';
            if (els.category) els.category.value = '';
            if (els.search) els.search.value = '';
            applyFilters();
        }

        function bindFilterControls() {
            var els = getFilterElements();
            [els.status, els.priority, els.category, els.search].forEach(function (el) {
                if (!el || el.dataset.tiFilterBound === '1') return;
                el.dataset.tiFilterBound = '1';
                el.addEventListener('change', applyFilters);
                if (el.hasAttribute('data-ti-filter-search')) {
                    el.addEventListener('input', applyFilters);
                }
            });
        }

        function anyFilterActive() {
            var f = getFilters();
            return !!(f.status || f.priority || f.category || f.q);
        }

        function setFilteredState(el, filtered) {
            el.classList.toggle('is-filter-hidden', filtered);
            el.removeAttribute('hidden');
        }

        function updateKanbanColEmpty() {
            var board = getBoard();
            if (!board) return;
            board.querySelectorAll('[data-ti-ticket-col]').forEach(function (col) {
                var cards = col.querySelectorAll('[data-ti-ticket-card]');
                var visible = 0;
                cards.forEach(function (card) {
                    if (!card.classList.contains('is-filter-hidden')) visible++;
                });
                var empty = col.querySelector('[data-ti-kanban-col-empty]');
                if (empty) empty.hidden = visible > 0;
            });
        }

        function showAllRows() {
            root.querySelectorAll('[data-ti-ticket-row], [data-ti-ticket-card]').forEach(function (el) {
                el.classList.remove('is-filter-hidden');
                el.removeAttribute('hidden');
            });
            updateKanbanColEmpty();
            updateFilteredSummary();
        }

        function applyFilters() {
            if (!anyFilterActive()) {
                showAllRows();
                syncFilterSizerUi();
                return;
            }

            getTableRows().forEach(function (row) {
                setFilteredState(row, !matchesFilters(row));
            });

            getKanbanCards().forEach(function (card) {
                setFilteredState(card, !matchesFilters(card));
            });
            updateKanbanColEmpty();
            updateFilteredSummary();
            syncFilterSizerUi();
        }

        function applyViewPanels(view) {
            if (btns.length) {
                btns.forEach(function (b) {
                    var active = b.getAttribute('data-ti-view') === view;
                    b.classList.toggle('is-active', active);
                    b.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
            }
            panels.forEach(function (panel) {
                var active = panel.getAttribute('data-ti-view-panel') === view;
                panel.hidden = !active;
                panel.classList.toggle('is-active', active);
            });
            try { sessionStorage.setItem('ti-chamados-view', view); } catch (e) { /* ignore */ }
        }

        function setView(view) {
            applyViewPanels(view);
            applyFilters();
        }

        function getSavedView() {
            try {
                return sessionStorage.getItem('ti-chamados-view') === 'kanban' ? 'kanban' : 'lista';
            } catch (e) {
                return 'lista';
            }
        }

        if (btns.length) {
            btns.forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    setView(btn.getAttribute('data-ti-view') || 'lista');
                });
            });
        }

        if (filterRoot) {
            bindFilterControls();
            document.addEventListener('unio-toolbar-mobile-synced', function (e) {
                if (!e.detail || !filterRoot.contains(e.detail.host)) return;
                applyFilters();
            });
            filterRoot.addEventListener('click', function (e) {
                if (e.target.closest('[data-ti-clear-filters]')) {
                    e.preventDefault();
                    clearFilters();
                }
            });
        }

        applyViewPanels(getSavedView());
        applyFilters();

        window.addEventListener('pageshow', function (event) {
            if (!event.persisted) return;
            applyViewPanels(getSavedView());
            applyFilters();
        });
    }

    function initTicketAssign() {
        function applyAssignResult(form, ticket) {
            if (!ticket) return;

            var row = form.closest('[data-ti-ticket-row]');
            if (row) {
                if (ticket.status) row.setAttribute('data-ti-status', ticket.status);
                if (ticket.assignee) {
                    var search = row.getAttribute('data-ti-search') || '';
                    row.setAttribute('data-ti-search', (search + ' ' + ticket.assignee).toLowerCase());
                }
                var statusBadge = row.querySelector('[data-ti-status-badge]');
                if (statusBadge && ticket.status_label) {
                    statusBadge.textContent = ticket.status_label;
                    if (ticket.status) {
                        var stVariant = ticket.status === 'resolvido' ? 'success' : (ticket.status === 'novo' ? 'info' : (ticket.status === 'aguardando' ? 'warning' : 'secondary'));
                        statusBadge.className = 'status-badge status-badge--' + stVariant + ' ti-badge ti-ticket-status-badge ti-ticket-status-badge--' + ticket.status;
                        statusBadge.setAttribute('data-ti-status-badge', ticket.status);
                    }
                }
            }

            var assigneeDisplay = document.querySelector('[data-ti-ticket-assignee-display]');
            if (assigneeDisplay && ticket.assignee) {
                assigneeDisplay.textContent = ticket.assignee;
            }
        }

        function submitAssign(form, triggerEl) {
            var select = form.querySelector('[name="technician_id"]');
            if (!select || !select.value) return;

            var formData = new FormData(form);
            formData.set('ajax', '1');
            var prevValue = select.value;
            select.disabled = true;
            if (triggerEl) triggerEl.disabled = true;

            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            })
                .then(function (res) {
                    return res.json().then(function (data) {
                        if (!res.ok || !data.ok) {
                            throw new Error((data && data.error) || 'Não foi possível atribuir o técnico.');
                        }
                        return data;
                    });
                })
                .then(function (data) {
                    applyAssignResult(form, data.ticket);
                })
                .catch(function (err) {
                    select.value = prevValue;
                    window.alert(err.message || 'Erro ao atribuir técnico.');
                })
                .finally(function () {
                    select.disabled = false;
                    if (triggerEl) triggerEl.disabled = false;
                });
        }

        document.querySelectorAll('[data-ti-ticket-assign]').forEach(function (form) {
            var select = form.querySelector('[name="technician_id"]');
            if (!select) return;

            if (form.querySelector('.ti-ticket-assign-select')) {
                select.addEventListener('change', function () {
                    if (select.value) submitAssign(form, null);
                });
            } else {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    submitAssign(form, form.querySelector('[type="submit"]'));
                });
            }
        });
    }

    function initPlaybookSteps() {
        document.querySelectorAll('[data-ti-playbook]').forEach(function (root) {
            var url = root.getAttribute('data-url');
            var csrf = root.getAttribute('data-csrf');
            if (!url || !csrf) return;

            root.querySelectorAll('[data-ti-playbook-step]').forEach(function (input) {
                input.addEventListener('change', function () {
                    var step = input.getAttribute('data-ti-playbook-step');
                    var row = input.closest('.ti-playbook-step, .wr-runbook-step, li');
                    if (row) row.classList.toggle('is-done', input.checked);

                    var body = new URLSearchParams();
                    body.set('_token', csrf);
                    body.set('step', step);
                    body.set('done', input.checked ? '1' : '0');

                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: body.toString(),
                    }).then(function (res) {
                        if (!res.ok) {
                            input.checked = !input.checked;
                            if (row) row.classList.toggle('is-done', input.checked);
                        }
                    }).catch(function () {
                        input.checked = !input.checked;
                        if (row) row.classList.toggle('is-done', input.checked);
                    });
                });
            });
        });
    }

    function initHeliaAssist() {
        var form = document.getElementById('tiChamadoForm');
        if (!form || !form.hasAttribute('data-ti-chamado-form')) return;

        var panel = document.querySelector('[data-ti-helia-assist]');
        var analyzeBtn = document.querySelector('[data-ti-helia-analyze]');
        var url = form.getAttribute('data-ti-helia-url');
        var csrf = form.getAttribute('data-ti-helia-csrf');
        var lastAnalysis = null;
        var debounceTimer;

        function impactLabel(id) {
            var map = { baixo: 'Baixo', medio: 'Médio', alto: 'Alto', critico: 'Crítico' };
            return map[id] || id;
        }

        function renderAnalysis(data) {
            if (!panel || !data) return;
            lastAnalysis = data;
            panel.hidden = false;
            var status = panel.querySelector('[data-ti-helia-status]');
            var summary = panel.querySelector('[data-ti-helia-summary]');
            var conf = panel.querySelector('[data-ti-helia-confidence]');
            var sug = panel.querySelector('[data-ti-helia-suggestions]');
            var kbWrap = panel.querySelector('[data-ti-helia-kb]');
            var kbList = panel.querySelector('[data-ti-helia-kb-list]');

            if (status) status.textContent = data.auto_triage_ready ? 'Triagem pronta para aplicar' : 'Descreva mais detalhes para maior precisão';
            if (summary) summary.textContent = data.summary || '';
            if (conf) {
                conf.hidden = false;
                conf.textContent = data.confidence + '%';
            }
            if (sug) {
                sug.hidden = false;
                ['category', 'priority', 'impact'].forEach(function (key) {
                    var btn = panel.querySelector('[data-ti-helia-apply="' + key + '"]');
                    var val = key === 'impact' ? data.suggested_impact : data['suggested_' + key];
                    var span = panel.querySelector('[data-ti-helia-val-' + key + ']');
                    if (btn && val) {
                        btn.hidden = false;
                        if (span) span.textContent = key === 'impact' ? impactLabel(val) : val;
                    }
                });
            }
            if (kbWrap && kbList && data.kb_articles && data.kb_articles.length) {
                kbWrap.hidden = false;
                kbList.innerHTML = data.kb_articles.map(function (kb) {
                    return '<li class="ti-helia-kb-item"><code>' + kb.id + '</code><span>' + kb.title + '</span><span class="ti-helia-kb-match">' + kb.match + '%</span></li>';
                }).join('');
            }

            var review = document.querySelector('[data-ti-helia-review]');
            if (review) {
                review.hidden = false;
                var rs = review.querySelector('[data-ti-helia-review-summary]');
                var rc = review.querySelector('[data-ti-helia-review-confidence]');
                if (rs) rs.textContent = data.summary || '';
                if (rc) rc.textContent = data.confidence + '% confiança';
            }
        }

        function runAnalyze() {
            if (!url) return;
            var title = form.querySelector('[name="title"]');
            var summary = form.querySelector('[name="summary"]');
            var category = form.querySelector('[name="category"]');
            if (!summary || !summary.value.trim()) return;

            if (panel) {
                panel.hidden = false;
                var status = panel.querySelector('[data-ti-helia-status]');
                if (status) status.textContent = 'Vitória analisando…';
            }

            var body = new FormData();
            body.append('_token', csrf || '');
            body.append('title', title ? title.value : '');
            body.append('summary', summary ? summary.value : '');
            body.append('category', category ? category.value : '');

            fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(renderAnalysis)
                .catch(function () {
                    if (panel) {
                        var status = panel.querySelector('[data-ti-helia-status]');
                        if (status) status.textContent = 'Não foi possível analisar agora.';
                    }
                });
        }

        if (analyzeBtn) {
            analyzeBtn.addEventListener('click', runAnalyze);
        }

        panel && panel.querySelectorAll('[data-ti-helia-apply]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.getAttribute('data-ti-helia-apply');
                if (!lastAnalysis) return;
                var val = key === 'impact' ? lastAnalysis.suggested_impact : lastAnalysis['suggested_' + key];
                var el = form.querySelector('[name="' + key + '"]');
                if (el && val) {
                    if (el.type === 'radio') {
                        var radio = form.querySelector('[name="' + key + '"][value="' + val + '"]');
                        if (radio) radio.checked = true;
                    } else {
                        el.value = val;
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });
        });

        ['title', 'summary'].forEach(function (name) {
            var el = form.querySelector('[name="' + name + '"]');
            if (!el) return;
            el.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    if (form.querySelector('[name="summary"]') && form.querySelector('[name="summary"]').value.trim().length >= 24) {
                        runAnalyze();
                    }
                }, 800);
            });
        });
    }

    function initChamadoAttachments() {
        var root = document.querySelector('[data-ti-chamado-attachments]');
        if (!root || root.dataset.tiAttachmentsInit) return;
        root.dataset.tiAttachmentsInit = '1';

        var input = root.querySelector('.ti-chamado-attachments-input');
        var drop = root.querySelector('.ti-chamado-attachments-drop');
        var list = root.querySelector('[data-ti-attachments-list]');
        var max = parseInt(root.getAttribute('data-ti-attachments-max') || '5', 10);
        var files = [];
        var objectUrls = [];

        function formatSize(bytes) {
            if (!bytes || bytes < 1024) return bytes + ' B';
            if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        function syncInput() {
            var dt = new DataTransfer();
            files.forEach(function (f) { dt.items.add(f); });
            input.files = dt.files;
        }

        function render() {
            objectUrls.forEach(function (u) { URL.revokeObjectURL(u); });
            objectUrls = [];
            if (!list) return;
            list.innerHTML = '';
            if (files.length === 0) {
                list.hidden = true;
                if (drop) drop.hidden = false;
                return;
            }
            list.hidden = false;
            files.forEach(function (file, idx) {
                var li = document.createElement('li');
                li.className = 'ti-chamado-attachments-item';
                var isImage = file.type && file.type.indexOf('image/') === 0;
                if (isImage) {
                    var url = URL.createObjectURL(file);
                    objectUrls.push(url);
                    var img = document.createElement('img');
                    img.src = url;
                    img.className = 'ti-chamado-attachments-item-thumb';
                    img.alt = '';
                    li.appendChild(img);
                } else {
                    var icon = document.createElement('span');
                    icon.className = 'ti-chamado-attachments-item-icon';
                    var iconClass = file.type === 'application/pdf' ? 'fa-file-pdf' : 'fa-file-lines';
                    icon.innerHTML = '<i class="fas ' + iconClass + '"></i>';
                    li.appendChild(icon);
                }
                var meta = document.createElement('div');
                meta.className = 'ti-chamado-attachments-item-meta';
                var nameEl = document.createElement('span');
                nameEl.className = 'ti-chamado-attachments-item-name';
                nameEl.textContent = file.name;
                var sizeEl = document.createElement('span');
                sizeEl.className = 'ti-chamado-attachments-item-size';
                sizeEl.textContent = formatSize(file.size);
                meta.appendChild(nameEl);
                meta.appendChild(sizeEl);
                li.appendChild(meta);
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'ti-chamado-attachments-item-remove';
                btn.setAttribute('aria-label', 'Remover ' + file.name);
                btn.innerHTML = '<i class="fas fa-xmark"></i>';
                btn.addEventListener('click', function () {
                    files.splice(idx, 1);
                    syncInput();
                    render();
                });
                li.appendChild(btn);
                list.appendChild(li);
            });
        }

        function addFiles(newFiles) {
            Array.from(newFiles || []).forEach(function (f) {
                if (files.length >= max) return;
                var dup = files.some(function (x) {
                    return x.name === f.name && x.size === f.size && x.lastModified === f.lastModified;
                });
                if (!dup) files.push(f);
            });
            syncInput();
            render();
        }

        if (input) {
            input.addEventListener('change', function () {
                if (input.files && input.files.length) addFiles(input.files);
            });
        }

        if (drop) {
            ['dragenter', 'dragover'].forEach(function (ev) {
                drop.addEventListener(ev, function (e) {
                    e.preventDefault();
                    drop.classList.add('unio-file-upload-drop--over');
                });
            });
            ['dragleave', 'drop'].forEach(function (ev) {
                drop.addEventListener(ev, function (e) {
                    e.preventDefault();
                    drop.classList.remove('unio-file-upload-drop--over');
                });
            });
            drop.addEventListener('drop', function (e) {
                var dropped = e.dataTransfer && e.dataTransfer.files;
                if (dropped && dropped.length) addFiles(dropped);
            });
        }

        var form = root.closest('form');
        if (form) {
            form.addEventListener('reset', function () {
                files = [];
                syncInput();
                render();
            });
        }
    }

    function initInfraCrud() {
        var meta = {
            ativo: { offcanvas: 'ti-ativo-form', formId: 'tiAtivoForm', titleNovo: 'Novo ativo', titleEdit: 'Editar ativo' },
            licenca: { offcanvas: 'ti-licenca-form', formId: 'tiLicencaForm', titleNovo: 'Nova licença', titleEdit: 'Editar licença' },
            integracao: { offcanvas: 'ti-integracao-form', formId: 'tiIntegracaoForm', titleNovo: 'Nova integração', titleEdit: 'Editar integração' },
            manutencao: { offcanvas: 'ti-manutencao-form', formId: 'tiManutencaoForm', titleNovo: 'Nova manutenção', titleEdit: 'Editar manutenção' },
            novidade: { offcanvas: 'ti-novidade-form', formId: 'tiNovidadeForm', titleNovo: 'Novo comunicado', titleEdit: 'Editar comunicado', labelNovo: 'Publicar' },
            kb: { offcanvas: 'ti-kb-form', formId: 'tiKbForm', titleNovo: 'Novo artigo KB', titleEdit: 'Editar artigo', labelNovo: 'Salvar artigo', labelEdit: 'Salvar alterações' },
            problema: { offcanvas: 'ti-problema-form', formId: 'tiProblemaForm', titleNovo: 'Novo problema', titleEdit: 'Editar problema', labelNovo: 'Registrar problema', labelEdit: 'Salvar alterações' },
        };

        function resetForm(resource) {
            var cfg = meta[resource];
            if (!cfg) return;
            var form = document.getElementById(cfg.formId);
            if (!form) return;
            form.reset();
            var novoAction = form.getAttribute('data-ti-novo-action') || form.action;
            if (!form.getAttribute('data-ti-novo-action')) {
                form.setAttribute('data-ti-novo-action', form.action);
            }
            form.action = novoAction;
            var drawer = document.querySelector('[data-unio-offcanvas="' + cfg.offcanvas + '"]');
            if (drawer) {
                var title = drawer.querySelector('.unio-offcanvas-title');
                if (title) title.textContent = cfg.titleNovo;
            }
            var label = document.querySelector('[data-ti-infra-submit-label="' + resource + '"]');
            if (label) label.textContent = cfg.labelNovo || 'Cadastrar';
        }

        function openEdit(resource, id, btn) {
            var cfg = meta[resource];
            if (!cfg) return;
            var form = document.getElementById(cfg.formId);
            if (!form) return;
            var editTpl = form.getAttribute('data-ti-edit-url') || '';
            if (editTpl) {
                form.action = editTpl.replace('/0/', '/' + id + '/').replace('/0/editar', '/' + id + '/editar');
            }
            Array.from(form.elements).forEach(function (el) {
                if (!el.name || el.name === '_token') return;
                var val = btn.getAttribute('data-ti-field-' + el.name);
                if (val !== null) {
                    el.value = val;
                }
            });
            var drawer = document.querySelector('[data-unio-offcanvas="' + cfg.offcanvas + '"]');
            if (drawer) {
                var title = drawer.querySelector('.unio-offcanvas-title');
                if (title) title.textContent = cfg.titleEdit;
            }
            var label = document.querySelector('[data-ti-infra-submit-label="' + resource + '"]');
            if (label) label.textContent = cfg.labelEdit || 'Salvar alterações';
            window.UnioOffcanvas && window.UnioOffcanvas.open(cfg.offcanvas);
        }

        document.querySelectorAll('[data-ti-infra-open-novo]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                resetForm(btn.getAttribute('data-ti-infra-open-novo'));
            });
        });

        document.querySelectorAll('[data-ti-infra-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openEdit(btn.getAttribute('data-ti-infra-edit'), btn.getAttribute('data-ti-infra-id'), btn);
            });
        });

        var reopen = document.getElementById('ti-infra-open-edit');
        if (reopen) {
            try {
                var payload = JSON.parse(reopen.textContent);
                if (payload.resource && payload.id) {
                    var editBtn = document.querySelector('[data-ti-infra-edit="' + payload.resource + '"][data-ti-infra-id="' + payload.id + '"]');
                    if (editBtn) {
                        editBtn.click();
                    } else {
                        window.UnioOffcanvas && window.UnioOffcanvas.open(meta[payload.resource].offcanvas);
                    }
                }
            } catch (e) { /* ignore */ }
        }
    }

    function initTicketRowMenus() {
        if (typeof jQuery === 'undefined') return;

        document.querySelectorAll('[data-ti-ticket-row-menu]').forEach(function (wrap) {
            var btn = wrap.querySelector('[data-toggle="dropdown"]');
            var menu = wrap.querySelector('.dropdown-menu');
            if (!btn || !menu) return;

            function placeMenu() {
                var rect = btn.getBoundingClientRect();
                var mw = menu.offsetWidth || 168;
                var mh = menu.offsetHeight || 140;
                var top = rect.bottom + 4;
                var left = Math.max(8, rect.right - mw);
                if (top + mh > window.innerHeight - 8) {
                    top = Math.max(8, rect.top - mh - 4);
                }
                menu.style.position = 'fixed';
                menu.style.top = top + 'px';
                menu.style.left = left + 'px';
                menu.style.right = 'auto';
                menu.style.bottom = 'auto';
                menu.style.transform = 'none';
                menu.style.zIndex = '1065';
            }

            function resetMenu() {
                menu.style.position = '';
                menu.style.top = '';
                menu.style.left = '';
                menu.style.right = '';
                menu.style.bottom = '';
                menu.style.transform = '';
                menu.style.zIndex = '';
            }

            jQuery(btn).on('shown.bs.dropdown', placeMenu);
            jQuery(btn).on('hidden.bs.dropdown', resetMenu);

            var reposition = function () {
                if (menu.classList.contains('show')) placeMenu();
            };
            window.addEventListener('scroll', reposition, true);
            window.addEventListener('resize', reposition);
        });
    }

    function initKanbanDragDrop() {
        var board = document.querySelector('[data-ti-chamados-root] [data-ti-ticket-board]');
        if (!board) return;

        var dragCard = null;

        function refreshColCounts() {
            board.querySelectorAll('[data-ti-ticket-col]').forEach(function (col) {
                var cards = col.querySelectorAll('[data-ti-ticket-card]:not(.is-filter-hidden)');
                var countEl = col.querySelector('.kanban-column-count');
                if (countEl) countEl.textContent = String(cards.length);
                var empty = col.querySelector('[data-ti-kanban-col-empty]');
                if (empty) empty.hidden = cards.length > 0;
            });
        }

        board.querySelectorAll('[data-ti-ticket-card][draggable="true"]').forEach(function (card) {
            card.addEventListener('dragstart', function (e) {
                dragCard = card;
                card.classList.add('is-dragging');
                if (e.dataTransfer) {
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', card.getAttribute('data-ticket-id') || '');
                }
            });
            card.addEventListener('dragend', function () {
                card.classList.remove('is-dragging');
                board.querySelectorAll('.kanban-column.is-drop-target').forEach(function (c) {
                    c.classList.remove('is-drop-target');
                });
                dragCard = null;
            });
        });

        board.querySelectorAll('[data-ti-ticket-col]').forEach(function (colWrap) {
            var col = colWrap.closest('.kanban-column') || colWrap;
            colWrap.addEventListener('dragover', function (e) {
                e.preventDefault();
                if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
                col.classList.add('is-drop-target');
            });
            colWrap.addEventListener('dragleave', function (e) {
                if (col.contains(e.relatedTarget)) return;
                col.classList.remove('is-drop-target');
            });
            colWrap.addEventListener('drop', function (e) {
                e.preventDefault();
                col.classList.remove('is-drop-target');
                if (!dragCard) return;

                var newStatus = colWrap.getAttribute('data-ti-ticket-col');
                var oldStatus = dragCard.getAttribute('data-ti-status');
                if (!newStatus || newStatus === oldStatus) return;

                var url = dragCard.getAttribute('data-ti-status-url');
                var token = dragCard.getAttribute('data-ti-status-token');
                if (!url || !token) return;

                var cardsContainer = colWrap.querySelector('.kanban-column-cards');
                if (!cardsContainer) return;

                var body = new FormData();
                body.append('_token', token);
                body.append('status', newStatus);
                body.append('ajax', '1');

                dragCard.classList.add('is-saving');
                fetch(url, {
                    method: 'POST',
                    body: body,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                    credentials: 'same-origin',
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        dragCard.classList.remove('is-saving');
                        if (!data || !data.ok) {
                            window.alert((data && data.error) || 'Não foi possível mover o chamado.');
                            return;
                        }
                        cardsContainer.appendChild(dragCard);
                        dragCard.setAttribute('data-ti-status', newStatus);
                        refreshColCounts();
                    })
                    .catch(function () {
                        dragCard.classList.remove('is-saving');
                        window.alert('Erro de rede ao atualizar status.');
                    });
            });
        });
    }

    function initChamadoConvTabs(panel) {
        var root = panel ? panel.querySelector('[data-ti-conv-tabs]') : null;
        if (!root) return null;

        var ticketId = panel.getAttribute('data-ticket-id') || 'default';
        var storageKey = 'ti-chat-tab-' + ticketId;
        var tabs = root.querySelectorAll('[data-ti-conv-tab]');
        var panes = root.querySelectorAll('[data-ti-conv-pane]');

        function scrollThreadToBottom() {
            var thread = root.querySelector('[data-ti-conv-pane="mensagens"] .helix-messages, [data-ti-conv-pane="mensagens"] .ti-chat-thread');
            if (thread) thread.scrollTop = thread.scrollHeight;
        }

        function activate(tabId) {
            if (!root.querySelector('[data-ti-conv-tab="' + tabId + '"]')) {
                tabId = 'mensagens';
            }

            tabs.forEach(function (tab) {
                var on = tab.getAttribute('data-ti-conv-tab') === tabId;
                tab.classList.toggle('is-active', on);
                tab.setAttribute('aria-selected', on ? 'true' : 'false');
            });

            panes.forEach(function (pane) {
                var on = pane.getAttribute('data-ti-conv-pane') === tabId;
                pane.hidden = !on;
                pane.classList.toggle('is-active', on);
            });

            try { sessionStorage.setItem(storageKey, tabId); } catch (e) { /* ignore */ }

            if (tabId === 'mensagens') {
                scrollThreadToBottom();
                var composer = panel.querySelector('[data-ti-chamado-message] .helix-input');
                if (composer && panel.classList.contains('ti-chamado-conversation--float') && !panel.classList.contains('ti-chamado-conversation--minimized')) {
                    composer.focus();
                }
            }
            if (tabId === 'responder') {
                var textarea = root.querySelector('[data-ti-conv-pane="responder"] select, [data-ti-conv-pane="responder"] .unio-form-control');
                if (textarea) textarea.focus();
            }
        }

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                activate(tab.getAttribute('data-ti-conv-tab'));
            });
        });

        return {
            activate: activate,
            storageKey: storageKey,
        };
    }

    function initChamadoChatFloat() {
        var panel = document.querySelector('[data-ti-chat-panel]');
        var slot = document.querySelector('[data-ti-chat-slot]');
        if (!panel || !slot) return;

        var ticketId = panel.getAttribute('data-ticket-id') || 'default';
        var storageKey = 'ti-chat-float-' + ticketId;
        var placeholder = slot.querySelector('[data-ti-chat-placeholder]');
        var docked = panel.querySelector('[data-ti-chat-docked]');
        var floatLayout = panel.querySelector('[data-ti-chat-float-layout]');
        var convTabs = initChamadoConvTabs(panel);
        var floating = false;
        var minimized = false;
        var minStorageKey = storageKey + '-min';
        var restoreBtn = panel.querySelector('[data-ti-chat-restore]');

        function getComposerInput() {
            return panel.querySelector('[data-ti-chamado-message] .helix-input');
        }

        function scrollThreadToBottom() {
            var scope = floating ? floatLayout : docked;
            var thread = scope ? scope.querySelector('.helix-messages, .ti-chat-thread') : null;
            if (!thread) return;
            var scrollHost = thread.closest('[data-ti-chat-box-scroll]');
            if (scrollHost) scrollHost.scrollTop = scrollHost.scrollHeight;
            else thread.scrollTop = thread.scrollHeight;
        }

        function setMinimized(on) {
            minimized = !!on && floating;
            panel.classList.toggle('ti-chamado-conversation--minimized', minimized);
            if (restoreBtn) restoreBtn.hidden = !minimized;
            try { sessionStorage.setItem(minStorageKey, minimized ? '1' : '0'); } catch (e) { /* ignore */ }
            if (window.TiChamadoChat && window.TiChamadoChat.syncSession) {
                window.TiChamadoChat.syncSession({ minimized: minimized, float: floating });
            }
            if (window.TiChamadoChat && window.TiChamadoChat.syncGlobalLauncher) {
                window.TiChamadoChat.syncGlobalLauncher();
            }
        }

        function updateToggleUi(isFloat) {
            panel.querySelectorAll('[data-ti-chat-float-toggle]').forEach(function (btn) {
                btn.setAttribute('aria-pressed', isFloat ? 'true' : 'false');
                btn.setAttribute('aria-label', isFloat ? 'Voltar conversa ao painel' : 'Fixar conversa no canto da tela');
                btn.setAttribute('title', isFloat ? 'Voltar ao painel' : 'Fixar no canto da tela');
                var icon = btn.querySelector('i.fas');
                if (icon && !btn.textContent.trim()) {
                    icon.className = isFloat ? 'fas fa-compress-arrows-alt' : 'fas fa-external-link-alt';
                }
            });
        }

        function setFloating(on) {
            floating = !!on;
            panel.classList.toggle('ti-chamado-conversation--float', floating);
            updateToggleUi(floating);

            if (!floating) setMinimized(false);

            if (docked) docked.hidden = floating;
            if (floatLayout) floatLayout.hidden = !floating;

            if (floating) {
                document.body.appendChild(panel);
                if (placeholder) placeholder.hidden = minimized;
                if (convTabs) {
                    var savedTab = null;
                    try { savedTab = sessionStorage.getItem(convTabs.storageKey); } catch (e) { /* ignore */ }
                    convTabs.activate(savedTab || 'mensagens');
                }
                var wasMin = false;
                try { wasMin = sessionStorage.getItem(minStorageKey) === '1'; } catch (e) { /* ignore */ }
                setMinimized(wasMin);
            } else {
                slot.appendChild(panel);
                if (placeholder) placeholder.hidden = true;
            }

            try { sessionStorage.setItem(storageKey, floating ? '1' : '0'); } catch (e) { /* ignore */ }
            if (window.TiChamadoChat && window.TiChamadoChat.syncSession) {
                window.TiChamadoChat.syncSession({ float: floating, minimized: minimized });
            } else if (!floating && window.TiChamadoChat && window.TiChamadoChat.clearSession) {
                window.TiChamadoChat.clearSession();
            }
            if (window.TiChamadoChat && window.TiChamadoChat.syncGlobalLauncher) {
                window.TiChamadoChat.syncGlobalLauncher();
            }
            scrollThreadToBottom();
        }

        document.addEventListener('ti-chat-restore-request', function () {
            if (!floating) setFloating(true);
            setMinimized(false);
            if (placeholder) placeholder.hidden = false;
            scrollThreadToBottom();
            var input = getComposerInput();
            if (input) input.focus();
            if (window.TiChamadoChat && window.TiChamadoChat.syncSession) {
                window.TiChamadoChat.syncSession({ minimized: false, float: true, unread: 0 });
            }
            if (window.TiChamadoChat && window.TiChamadoChat.syncGlobalLauncher) {
                window.TiChamadoChat.syncGlobalLauncher();
            }
        });

        document.addEventListener('click', function (ev) {
            var minBtn = ev.target.closest('[data-ti-chat-minimize]');
            if (minBtn && panel.contains(minBtn) && floating) {
                setMinimized(true);
                if (placeholder) placeholder.hidden = true;
                return;
            }
            var restore = ev.target.closest('[data-ti-chat-restore]');
            if (restore && panel.contains(restore) && floating) {
                setMinimized(false);
                if (placeholder) placeholder.hidden = false;
                scrollThreadToBottom();
                var input = getComposerInput();
                if (input) input.focus();
                return;
            }
            var btn = ev.target.closest('[data-ti-chat-float-toggle]');
            if (!btn || !panel.contains(btn) && !(placeholder && placeholder.contains(btn))) return;
            setFloating(!floating);
        });

        try {
            if (sessionStorage.getItem(storageKey) === '1') setFloating(true);
            var chatParam = new URLSearchParams(window.location.search).get('chat');
            if (chatParam === 'restore' || chatParam === 'open') {
                setFloating(true);
                setMinimized(false);
                if (placeholder) placeholder.hidden = false;
            }
        } catch (e) { /* ignore */ }
    }

    function boot() {
        var payload = getPayload();
        initChamadoWizard();
        initChamadoAttachments();
        initHeliaAssist();
        initChamadosView();
        initKanbanDragDrop();
        initTicketAssign();
        initTicketRowMenus();
        initPlaybookSteps();
        initInfraCrud();
        initChamadoChatFloat();
        if (payload && payload.section === 'analytics') {
            initVolumeChart(payload);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
