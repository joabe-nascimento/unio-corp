import { Application } from '@hotwired/stimulus';
import CoreProjetosController from './controllers/core_projetos_controller.js';
import DevKanbanBoardController from './controllers/dev_kanban_board_controller.js';

const stimulus = Application.start();
stimulus.register('core-projetos', CoreProjetosController);
stimulus.register('dev-kanban-board', DevKanbanBoardController);

document.addEventListener('change', (event) => {
    const sel = event.target;
    if (sel.id !== 'projeto' || !sel.closest('#kanban-projeto-filter')) {
        return;
    }
    const url = new URL(window.location.href);
    url.searchParams.set('view', 'kanban');
    if (sel.value) {
        url.searchParams.set('projeto', sel.value);
    } else {
        url.searchParams.delete('projeto');
    }
    window.location.assign(url.toString());
});
