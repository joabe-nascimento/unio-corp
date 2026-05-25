/**
 * Projetos e Metas — abas (hub) + kanban SortableJS
 */
(function () {
    'use strict';

    var initialized = new WeakSet();

    function taskUrl(template, taskId) {
        if (!template) {
            return '';
        }
        var id = String(taskId);
        return template.replace(/\/tarefas\/0(\/|$)/g, '/tarefas/' + id + '$1').replace(/\/0(\/|$)/g, '/' + id + '$1');
    }

    function moveUrl(template, taskId) {
        return taskUrl(template, taskId);
    }

    function initBoard(board) {
        if (!board || initialized.has(board)) {
            return;
        }
        if (typeof Sortable === 'undefined') {
            return;
        }

        var template = board.dataset.moveUrlTemplate;
        var csrf = board.dataset.csrf;
        if (!template || !csrf) {
            return;
        }

        var projetoFilter = board.dataset.projetoFilter || '';
        var redirect = board.dataset.redirect || '';

        board.querySelectorAll('[data-kanban-column]').forEach(function (columnEl) {
            Sortable.create(columnEl, {
                group: 'dev-kanban',
                animation: 160,
                draggable: '.dev-kanban-card',
                ghostClass: 'dev-kanban-ghost',
                dragClass: 'dev-kanban-drag',
                filter: 'a, button, input, select, textarea, label, .kanban-card-menu, .dropdown-menu',
                preventOnFilter: true,
                delay: 80,
                delayOnTouchOnly: true,
                touchStartThreshold: 3,
                forceFallback: true,
                fallbackOnBody: true,
                swapThreshold: 0.65,
                onEnd: function (evt) {
                    var card = evt.item;
                    var taskId = card.dataset.taskId;
                    var fromStatus = evt.from.dataset.status;
                    var newStatus = evt.to.dataset.status;
                    var newIndex = evt.newIndex;

                    if (!taskId || !newStatus) {
                        return;
                    }

                    if (fromStatus === newStatus && evt.oldIndex === newIndex) {
                        return;
                    }

                    var body = new FormData();
                    body.append('_token', csrf);
                    body.append('status', newStatus);
                    body.append('ordem', String(newIndex));
                    body.append('view', 'kanban');
                    if (projetoFilter) {
                        body.append('projeto_filter', projetoFilter);
                    }
                    if (redirect) {
                        body.append('redirect', redirect);
                    }

                    card.classList.add('dev-kanban-card--saving');

                    fetch(moveUrl(template, taskId), {
                        method: 'POST',
                        body: body,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'application/json',
                        },
                        credentials: 'same-origin',
                    })
                        .then(function (res) {
                            var ct = res.headers.get('content-type') || '';
                            if (!res.ok || ct.indexOf('json') === -1) {
                                throw new Error('move_failed_' + res.status);
                            }
                            return res.json();
                        })
                        .then(function (data) {
                            if (!data || !data.ok) {
                                throw new Error('move_rejected');
                            }
                            card.classList.remove('dev-kanban-card--saving');
                            board.querySelectorAll('[data-kanban-count]').forEach(function (badge) {
                                var st = badge.dataset.kanbanCount;
                                var col = board.querySelector('[data-kanban-column="' + st + '"]');
                                badge.textContent = col
                                    ? col.querySelectorAll('.dev-kanban-card').length
                                    : 0;
                            });
                        })
                        .catch(function () {
                            window.location.reload();
                        });
                },
            });
        });

        initialized.add(board);
    }

    function getTarefaOffcanvasForm() {
        var root = document.querySelector('[data-huplex-offcanvas="dev-tarefa"]');
        return root ? root.querySelector('[data-dev-tarefa-form]') : null;
    }

    function resolveProjetoId(trigger) {
        var id = trigger.getAttribute('data-kanban-projeto-id') || '';
        if (id && id !== '0') {
            return id;
        }
        var board = trigger.closest('[data-dev-kanban]');
        if (board && board.dataset.projetoFilter) {
            return board.dataset.projetoFilter;
        }
        var filterSel = document.getElementById('projeto');
        if (filterSel && filterSel.value) {
            return filterSel.value;
        }
        return '';
    }

    function filterTarefaMetas(projetoId) {
        var metaSelect = document.querySelector('[data-dev-tarefa-meta-select]');
        if (!metaSelect) {
            return;
        }
        var pid = projetoId ? String(projetoId) : '';
        metaSelect.querySelectorAll('option').forEach(function (opt) {
            if (!opt.value) {
                return;
            }
            var optPid = opt.getAttribute('data-projeto-id') || '';
            var show = !pid || optPid === '' || optPid === pid;
            opt.hidden = !show;
            opt.disabled = !show;
            if (!show && opt.selected) {
                opt.selected = false;
            }
        });
    }

    function setFieldValue(form, name, value) {
        var el = form.querySelector('[name="' + name + '"]');
        if (!el) {
            return;
        }
        el.value = value != null ? String(value) : '';
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function applyTarefaOffcanvasContext(trigger) {
        var form = getTarefaOffcanvasForm();
        if (!form || !trigger) {
            return;
        }

        var status = trigger.getAttribute('data-kanban-status') || 'BACKLOG';
        var projetoId = resolveProjetoId(trigger);
        var locked = form.getAttribute('data-locked-projeto') || '0';

        setFieldValue(form, 'status', status);

        if (locked !== '0' && locked !== '') {
            setFieldValue(form, 'projeto_id', locked);
            filterTarefaMetas(locked);
        } else if (projetoId) {
            setFieldValue(form, 'projeto_id', projetoId);
            filterTarefaMetas(projetoId);
        } else {
            setFieldValue(form, 'projeto_id', '');
            filterTarefaMetas('');
        }

        setFieldValue(form, 'titulo', '');
        setFieldValue(form, 'descricao', '');
        setFieldValue(form, 'prioridade', 'MEDIA');
        setFieldValue(form, 'meta_id', '');

        if (trigger.hasAttribute('data-kanban-add')) {
            document.querySelectorAll('.kanban-column-add-card.is-active').forEach(function (btn) {
                btn.classList.remove('is-active');
            });
            trigger.classList.add('is-active');
        }
    }

    function focusTarefaTitulo() {
        var form = getTarefaOffcanvasForm();
        if (!form) {
            return;
        }
        var titulo = form.querySelector('[name="titulo"]');
        if (titulo) {
            window.setTimeout(function () {
                titulo.focus();
            }, 300);
        }
    }

    var tarefaOffcanvasClickBound = false;

    function initKanbanTarefaOffcanvas() {
        if (tarefaOffcanvasClickBound) {
            return;
        }
        tarefaOffcanvasClickBound = true;

        document.addEventListener(
            'click',
            function (e) {
                var trigger = e.target.closest('[data-kanban-add], [data-huplex-offcanvas-open="dev-tarefa"]');
                if (!trigger) {
                    return;
                }
                if (trigger.closest('[data-kanban-add-disabled]')) {
                    return;
                }
                e.preventDefault();
                e.stopImmediatePropagation();
                applyTarefaOffcanvasContext(trigger);
                if (window.HuplexOffcanvas && typeof window.HuplexOffcanvas.open === 'function') {
                    window.HuplexOffcanvas.open('dev-tarefa');
                    focusTarefaTitulo();
                }
            },
            true
        );

        document.addEventListener('change', function (e) {
            var sel = e.target.closest('[name="projeto_id"]');
            if (!sel || !sel.closest('[data-dev-tarefa-form], [data-dev-tarefa-edit-form]')) {
                return;
            }
            filterTarefaMetas(sel.value);
        });

        var form = getTarefaOffcanvasForm();
        if (form) {
            var locked = form.getAttribute('data-locked-projeto') || '0';
            filterTarefaMetas(locked !== '0' ? locked : '');
        }
        var editForm = getTarefaEditOffcanvasForm();
        if (editForm) {
            var lockedEdit = editForm.getAttribute('data-locked-projeto') || '0';
            filterTarefaMetas(lockedEdit !== '0' ? lockedEdit : '');
        }
    }

    function getTarefaEditOffcanvasForm() {
        var root = document.querySelector('[data-huplex-offcanvas="dev-tarefa-edit"]');
        return root ? root.querySelector('[data-dev-tarefa-edit-form]') : null;
    }

    function applyTarefaEditFromCard(card) {
        var form = getTarefaEditOffcanvasForm();
        if (!form || !card) {
            return;
        }

        var board = card.closest('[data-dev-kanban]');
        var template = form.getAttribute('data-edit-url-template') || (board ? board.dataset.editUrlTemplate : '');
        var taskId = card.dataset.taskId;

        if (template && taskId) {
            form.action = taskUrl(template, taskId);
        }

        var idField = form.querySelector('[data-dev-tarefa-edit-id]');
        if (idField) {
            idField.value = taskId || '';
        }

        var locked = form.getAttribute('data-locked-projeto') || '0';
        var projetoId = locked !== '0' && locked !== '' ? locked : card.dataset.taskProjetoId || '';

        setFieldValue(form, 'projeto_id', projetoId);
        setFieldValue(form, 'titulo', card.dataset.taskTitulo || '');
        setFieldValue(form, 'descricao', card.dataset.taskDescricao || '');
        setFieldValue(form, 'status', card.dataset.taskStatus || 'BACKLOG');
        setFieldValue(form, 'prioridade', card.dataset.taskPrioridade || 'MEDIA');
        setFieldValue(form, 'meta_id', card.dataset.taskMetaId || '');
        filterTarefaMetas(projetoId);
    }

    function focusTarefaEditTitulo() {
        var form = getTarefaEditOffcanvasForm();
        if (!form) {
            return;
        }
        var titulo = form.querySelector('[name="titulo"]');
        if (titulo) {
            window.setTimeout(function () {
                titulo.focus();
                titulo.select();
            }, 300);
        }
    }

    var cardActionsBound = false;

    function initKanbanCardActions() {
        if (cardActionsBound) {
            return;
        }
        cardActionsBound = true;

        document.addEventListener('click', function (e) {
            var editBtn = e.target.closest('[data-kanban-edit]');
            if (editBtn) {
                e.preventDefault();
                e.stopPropagation();
                var card = editBtn.closest('.dev-kanban-card');
                if (!card) {
                    return;
                }
                applyTarefaEditFromCard(card);
                if (window.HuplexOffcanvas && typeof window.HuplexOffcanvas.open === 'function') {
                    window.HuplexOffcanvas.open('dev-tarefa-edit');
                    focusTarefaEditTitulo();
                }
                return;
            }

            var deleteBtn = e.target.closest('[data-kanban-delete]');
            if (!deleteBtn) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();

            var cardDel = deleteBtn.closest('.dev-kanban-card');
            var boardDel = cardDel ? cardDel.closest('[data-dev-kanban]') : null;
            if (!cardDel || !boardDel) {
                return;
            }

            var titulo = deleteBtn.getAttribute('data-task-titulo') || cardDel.dataset.taskTitulo || 'esta tarefa';
            if (!window.confirm('Excluir "' + titulo + '"? Esta ação não pode ser desfeita.')) {
                return;
            }

            var deleteTemplate = boardDel.dataset.deleteUrlTemplate;
            var csrf = boardDel.dataset.csrfDelete;
            var taskIdDel = deleteBtn.getAttribute('data-task-id') || cardDel.dataset.taskId;
            if (!deleteTemplate || !csrf || !taskIdDel) {
                return;
            }

            var body = new FormData();
            body.append('_token', csrf);
            if (boardDel.dataset.redirect) {
                body.append('redirect', boardDel.dataset.redirect);
            } else {
                body.append('redirect_view', 'kanban');
            }
            if (boardDel.dataset.projetoFilter) {
                body.append('projeto_filter', boardDel.dataset.projetoFilter);
            }

            fetch(taskUrl(deleteTemplate, taskIdDel), {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
            }).then(function () {
                window.location.reload();
            });
        });
    }

    function initKanbanIn(root) {
        var scope = root || document;
        scope.querySelectorAll('[data-dev-kanban]').forEach(function (board) {
            var panel = board.closest('[data-hub-panel]');
            if (panel && panel.hidden) {
                return;
            }
            initBoard(board);
        });
    }

    function initCoreProjetosTabs() {
        var root = document.querySelector('[data-core-projetos-root]');
        var tabs = document.querySelector('.page-lead-zone--hub-tabs [data-hub-tabs]');
        if (!root || !tabs) {
            return;
        }

        function activateTab(name) {
            tabs.querySelectorAll('[data-hub-tab]').forEach(function (btn) {
                var active = btn.getAttribute('data-hub-tab') === name;
                btn.classList.toggle('hub-overview-tab--active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            root.querySelectorAll('[data-hub-panel]').forEach(function (panel) {
                var active = panel.getAttribute('data-hub-panel') === name;
                panel.hidden = !active;
                panel.classList.toggle('hub-tab-panel--active', active);
            });
            document.querySelectorAll('[data-core-toolbar-for]').forEach(function (el) {
                var show = el.getAttribute('data-core-toolbar-for') === name;
                el.hidden = !show;
                el.style.display = show ? '' : 'none';
            });
            if (name === 'permissions') {
                document.querySelectorAll('.core-projetos-toolbar').forEach(function (el) {
                    el.hidden = true;
                    el.style.display = 'none';
                });
            }
            try {
                var url = new URL(window.location.href);
                url.searchParams.set('view', name);
                window.history.replaceState({}, '', url.pathname + url.search);
            } catch (e) { /* ignore */ }
            if (name === 'kanban') {
                initKanbanIn(root);
            }
        }

        tabs.querySelectorAll('[data-hub-tab]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                activateTab(btn.getAttribute('data-hub-tab'));
            });
        });

        activateTab(root.getAttribute('data-initial-view') || 'kanban');
    }

    function boot() {
        initKanbanTarefaOffcanvas();
        initKanbanCardActions();
        initCoreProjetosTabs();
        initKanbanIn(document);
    }

    window.DevKanban = { initIn: initKanbanIn, initBoard: initBoard, boot: boot };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
    window.addEventListener('load', boot);
})();
