/**
 * Projetos e Metas — abas (hub) + kanban SortableJS
 */
(function () {
    'use strict';

    var initialized = new WeakSet();

    function moveUrl(template, taskId) {
        if (!template) {
            return '';
        }
        var id = String(taskId);
        return template
            .replace(/\/tarefas\/0\/mover/, '/tarefas/' + id + '/mover')
            .replace(/\/tarefas\/__TASK_ID__\/mover/, '/tarefas/' + id + '/mover')
            .replace(/\/0\/mover$/, '/' + id + '/mover');
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
                filter: 'a, button, input, select, textarea, label',
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
