import { session } from '@hotwired/turbo';
import { Application } from '@hotwired/stimulus';
import CoreProjetosController from './controllers/core_projetos_controller.js';
import DevKanbanBoardController from './controllers/dev_kanban_board_controller.js';

/** Turbo Frame nas abas — sem Turbo Drive global (não quebra AdminLTE/sidebar). */
session.drive = false;

const stimulus = Application.start();
stimulus.register('core-projetos', CoreProjetosController);
stimulus.register('dev-kanban-board', DevKanbanBoardController);

document.addEventListener('turbo:frame-load', (event) => {
    if (event.target.id !== 'core-projetos-frame') {
        return;
    }
    document.querySelectorAll('.kanban-column-add-card.is-active').forEach((btn) => {
        btn.classList.remove('is-active');
    });
});

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
