import { Controller } from '@hotwired/stimulus';

/**
 * Projetos e Metas — abas client-side + offcanvas + dev-kanban-board.
 */
export default class extends Controller {
    static outlets = ['kanbanBoard'];

    static targets = ['projetoFilter', 'kanbanFilterEmpty'];

    static values = {
        initialView: { type: String, default: 'kanban' },
    };

    connect() {
        this.boundKanbanEdit = this.onKanbanEdit.bind(this);
        this.boundOpenTarefa = this.openTarefaOffcanvas.bind(this);
        this.boundTabClick = this.onTabClick.bind(this);
        this.element.addEventListener('dev-kanban:edit', this.boundKanbanEdit);
        document.addEventListener('click', this.boundOpenTarefa, true);
        document.addEventListener('click', this.boundTabClick);
        this.initOffcanvasMetaFilters();
        this.activateTab(this.initialViewValue);
        if (this.hasProjetoFilterTarget) {
            this.filterKanban();
        }
    }

    disconnect() {
        this.element.removeEventListener('dev-kanban:edit', this.boundKanbanEdit);
        document.removeEventListener('click', this.boundOpenTarefa, true);
        document.removeEventListener('click', this.boundTabClick);
    }

    filterKanban() {
        if (!this.hasProjetoFilterTarget) {
            return;
        }
        const projetoId = this.projetoFilterTarget.value;
        let visible = 0;
        this.kanbanBoardOutlets.forEach((board) => {
            visible = board.applyProjetoFilter(projetoId);
        });

        if (this.hasKanbanFilterEmptyTarget) {
            const showEmpty = projetoId !== '' && visible === 0;
            this.kanbanFilterEmptyTarget.hidden = !showEmpty;
        }

        try {
            const url = new URL(window.location.href);
            url.searchParams.set('view', 'kanban');
            if (projetoId) {
                url.searchParams.set('projeto', projetoId);
            } else {
                url.searchParams.delete('projeto');
            }
            window.history.replaceState({}, '', url.pathname + url.search);
        } catch {
            /* ignore */
        }
    }

    onTabClick(event) {
        const btn = event.target.closest('[data-hub-tab]');
        if (!btn || !btn.closest('.page-lead-zone--hub-tabs [data-hub-tabs]')) {
            return;
        }
        event.preventDefault();
        const name = btn.getAttribute('data-hub-tab');
        if (name) {
            this.activateTab(name);
        }
    }

