/**
 * recrutamento-hub — offcanvas dinâmicos (candidato, editar vaga)
 */
(function () {
    'use strict';

    function replaceIdInUrl(url, id) {
        return url.replace(/\/0(\/|$)/, '/' + id + '$1');
    }

    function initCandidatoForm(options) {
        options = options || {};
        var form = document.getElementById('recrutamentoCandidatoForm');
        var vagaLabel = document.getElementById('recrutamentoCandidatoVagaLabel');
        var tokenInput = document.getElementById('recrutamentoCandidatoToken');
        var submitLabel = document.getElementById('recrutamentoCandidatoSubmitLabel');
        var offcanvas = document.getElementById('recrutamento-candidato-form');
        if (!form) {
            return;
        }

        var actionTpl = form.getAttribute('data-recrutamento-candidato-action') || form.action;
        var editActionTpl = form.getAttribute('data-recrutamento-candidato-edit-action') || actionTpl;
        var redirectInput = document.getElementById('recrutamento-candidato-redirect');
        var nomeInput = document.getElementById('candidato-nome');
        var emailInput = document.getElementById('candidato-email');
        var telefoneInput = document.getElementById('candidato-telefone');
        var origemInput = document.getElementById('candidato-origem');
        var linkedinInput = document.getElementById('candidato-linkedin');

        function setCreateMode(vagaId, titulo, redirect) {
            if (vagaId) {
                form.action = replaceIdInUrl(actionTpl, vagaId);
            }
            if (tokenInput) {
                tokenInput.name = '_token';
                tokenInput.value = form.getAttribute('data-recrutamento-candidato-create-token')
                    || tokenInput.getAttribute('data-create-token')
                    || tokenInput.value;
            }
            if (redirectInput) {
                redirectInput.value = redirect || options.defaultRedirect || 'list';
            }
            form.reset();
            if (vagaLabel) {
                vagaLabel.textContent = titulo ? 'Vaga: ' + titulo : '';
                vagaLabel.hidden = false;
            }
            if (submitLabel) {
                submitLabel.textContent = 'Adicionar';
            }
            if (offcanvas) {
                var titleEl = offcanvas.querySelector('.unio-offcanvas-title');
                if (titleEl) {
                    titleEl.textContent = 'Adicionar candidato';
                }
            }
        }

        function setEditMode(candidatoId, redirect) {
            form.action = replaceIdInUrl(editActionTpl, candidatoId);
            if (redirectInput) {
                redirectInput.value = redirect || 'show';
            }
            if (vagaLabel) {
                vagaLabel.textContent = '';
                vagaLabel.hidden = true;
            }
            if (submitLabel) {
                submitLabel.textContent = 'Salvar';
            }
            if (offcanvas) {
                var titleEl = offcanvas.querySelector('.unio-offcanvas-title');
                if (titleEl) {
                    titleEl.textContent = 'Editar candidato';
                }
            }
        }

        document.querySelectorAll('[data-recrutamento-candidato-vaga]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                setCreateMode(
                    btn.getAttribute('data-recrutamento-candidato-vaga'),
                    btn.getAttribute('data-recrutamento-candidato-titulo') || '',
                    btn.getAttribute('data-recrutamento-candidato-redirect') || options.defaultRedirect || 'list',
                );
            });
        });

        document.querySelectorAll('[data-recrutamento-candidato-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-recrutamento-candidato-edit');
                if (!id) {
                    return;
                }
                setEditMode(id, btn.getAttribute('data-recrutamento-candidato-redirect') || 'show');
                if (tokenInput) {
                    tokenInput.value = btn.getAttribute('data-recrutamento-candidato-edit-token') || '';
                }
                if (nomeInput) {
                    nomeInput.value = btn.getAttribute('data-recrutamento-candidato-nome') || '';
                }
                if (emailInput) {
                    emailInput.value = btn.getAttribute('data-recrutamento-candidato-email') || '';
                }
                if (telefoneInput) {
                    telefoneInput.value = btn.getAttribute('data-recrutamento-candidato-telefone') || '';
                }
                if (origemInput) {
                    origemInput.value = btn.getAttribute('data-recrutamento-candidato-origem') || 'MANUAL';
                }
                if (linkedinInput) {
                    linkedinInput.value = btn.getAttribute('data-recrutamento-candidato-linkedin') || '';
                }
            });
        });
    }

    function initRejectOffcanvas() {
        var form = document.getElementById('recrutamentoCandidatoRejectForm');
        if (!form) {
            return;
        }

        var actionTpl = form.getAttribute('data-recrutamento-candidato-reject-action') || form.action;
        var tokenInput = document.getElementById('recrutamentoCandidatoRejectToken');
        var label = document.getElementById('recrutamentoCandidatoRejectLabel');
        var redirectInput = document.getElementById('recrutamento-candidato-reject-redirect');
        var vagaInput = document.getElementById('recrutamento-candidato-reject-vaga');
        var qInput = document.getElementById('recrutamento-candidato-reject-q');

        document.querySelectorAll('[data-recrutamento-candidato-reject]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-recrutamento-candidato-reject');
                if (!id) {
                    return;
                }
                form.action = replaceIdInUrl(actionTpl, id);
                if (tokenInput) {
                    tokenInput.value = btn.getAttribute('data-recrutamento-candidato-reject-token') || '';
                }
                if (redirectInput) {
                    redirectInput.value = btn.getAttribute('data-recrutamento-candidato-reject-redirect') || 'pipeline';
                }
                if (vagaInput) {
                    vagaInput.value = btn.getAttribute('data-recrutamento-candidato-reject-vaga') || '';
                }
                if (qInput) {
                    qInput.value = btn.getAttribute('data-recrutamento-candidato-reject-q') || '';
                }
                if (label) {
                    var nome = btn.getAttribute('data-recrutamento-candidato-reject-nome') || '';
                    label.textContent = nome ? 'Candidato: ' + nome : '';
                }
                form.reset();
                if (tokenInput) {
                    tokenInput.value = btn.getAttribute('data-recrutamento-candidato-reject-token') || '';
                }
                if (redirectInput) {
                    redirectInput.value = btn.getAttribute('data-recrutamento-candidato-reject-redirect') || 'pipeline';
                }
                if (vagaInput) {
                    vagaInput.value = btn.getAttribute('data-recrutamento-candidato-reject-vaga') || '';
                }
                if (qInput) {
                    qInput.value = btn.getAttribute('data-recrutamento-candidato-reject-q') || '';
                }
            });
        });
    }

    function initVagaEditForm() {
        var form = document.getElementById('recrutamentoVagaEditForm');
        var label = document.getElementById('recrutamentoVagaEditLabel');
        if (!form) {
            return;
        }

        var actionTpl = form.getAttribute('data-recrutamento-vaga-edit-action') || form.action;
        var tokenInput = form.querySelector('input[name="_token"]');
        var redirectInput = document.getElementById('recrutamento-vaga-edit-redirect');
        var redirectStatus = document.getElementById('recrutamento-vaga-edit-redirect-status');
        var redirectQ = document.getElementById('recrutamento-vaga-edit-redirect-q');

        document.querySelectorAll('[data-recrutamento-vaga-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.getAttribute('data-recrutamento-vaga-edit');
                if (!id) {
                    return;
                }
                form.action = replaceIdInUrl(actionTpl, id);
                if (tokenInput) {
                    tokenInput.value = btn.getAttribute('data-recrutamento-vaga-edit-token') || '';
                }
                if (redirectInput) {
                    redirectInput.value = btn.getAttribute('data-recrutamento-vaga-edit-redirect') || 'list';
                }
                if (redirectStatus) {
                    redirectStatus.value = btn.getAttribute('data-recrutamento-vaga-edit-filter-status') || '';
                }
                if (redirectQ) {
                    redirectQ.value = btn.getAttribute('data-recrutamento-vaga-edit-filter-q') || '';
                }

                var titulo = document.getElementById('edit-vaga-titulo');
                var departamento = document.getElementById('edit-vaga-departamento');
                var descricao = document.getElementById('edit-vaga-descricao');
                var requisitos = document.getElementById('edit-vaga-requisitos');
                var tipoContrato = document.getElementById('edit-vaga-tipo-contrato');
                var localTrabalho = document.getElementById('edit-vaga-local');
                var quantidade = document.getElementById('edit-vaga-quantidade');
                var status = document.getElementById('edit-vaga-status');
                if (titulo) titulo.value = btn.getAttribute('data-recrutamento-vaga-titulo') || '';
                if (departamento) departamento.value = btn.getAttribute('data-recrutamento-vaga-departamento') || '';
                if (descricao) descricao.value = btn.getAttribute('data-recrutamento-vaga-descricao') || '';
                if (requisitos) requisitos.value = btn.getAttribute('data-recrutamento-vaga-requisitos') || '';
                if (tipoContrato) tipoContrato.value = btn.getAttribute('data-recrutamento-vaga-tipo-contrato') || '';
                if (localTrabalho) localTrabalho.value = btn.getAttribute('data-recrutamento-vaga-local') || '';
                if (quantidade) quantidade.value = btn.getAttribute('data-recrutamento-vaga-quantidade') || '1';
                if (status) status.value = btn.getAttribute('data-recrutamento-vaga-status') || 'ABERTA';
                if (label) {
                    label.textContent = btn.getAttribute('data-recrutamento-vaga-titulo')
                        ? 'Editando: ' + btn.getAttribute('data-recrutamento-vaga-titulo')
                        : '';
                }
            });
        });
    }

    function initPipelineBoardImpl() {
        var board = document.querySelector('[data-recrutamento-pipeline-board]');
        if (!board || board.dataset.recrutamentoPipelineInit === '1') {
            return;
        }
        board.dataset.recrutamentoPipelineInit = '1';

        function columnCards(stage) {
            var col = board.querySelector('[data-recrutamento-pipeline-col="' + stage + '"]');
            return col ? col.querySelector('.kanban-column-cards') : null;
        }

        function updateColumnCount(stage) {
            var col = board.querySelector('[data-recrutamento-pipeline-col="' + stage + '"]');
            if (!col) {
                return;
            }
            var countEl = col.querySelector('.kanban-column-count');
            var total = col.querySelectorAll('[data-recrutamento-pipeline-card]').length;
            if (countEl) {
                countEl.textContent = String(total);
            }
        }

        function ensureEmptyState(cardsEl) {
            if (!cardsEl || cardsEl.querySelector('[data-recrutamento-pipeline-card]')) {
                return;
            }
            if (cardsEl.querySelector('.recrutamento-pipeline-empty')) {
                return;
            }
            var empty = document.createElement('p');
            empty.className = 'text-muted small mb-0 px-1 py-2 recrutamento-pipeline-empty';
            empty.textContent = 'Nenhum candidato';
            cardsEl.appendChild(empty);
        }

        function removeEmptyState(cardsEl) {
            if (!cardsEl) {
                return;
            }
            cardsEl.querySelectorAll('.recrutamento-pipeline-empty').forEach(function (el) {
                el.remove();
            });
        }

        function updateKpis(counts) {
            if (!counts) {
                return;
            }
            var funil = (counts.TRIAGEM || 0) + (counts.ENTREVISTA || 0)
                + (counts.PROPOSTA || 0) + (counts.CONTRATADO || 0);
            var map = Object.assign({ funil: funil }, counts);
            document.querySelectorAll('[data-recrutamento-pipeline-kpi]').forEach(function (el) {
                var key = el.getAttribute('data-recrutamento-pipeline-kpi');
                if (map[key] !== undefined) {
                    el.textContent = String(map[key]);
                }
            });
        }

        function bindPipelineForms(root) {
            (root || board).querySelectorAll('[data-recrutamento-pipeline-form]').forEach(function (form) {
                if (form.dataset.recrutamentoPipelineFormInit === '1') {
                    return;
                }
                form.dataset.recrutamentoPipelineFormInit = '1';
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var card = form.closest('[data-recrutamento-pipeline-card]');
                    var fromEtapa = card ? card.getAttribute('data-recrutamento-pipeline-etapa') : '';
                    var submitBtn = form.querySelector('[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                    }
                    if (card) {
                        card.classList.add('recrutamento-pipeline-card--busy');
                    }

                    fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                        },
                    })
                        .then(function (res) {
                            return res.json().then(function (data) {
                                if (!res.ok || !data.ok) {
                                    throw new Error((data && data.error) || 'Não foi possível mover o candidato.');
                                }
                                return data;
                            });
                        })
                        .then(function (data) {
                            if (card) {
                                card.remove();
                            }
                            var sourceStage = fromEtapa || data.from_etapa;
                            ensureEmptyState(columnCards(sourceStage));
                            updateColumnCount(sourceStage);

                            var targetCards = columnCards(data.to_etapa);
                            if (targetCards && data.card_html) {
                                removeEmptyState(targetCards);
                                var wrap = document.createElement('div');
                                wrap.innerHTML = data.card_html.trim();
                                var newCard = wrap.firstElementChild;
                                if (newCard) {
                                    targetCards.appendChild(newCard);
                                    bindPipelineForms(newCard);
                                }
                            }
                            updateColumnCount(data.to_etapa);
                            updateKpis(data.counts);

                            if (window.UnioToast && typeof window.UnioToast.show === 'function') {
                                window.UnioToast.show(data.message, 'success');
                            }
                        })
                        .catch(function (err) {
                            if (window.UnioToast && typeof window.UnioToast.show === 'function') {
                                window.UnioToast.show(err.message || 'Erro ao mover candidato.', 'error');
                            } else {
                                window.alert(err.message || 'Erro ao mover candidato.');
                            }
                        })
                        .finally(function () {
                            if (submitBtn) {
                                submitBtn.disabled = false;
                            }
                            if (card) {
                                card.classList.remove('recrutamento-pipeline-card--busy');
                            }
                        });
                });
            });
        }

        bindPipelineForms(board);
        initPipelineDragDrop(board, {
            columnCards: columnCards,
            updateColumnCount: updateColumnCount,
            updateKpis: updateKpis,
            ensureEmptyState: ensureEmptyState,
            removeEmptyState: removeEmptyState,
            bindPipelineForms: bindPipelineForms,
        });
    }

    function initPipelineDragDrop(board, helpers) {
        if (!board.hasAttribute('data-recrutamento-pipeline-dnd') || typeof Sortable === 'undefined') {
            return;
        }

        function revertDrag(evt) {
            var item = evt.item;
            var from = evt.from;
            var ref = from.children[evt.oldIndex] || null;
            if (ref === item) {
                return;
            }
            from.insertBefore(item, ref);
            helpers.ensureEmptyState(from);
            helpers.removeEmptyState(evt.to);
            var fromStage = stageFromColumn(from);
            var toStage = stageFromColumn(evt.to);
            if (fromStage) {
                helpers.updateColumnCount(fromStage);
            }
            if (toStage) {
                helpers.updateColumnCount(toStage);
            }
        }

        function stageFromColumn(cardsEl) {
            var col = cardsEl ? cardsEl.closest('[data-recrutamento-pipeline-col]') : null;
            return col ? col.getAttribute('data-recrutamento-pipeline-col') : '';
        }

        board.querySelectorAll('[data-recrutamento-pipeline-sort-col]').forEach(function (columnEl) {
            Sortable.create(columnEl, {
                group: 'recrutamento-pipeline',
                animation: 160,
                draggable: '.recrutamento-pipeline-card',
                handle: '.recrutamento-pipeline-card-drag-handle',
                ghostClass: 'dev-kanban-ghost',
                dragClass: 'dev-kanban-drag',
                filter: 'a, button, input, select, textarea, label, form',
                preventOnFilter: true,
                delay: 80,
                delayOnTouchOnly: true,
                touchStartThreshold: 3,
                swapThreshold: 0.65,
                onStart: function (evt) {
                    var width = evt.item.getBoundingClientRect().width;
                    if (width > 0) {
                        evt.item.style.width = width + 'px';
                    }
                },
                onEnd: function (evt) {
                    evt.item.style.width = '';
                    var card = evt.item;
                    var fromStage = stageFromColumn(evt.from);
                    var newStage = stageFromColumn(evt.to);

                    helpers.removeEmptyState(evt.to);

                    if (!newStage || fromStage === newStage) {
                        return;
                    }

                    var moveUrl = card.getAttribute('data-recrutamento-pipeline-move-url');
                    var token = card.getAttribute('data-recrutamento-pipeline-move-token');
                    if (!moveUrl || !token) {
                        revertDrag(evt);
                        return;
                    }

                    var body = new FormData();
                    body.append('_token', token);
                    body.append('etapa', newStage);
                    body.append('vaga', card.getAttribute('data-recrutamento-pipeline-filter-vaga') || '');
                    body.append('q', card.getAttribute('data-recrutamento-pipeline-filter-q') || '');

                    card.classList.add('recrutamento-pipeline-card--busy');

                    fetch(moveUrl, {
                        method: 'POST',
                        body: body,
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                        },
                    })
                        .then(function (res) {
                            return res.json().then(function (data) {
                                if (!res.ok || !data.ok) {
                                    throw new Error((data && data.error) || 'Não foi possível mover o candidato.');
                                }
                                return data;
                            });
                        })
                        .then(function (data) {
                            var wrap = document.createElement('div');
                            wrap.innerHTML = data.card_html.trim();
                            var newCard = wrap.firstElementChild;
                            if (newCard) {
                                card.replaceWith(newCard);
                                helpers.bindPipelineForms(newCard);
                            } else {
                                card.remove();
                            }
                            helpers.ensureEmptyState(evt.from);
                            helpers.updateColumnCount(fromStage);
                            helpers.updateColumnCount(newStage);
                            helpers.updateKpis(data.counts);

                            if (window.UnioToast && typeof window.UnioToast.show === 'function') {
                                window.UnioToast.show(data.message, 'success');
                            }
                        })
                        .catch(function (err) {
                            revertDrag(evt);
                            if (window.UnioToast && typeof window.UnioToast.show === 'function') {
                                window.UnioToast.show(err.message || 'Erro ao mover candidato.', 'error');
                            } else {
                                window.alert(err.message || 'Erro ao mover candidato.');
                            }
                        });
                },
            });
        });
    }

    function bindEntrevistaCopyButtons(root) {
        (root || document).querySelectorAll('.recrutamento-entrevista-copy').forEach(function (btn) {
            if (btn.dataset.boundCopy) {
                return;
            }
            btn.dataset.boundCopy = '1';
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-copy-text') || '';
                if (!text) {
                    return;
                }
                function showCopied() {
                    if (window.UnioToast && typeof window.UnioToast.show === 'function') {
                        window.UnioToast.show('Copiado.', 'success');
                    }
                }
                if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                    navigator.clipboard.writeText(text).then(showCopied).catch(function () {
                        window.prompt('Copiar:', text);
                    });
                    return;
                }
                window.prompt('Copiar:', text);
            });
        });
    }

    function initEntrevistaForm() {
        document.querySelectorAll('[data-recrutamento-entrevista-form]').forEach(function (form) {
            bindEntrevistaCopyButtons(form);

            form.querySelectorAll('[data-recrutamento-entrevista-tipo]').forEach(function (input) {
                input.addEventListener('change', function () {
                    var presencial = input.value === 'PRESENCIAL';
                    var label = form.querySelector('[data-recrutamento-entrevista-link-label]');
                    var hint = form.querySelector('[data-recrutamento-entrevista-link-hint]');
                    var linkInput = form.querySelector('[data-recrutamento-entrevista-link-input]');
                    var linkIcon = form.querySelector('[data-recrutamento-entrevista-link-icon]');
                    if (label) {
                        label.textContent = presencial ? 'Local / endereço' : 'Link da reunião';
                    }
                    if (hint) {
                        hint.textContent = presencial
                            ? 'Informe onde o candidato deve comparecer.'
                            : 'Google Meet, Teams, Zoom ou similar.';
                    }
                    if (linkInput) {
                        linkInput.placeholder = presencial
                            ? 'Sala, andar ou endereço completo'
                            : 'https://meet.google.com/...';
                    }
                    if (linkIcon) {
                        linkIcon.className = 'fas fa-' + (presencial ? 'location-dot' : 'link');
                    }
                });
            });
        });
    }

    function initCandidatoShowTabs() {
        var root = document.querySelector('[data-candidato-show-tabs]');
        if (!root) {
            return;
        }

        bindEntrevistaCopyButtons(root.closest('.recrutamento-candidato-show') || root);

        var tabs = root.querySelector('[data-candidato-tabs]');
        if (!tabs) {
            return;
        }

        var validTabs = ['geral', 'entrevista', 'historico'];
        var params = new URLSearchParams(window.location.search);
        var initial = params.get('tab') || 'geral';
        if (validTabs.indexOf(initial) === -1) {
            initial = 'geral';
        }
        if (initial === 'entrevista' && !tabs.querySelector('[data-candidato-tab="entrevista"]')) {
            initial = 'geral';
        }

        function activate(name) {
            tabs.querySelectorAll('[data-candidato-tab]').forEach(function (btn) {
                var active = btn.getAttribute('data-candidato-tab') === name;
                btn.classList.toggle('hub-overview-tab--active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            var pageRoot = root.closest('.recrutamento-candidato-show') || document;
            pageRoot.querySelectorAll('[data-candidato-panel]').forEach(function (panel) {
                var active = panel.getAttribute('data-candidato-panel') === name;
                panel.hidden = !active;
                panel.classList.toggle('hub-tab-panel--active', active);
            });

            var url = new URL(window.location.href);
            if (name === 'geral') {
                url.searchParams.delete('tab');
            } else {
                url.searchParams.set('tab', name);
            }
            window.history.replaceState({}, '', url);
        }

        tabs.querySelectorAll('[data-candidato-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                activate(btn.getAttribute('data-candidato-tab'));
            });
        });

        activate(initial);
    }

    function initCopyButtons() {
        document.querySelectorAll('.recrutamento-admin-copy-url, .recrutamento-copy-url').forEach(function (btn) {
            if (btn.dataset.recrutamentoCopyBound) return;
            btn.dataset.recrutamentoCopyBound = '1';
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-copy-text') || '';
                if (!text) return;
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () {
                        if (window.UnioToast) window.UnioToast.show('URL copiada.', 'success');
                    }).catch(function () {
                        window.prompt('Copiar URL:', text);
                    });
                } else {
                    window.prompt('Copiar URL:', text);
                }
            });
        });
    }

    window.RecrutamentoHub = {
        initCopyButtons: initCopyButtons,
        initCandidatoForm: initCandidatoForm,
        initCandidatoShowTabs: initCandidatoShowTabs,
        initEntrevistaForm: initEntrevistaForm,
        initVagaEditForm: initVagaEditForm,
        initRejectOffcanvas: initRejectOffcanvas,
        initPipelineBoard: function () {
            initRejectOffcanvas();
            initPipelineBoardImpl();
        },
        initVagaShow: function (opts) {
            opts = opts || {};
            initCandidatoForm({ defaultRedirect: opts.redirectTo || 'show' });
            initVagaEditForm();
            initRejectOffcanvas();
            if (opts.openCandidato && opts.vagaId) {
                var trigger = document.querySelector('[data-recrutamento-candidato-vaga="' + opts.vagaId + '"]');
                if (trigger) {
                    trigger.click();
                }
            }
        },
        initVagasList: function () {
            initCandidatoForm({ defaultRedirect: 'list' });
            initVagaEditForm();
            initRejectOffcanvas();
        },
    };
})();
