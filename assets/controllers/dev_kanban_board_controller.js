import { Controller } from '@hotwired/stimulus';

/**
 * Kanban Projetos e Metas — drag-and-drop (SortableJS) + persistência AJAX.
 */
export default class extends Controller {
    static targets = ['column', 'count'];

    static values = {
        moveUrlTemplate: String,
        csrf: String,
        projetoFilter: { type: String, default: '' },
        redirect: { type: String, default: '' },
        editUrlTemplate: { type: String, default: '' },
        deleteUrlTemplate: { type: String, default: '' },
        csrfDelete: { type: String, default: '' },
        addDisabled: { type: Boolean, default: false },
    };

    connect() {
        this.sortables = [];
        this.scheduleInitSortable();
        this.applyProjetoFilter(this.projetoFilterValue);
    }

    /** Filtra cards por projeto sem recarregar a página. */
    applyProjetoFilter(projetoId) {
        const id = projetoId ? String(projetoId) : '';
        this.projetoFilterValue = id;

        this.element.querySelectorAll('.dev-kanban-card').forEach((card) => {
            const match = !id || card.dataset.taskProjetoId === id;
            card.classList.toggle('dev-kanban-card--hidden-filter', !match);
        });

        this.element.querySelectorAll('[data-kanban-add]').forEach((btn) => {
            if (id) {
                btn.setAttribute('data-kanban-projeto-id', id);
            }
        });

        this.updateCounts();
        return this.visibleCardCount();
    }

    visibleCardCount() {
        return this.element.querySelectorAll('.dev-kanban-card:not(.dev-kanban-card--hidden-filter)').length;
    }

    scheduleInitSortable(attempt = 0) {
        if (typeof Sortable !== 'undefined') {
            this.initSortable();
            return;
        }
        if (attempt < 60) {
            window.setTimeout(() => this.scheduleInitSortable(attempt + 1), 50);
        }
    }

    disconnect() {
        this.destroySortable();
    }

    /** Chamado ao exibir a aba Kanban (painel estava hidden). */
    refresh() {
        this.destroySortable();
        this.initSortable();
    }

    onClick(event) {
        const editBtn = event.target.closest('[data-kanban-edit]');
        if (editBtn) {
            event.preventDefault();
            event.stopPropagation();
            this.openEdit(editBtn.closest('.dev-kanban-card'));
            return;
        }

        const deleteBtn = event.target.closest('[data-kanban-delete]');
        if (deleteBtn) {
            event.preventDefault();
            event.stopPropagation();
            this.deleteCard(deleteBtn);
        }
    }

    destroySortable() {
        this.sortables.forEach((s) => s.destroy());
        this.sortables = [];
    }

    initSortable() {
        if (typeof Sortable === 'undefined') {
            return;
        }
        if (!this.moveUrlTemplateValue || !this.csrfValue) {
            return;
        }

        this.columnTargets.forEach((columnEl) => {
            const sortable = Sortable.create(columnEl, {
                group: 'dev-kanban',
                animation: 160,
                draggable: '.dev-kanban-card',
                ghostClass: 'dev-kanban-ghost',
                dragClass: 'dev-kanban-drag',
                filter: 'a, button, input, select, textarea, label, .kanban-card-actions, .kanban-card-menu-btn, .dropdown-menu',
                preventOnFilter: true,
                delay: 80,
                delayOnTouchOnly: true,
                touchStartThreshold: 3,
                forceFallback: true,
                fallbackOnBody: true,
                swapThreshold: 0.65,
                onStart: () => this.closeOpenDropdowns(),
                onEnd: (evt) => this.onDragEnd(evt),
            });
            this.sortables.push(sortable);
        });
    }

    closeOpenDropdowns() {
        this.element.querySelectorAll('.kanban-card-actions .dropdown-menu.show').forEach((menu) => {
            menu.classList.remove('show');
        });
        this.element.querySelectorAll('.kanban-card-menu-btn[aria-expanded="true"]').forEach((btn) => {
            btn.setAttribute('aria-expanded', 'false');
        });
    }

