import { session } from '@hotwired/turbo';
import { Application } from '@hotwired/stimulus';
import CoreProjetosController from './controllers/core_projetos_controller.js';
import DevKanbanBoardController from './controllers/dev_kanban_board_controller.js';

/** Turbo Frame nas abas — sem Turbo Drive global (não quebra AdminLTE/sidebar). */
session.drive = false;

const stimulus = Application.start();
stimulus.register('core-projetos', CoreProjetosController);
stimulus.register('dev-kanban-board', DevKanbanBoardController);

/** Sincroniza abas no lead (fora do frame) com a URL após navegação Turbo. */
function syncCoreProjetosTabs() {
    const root = document.querySelector('.page-lead-zone--hub-tabs');
    if (!root) {
        return;
    }
    let view = 'kanban';
    try {
        view = new URL(window.location.href).searchParams.get('view') || 'kanban';
    } catch {
        /* ignore */
    }
    root.querySelectorAll('[data-hub-tab]').forEach((tab) => {
        const active = tab.getAttribute('data-hub-tab') === view;
        tab.classList.toggle('hub-overview-tab--active', active);
        tab.setAttribute('aria-selected', active ? 'true' : 'false');
    });
}

document.addEventListener('turbo:frame-load', (event) => {
    if (event.target.id !== 'core-projetos-frame') {
        return;
    }
    syncCoreProjetosTabs();
    document.querySelectorAll('.kanban-column-add-card.is-active').forEach((btn) => {
        btn.classList.remove('is-active');
    });
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', syncCoreProjetosTabs);
} else {
    syncCoreProjetosTabs();
}

document.addEventListener('change', (event) => {
    const sel = event.target;
    if (sel.id !== 'projeto' || !sel.closest('#kanban-projeto-filter')) {
        return;
    }
    const form = sel.closest('form');
    if (form?.requestSubmit) {
        form.requestSubmit();
    } else {
        form?.submit();
    }
});