    activateTab(name) {
        const tabsRoot = document.querySelector('.page-lead-zone--hub-tabs [data-hub-tabs]');
        if (tabsRoot) {
            tabsRoot.querySelectorAll('[data-hub-tab]').forEach((btn) => {
                const active = btn.getAttribute('data-hub-tab') === name;
                btn.classList.toggle('hub-overview-tab--active', active);
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        this.element.querySelectorAll('[data-hub-panel]').forEach((panel) => {
            const active = panel.getAttribute('data-hub-panel') === name;
            panel.hidden = !active;
            panel.classList.toggle('hub-tab-panel--active', active);
        });

        this.element.querySelectorAll('[data-core-toolbar-for]').forEach((el) => {
            const show = el.getAttribute('data-core-toolbar-for') === name;
            el.hidden = !show;
            el.style.display = show ? '' : 'none';
        });

        if (name === 'permissions') {
            document.querySelectorAll('.core-projetos-toolbar').forEach((el) => {
                el.hidden = true;
                el.style.display = 'none';
            });
        }

        try {
            const url = new URL(window.location.href);
            url.searchParams.set('view', name);
            const projeto = url.searchParams.get('projeto');
            if (name !== 'kanban' && projeto) {
                url.searchParams.delete('projeto');
            }
            window.history.replaceState({}, '', url.pathname + url.search);
        } catch {
            /* ignore */
        }

        if (name === 'kanban') {
            this.kanbanBoardOutlets.forEach((outlet) => outlet.refresh());
        }
    }

    openTarefaOffcanvas(event) {
        const trigger = event.target.closest('[data-kanban-add], [data-unio-offcanvas-open="dev-tarefa"]');
        if (!trigger) {
            return;
        }
        if (trigger.closest('[data-kanban-add-disabled]')) {
            return;
        }
        event.preventDefault();
        event.stopImmediatePropagation();
        this.applyTarefaOffcanvasContext(trigger);
        window.UnioOffcanvas?.open('dev-tarefa');
        this.focusTarefaTitulo();
    }

    onKanbanEdit(event) {
        const card = event.detail?.card;
        if (!card) {
            return;
        }
        this.applyTarefaEditFromCard(card);
        window.UnioOffcanvas?.open('dev-tarefa-edit');
        this.focusTarefaEditTitulo();
    }

    getTarefaOffcanvasForm() {
        const root = document.querySelector('[data-unio-offcanvas="dev-tarefa"]');
        return root ? root.querySelector('[data-dev-tarefa-form]') : null;
    }

    getTarefaEditOffcanvasForm() {
        const root = document.querySelector('[data-unio-offcanvas="dev-tarefa-edit"]');
        return root ? root.querySelector('[data-dev-tarefa-edit-form]') : null;
    }

    resolveProjetoId(trigger) {
        let id = trigger.getAttribute('data-kanban-projeto-id') || '';
        if (id && id !== '0') {
            return id;
        }
        const board = trigger.closest('[data-controller~="dev-kanban-board"]');
        if (board?.dataset.devKanbanBoardProjetoFilterValue) {
            return board.dataset.devKanbanBoardProjetoFilterValue;
        }
        const filterSel = document.getElementById('projeto');
        if (filterSel?.value) {
            return filterSel.value;
        }
        return '';
    }

    filterTarefaMetas(projetoId) {
        const metaSelect = document.querySelector('[data-dev-tarefa-meta-select]');
        if (!metaSelect) {
            return;
        }
        const pid = projetoId ? String(projetoId) : '';
        metaSelect.querySelectorAll('option').forEach((opt) => {
            if (!opt.value) {
                return;
            }
            const optPid = opt.getAttribute('data-projeto-id') || '';
            const show = !pid || optPid === '' || optPid === pid;
            opt.hidden = !show;
            opt.disabled = !show;
            if (!show && opt.selected) {
                opt.selected = false;
            }
        });
    }

    setFieldValue(form, name, value) {
        const el = form.querySelector(`[name="${name}"]`);
        if (!el) {
            return;
        }
        el.value = value != null ? String(value) : '';
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    applyTarefaOffcanvasContext(trigger) {
        const form = this.getTarefaOffcanvasForm();
        if (!form || !trigger) {
            return;
        }

        const status = trigger.getAttribute('data-kanban-status') || 'BACKLOG';
        const projetoId = this.resolveProjetoId(trigger);
        const locked = form.getAttribute('data-locked-projeto') || '0';

        this.setFieldValue(form, 'status', status);

        if (locked !== '0' && locked !== '') {
            this.setFieldValue(form, 'projeto_id', locked);
            this.filterTarefaMetas(locked);
        } else if (projetoId) {
            this.setFieldValue(form, 'projeto_id', projetoId);
            this.filterTarefaMetas(projetoId);
        } else {
            this.setFieldValue(form, 'projeto_id', '');
            this.filterTarefaMetas('');
        }

        this.setFieldValue(form, 'titulo', '');
        this.setFieldValue(form, 'descricao', '');
        this.setFieldValue(form, 'prioridade', 'MEDIA');
        this.setFieldValue(form, 'meta_id', '');

        if (trigger.hasAttribute('data-kanban-add')) {
            document.querySelectorAll('.kanban-column-add-card.is-active').forEach((btn) => {
                btn.classList.remove('is-active');
            });
            trigger.classList.add('is-active');
        }
    }

    applyTarefaEditFromCard(card) {
        const form = this.getTarefaEditOffcanvasForm();
        if (!form || !card) {
            return;
        }

        const board = card.closest('[data-controller~="dev-kanban-board"]');
        const template =
            form.getAttribute('data-edit-url-template') ||
            board?.dataset.devKanbanBoardEditUrlTemplateValue ||
            '';
        const taskId = card.dataset.taskId;

        if (template && taskId) {
            form.action = this.taskUrl(template, taskId);
        }

        const idField = form.querySelector('[data-dev-tarefa-edit-id]');
        if (idField) {
            idField.value = taskId || '';
        }

        const locked = form.getAttribute('data-locked-projeto') || '0';
        const projetoId = locked !== '0' && locked !== '' ? locked : card.dataset.taskProjetoId || '';

        this.setFieldValue(form, 'projeto_id', projetoId);
        this.setFieldValue(form, 'titulo', card.dataset.taskTitulo || '');
        this.setFieldValue(form, 'descricao', card.dataset.taskDescricao || '');
        this.setFieldValue(form, 'status', card.dataset.taskStatus || 'BACKLOG');
        this.setFieldValue(form, 'prioridade', card.dataset.taskPrioridade || 'MEDIA');
        this.setFieldValue(form, 'meta_id', card.dataset.taskMetaId || '');
        this.filterTarefaMetas(projetoId);
    }

    taskUrl(template, taskId) {
        const id = String(taskId);
        return template
            .replace(/\/tarefas\/0(\/|$)/g, `/tarefas/${id}$1`)
            .replace(/\/0(\/|$)/g, `/${id}$1`);
    }

    focusTarefaTitulo() {
        const form = this.getTarefaOffcanvasForm();
        const titulo = form?.querySelector('[name="titulo"]');
        if (titulo) {
            window.setTimeout(() => titulo.focus(), 300);
        }
    }

    focusTarefaEditTitulo() {
        const form = this.getTarefaEditOffcanvasForm();
        const titulo = form?.querySelector('[name="titulo"]');
        if (titulo) {
            window.setTimeout(() => {
                titulo.focus();
                titulo.select();
            }, 300);
        }
    }

    initOffcanvasMetaFilters() {
        const form = this.getTarefaOffcanvasForm();
        if (form) {
            const locked = form.getAttribute('data-locked-projeto') || '0';
            this.filterTarefaMetas(locked !== '0' ? locked : '');
        }
        const editForm = this.getTarefaEditOffcanvasForm();
        if (editForm) {
            const lockedEdit = editForm.getAttribute('data-locked-projeto') || '0';
            this.filterTarefaMetas(lockedEdit !== '0' ? lockedEdit : '');
        }
    }
}