    async onDragEnd(evt) {
        const card = evt.item;
        const taskId = card.dataset.taskId;
        const fromStatus = evt.from.dataset.status;
        const newStatus = evt.to.dataset.status;
        const newIndex = evt.newIndex;

        if (!taskId || !newStatus) {
            return;
        }
        if (fromStatus === newStatus && evt.oldIndex === newIndex) {
            return;
        }

        const body = new FormData();
        body.append('_token', this.csrfValue);
        body.append('status', newStatus);
        body.append('ordem', String(newIndex));
        body.append('view', 'kanban');
        if (this.projetoFilterValue) {
            body.append('projeto_filter', this.projetoFilterValue);
        }
        if (this.redirectValue) {
            body.append('redirect', this.redirectValue);
        }

        card.classList.add('dev-kanban-card--saving');

        try {
            const res = await fetch(this.taskUrl(this.moveUrlTemplateValue, taskId), {
                method: 'POST',
                body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });
            const ct = res.headers.get('content-type') || '';
            if (!res.ok || ct.indexOf('json') === -1) {
                throw new Error('move_failed');
            }
            const data = await res.json();
            if (!data?.ok) {
                throw new Error('move_rejected');
            }
            card.dataset.taskStatus = newStatus;
            card.classList.remove('dev-kanban-card--saving');
            this.updateCounts();
            this.dispatch('moved', { detail: { taskId, status: newStatus, ordem: newIndex } });
        } catch {
            card.classList.remove('dev-kanban-card--saving');
            this.revertDrag(evt);
            this.showToast('Não foi possível mover a tarefa. Tente novamente.', 'error');
        }
    }

    revertDrag(evt) {
        const item = evt.item;
        const from = evt.from;
        const ref = from.children[evt.oldIndex] || null;
        if (ref === item) {
            return;
        }
        from.insertBefore(item, ref);
        this.updateCounts();
    }

    updateCounts() {
        this.countTargets.forEach((badge) => {
            const status = badge.dataset.kanbanCount;
            const col = this.element.querySelector(`[data-kanban-column="${status}"]`);
            badge.textContent = col
                ? col.querySelectorAll('.dev-kanban-card:not(.dev-kanban-card--hidden-filter)').length
                : 0;
        });
    }

    openEdit(card) {
        if (!card) {
            return;
        }
        const root = this.element.closest('[data-core-projetos-root]');
        if (root) {
            root.dispatchEvent(
                new CustomEvent('dev-kanban:edit', { bubbles: true, detail: { card } })
            );
        }
    }

    async deleteCard(deleteBtn) {
        const card = deleteBtn.closest('.dev-kanban-card');
        if (!card) {
            return;
        }

        const titulo =
            deleteBtn.getAttribute('data-task-titulo') || card.dataset.taskTitulo || 'esta tarefa';
        if (!window.confirm(`Excluir "${titulo}"? Esta ação não pode ser desfeita.`)) {
            return;
        }

        const deleteTemplate = this.deleteUrlTemplateValue;
        const csrf = this.csrfDeleteValue;
        const taskId = deleteBtn.getAttribute('data-task-id') || card.dataset.taskId;
        if (!deleteTemplate || !csrf || !taskId) {
            return;
        }

        const body = new FormData();
        body.append('_token', csrf);
        if (this.redirectValue) {
            body.append('redirect', this.redirectValue);
        } else {
            body.append('redirect_view', 'kanban');
        }
        if (this.projetoFilterValue) {
            body.append('projeto_filter', this.projetoFilterValue);
        }

        card.classList.add('dev-kanban-card--saving');

        try {
            const res = await fetch(this.taskUrl(deleteTemplate, taskId), {
                method: 'POST',
                body,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
            });
            const ct = res.headers.get('content-type') || '';
            if (!res.ok || ct.indexOf('json') === -1) {
                throw new Error('delete_failed');
            }
            const data = await res.json();
            if (!data?.ok) {
                throw new Error('delete_rejected');
            }
            card.remove();
            this.updateCounts();
            this.showToast('Tarefa excluída.', 'success');
            this.dispatch('deleted', { detail: { taskId } });
        } catch {
            card.classList.remove('dev-kanban-card--saving');
            this.showToast('Não foi possível excluir a tarefa.', 'error');
        }
    }

    taskUrl(template, taskId) {
        const id = String(taskId);
        return template
            .replace(/\/tarefas\/0(\/|$)/g, `/tarefas/${id}$1`)
            .replace(/\/0(\/|$)/g, `/${id}$1`);
    }

    showToast(message, type) {
        if (window.UnioToast?.show) {
            window.UnioToast.show(message, type);
            return;
        }
        if (type === 'error') {
            window.alert(message);
        }
    }
}
