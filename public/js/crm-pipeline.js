/**
 * CRM Pipeline — drag-and-drop (SortableJS)
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function initBoard(attempt) {
        var board = document.querySelector('[data-crm-pipeline-board]');
        if (!board || board.dataset.crmPipelineInit === '1') {
            return;
        }

        if (typeof Sortable === 'undefined') {
            if ((attempt || 0) < 40) {
                window.setTimeout(function () {
                    initBoard((attempt || 0) + 1);
                }, 50);
            }
            return;
        }

        board.dataset.crmPipelineInit = '1';

        function columnEls() {
            return board.querySelectorAll('[data-crm-pipeline-col]');
        }

        function updateColumnCount(stage) {
            var col = board.querySelector('[data-crm-pipeline-col="' + stage + '"]');
            if (!col) return;
            var countEl = col.querySelector('.kanban-column-count');
            var total = col.querySelectorAll('[data-crm-pipeline-card]').length;
            if (countEl) countEl.textContent = String(total);
        }

        function ensureEmpty(container) {
            if (!container || container.querySelector('[data-crm-pipeline-card]')) return;
            if (container.querySelector('.crm-pipeline__empty')) return;
            var empty = document.createElement('div');
            empty.className = 'crm-pipeline__empty';
            empty.setAttribute('data-crm-pipeline-empty', '1');
            empty.innerHTML = '<i class="fas fa-inbox" aria-hidden="true"></i><p>Vazio</p>';
            container.appendChild(empty);
        }

        function removeEmpty(container) {
            if (!container) return;
            container.querySelectorAll('.crm-pipeline__empty').forEach(function (el) {
                el.remove();
            });
        }

        function clearAllEmptyPlaceholders() {
            board.querySelectorAll('.crm-pipeline__empty').forEach(function (el) {
                el.remove();
            });
        }

        function stageFromContainer(el) {
            var col = el ? el.closest('[data-crm-pipeline-col]') : null;
            return col ? col.getAttribute('data-crm-pipeline-col') : '';
        }

        function revertDrag(evt) {
            var item = evt.item;
            var from = evt.from;
            var ref = from.children[evt.oldIndex] || null;
            if (ref !== item) {
                from.insertBefore(item, ref);
            }
            ensureEmpty(from);
            removeEmpty(evt.to);
            columnEls().forEach(function (col) {
                var sortEl = col.querySelector('[data-crm-pipeline-sort-col]');
                ensureEmpty(sortEl);
            });
            updateColumnCount(stageFromContainer(from));
            updateColumnCount(stageFromContainer(evt.to));
            refreshSummary();
        }

        function refreshSummary() {
            var openStages = ['lead', 'qualificacao', 'proposta', 'negociacao'];
            var openCount = 0;
            var openValue = 0;
            openStages.forEach(function (stage) {
                board.querySelectorAll('[data-crm-pipeline-col="' + stage + '"] [data-crm-pipeline-card]').forEach(function (card) {
                    openCount += 1;
                    openValue += parseFloat(card.getAttribute('data-crm-valor') || '0') || 0;
                });
            });
            var won = board.querySelectorAll('[data-crm-pipeline-col="ganho"] [data-crm-pipeline-card]').length;
            var lost = board.querySelectorAll('[data-crm-pipeline-col="perdido"] [data-crm-pipeline-card]').length;

            var root = board.closest('.crm-pipeline');
            var elOpen = root && root.querySelector('[data-crm-summary-open]');
            var elValue = root && root.querySelector('[data-crm-summary-value]');
            var elWon = root && root.querySelector('[data-crm-summary-won]');
            var elLost = root && root.querySelector('[data-crm-summary-lost]');

            if (elOpen) elOpen.textContent = String(openCount);
            if (elWon) elWon.textContent = String(won);
            if (elLost) elLost.textContent = String(lost);
            if (elValue) {
                elValue.textContent = 'R$ ' + openValue.toLocaleString('pt-BR', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });
            }

            columnEls().forEach(function (col) {
                var stage = col.getAttribute('data-crm-pipeline-col');
                updateColumnCount(stage);
                var sum = 0;
                col.querySelectorAll('[data-crm-pipeline-card]').forEach(function (card) {
                    sum += parseFloat(card.getAttribute('data-crm-valor') || '0') || 0;
                });
                var sumEl = col.querySelector('[data-crm-col-sum]');
                if (sumEl) {
                    if (sum > 0) {
                        sumEl.hidden = false;
                        sumEl.textContent = 'R$ ' + Math.round(sum).toLocaleString('pt-BR');
                    } else {
                        sumEl.hidden = true;
                    }
                }
            });
        }

        function applyStageToCard(card, stage) {
            card.setAttribute('data-crm-stage', stage);
            var select = card.querySelector('select[name="estagio"]');
            if (select) {
                select.value = stage;
                Array.prototype.forEach.call(select.options, function (opt) {
                    var label = opt.value === stage
                        ? 'Estágio atual'
                        : ('→ ' + (opt.getAttribute('data-label') || opt.value));
                    opt.textContent = label;
                });
            }
            var prob = card.querySelector('.crm-pipeline__card-prob');
            var bar = card.querySelector('.crm-pipeline__prob-bar > span');
            if (stage === 'ganho') {
                card.setAttribute('data-crm-prob', '100');
                if (prob) prob.textContent = '100%';
                if (bar) bar.style.width = '100%';
            } else if (stage === 'perdido') {
                card.setAttribute('data-crm-prob', '0');
                if (prob) prob.textContent = '0%';
                if (bar) bar.style.width = '0%';
            }
        }

        function toast(message, type) {
            if (window.UnioToast && typeof window.UnioToast.show === 'function') {
                window.UnioToast.show(message, type || 'success');
            }
        }

        function isInteractiveFilter(evt, target) {
            // O handle de arraste NÃO pode ser filtrado (antes era <button> e o Sortable
            // bloqueava o drag por causa de filter: 'button').
            if (target.closest && target.closest('.crm-pipeline__drag-handle')) {
                return false;
            }
            return !!(target.closest && target.closest(
                'a, button, input, select, textarea, label, form, .crm-pipeline__move, .crm-pipeline__card-open'
            ));
        }

        columnEls().forEach(function (col) {
            var sortEl = col.querySelector('[data-crm-pipeline-sort-col]');
            if (!sortEl) return;

            Sortable.create(sortEl, {
                group: 'crm-pipeline',
                animation: 160,
                draggable: '[data-crm-pipeline-card]',
                handle: '.crm-pipeline__drag-handle',
                ghostClass: 'crm-pipeline__card--ghost',
                dragClass: 'crm-pipeline__card--drag',
                chosenClass: 'crm-pipeline__card--chosen',
                filter: isInteractiveFilter,
                preventOnFilter: true,
                delay: 0,
                delayOnTouchOnly: true,
                touchStartThreshold: 3,
                forceFallback: true,
                fallbackOnBody: true,
                fallbackTolerance: 3,
                swapThreshold: 0.65,
                emptyInsertThreshold: 48,
                onStart: function (evt) {
                    var width = evt.item.getBoundingClientRect().width;
                    if (width > 0) evt.item.style.width = width + 'px';
                    // Placeholders "Vazio" atrapalham o insert em colunas vazias.
                    clearAllEmptyPlaceholders();
                    board.classList.add('is-dragging');
                },
                onEnd: function (evt) {
                    evt.item.style.width = '';
                    board.classList.remove('is-dragging');

                    var card = evt.item;
                    var fromStage = stageFromContainer(evt.from);
                    var newStage = stageFromContainer(evt.to);

                    removeEmpty(evt.to);
                    columnEls().forEach(function (c) {
                        ensureEmpty(c.querySelector('[data-crm-pipeline-sort-col]'));
                    });
                    updateColumnCount(fromStage);
                    updateColumnCount(newStage);

                    if (!newStage || fromStage === newStage) {
                        refreshSummary();
                        return;
                    }

                    var moveUrl = card.getAttribute('data-crm-move-url');
                    var token = card.getAttribute('data-crm-move-token');
                    if (!moveUrl || !token) {
                        revertDrag(evt);
                        toast('Não foi possível mover: token ausente.', 'error');
                        return;
                    }

                    var body = new FormData();
                    body.append('_token', token);
                    body.append('estagio', newStage);

                    card.classList.add('crm-pipeline__card--busy');

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
                                    throw new Error((data && data.error) || 'Não foi possível mover a oportunidade.');
                                }
                                return data;
                            }).catch(function (parseErr) {
                                if (parseErr instanceof Error && parseErr.message && parseErr.message.indexOf('mover') !== -1) {
                                    throw parseErr;
                                }
                                throw new Error(res.status === 403
                                    ? 'Sem permissão para mover.'
                                    : 'Resposta inválida do servidor.');
                            });
                        })
                        .then(function (data) {
                            applyStageToCard(card, data.to_estagio || newStage);
                            refreshSummary();
                            toast(data.message || 'Estágio atualizado.', 'success');
                        })
                        .catch(function (err) {
                            revertDrag(evt);
                            toast(err.message || 'Erro ao mover oportunidade.', 'error');
                        })
                        .finally(function () {
                            card.classList.remove('crm-pipeline__card--busy');
                        });
                },
            });
        });
    }

    ready(function () {
        initBoard(0);
    });
})();
